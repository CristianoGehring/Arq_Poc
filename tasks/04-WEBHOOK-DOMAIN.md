# 04 - Webhook Domain (Domínio de Webhooks)

## Objetivo
Implementar processamento **assíncrono**, **seguro** e **idempotente** de webhooks dos gateways de pagamento usando arquitetura baseada em Actions/Queries/Exceptions.

## Prioridade
🔴 ALTA - Crítico para atualização automática de status de cobranças

## Dependências
- Task 00 (Setup Inicial)
- Task 02 (Charge Domain)
- Task 03 (Payment Gateway Domain)

---

## Ordem de Implementação

### 1. Enums (app/Enums/)

#### 1.1 WebhookEventType
```php
<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos de eventos de webhook
 */
enum WebhookEventType: string
{
    case CHARGE_CREATED = 'charge.created';
    case CHARGE_UPDATED = 'charge.updated';
    case CHARGE_PAID = 'charge.paid';
    case CHARGE_CANCELLED = 'charge.cancelled';
    case CHARGE_REFUNDED = 'charge.refunded';
    case CHARGE_EXPIRED = 'charge.expired';
    case PAYMENT_RECEIVED = 'payment.received';
    case UNKNOWN = 'unknown';

    /**
     * Verifica se evento é crítico (requer processamento imediato)
     */
    public function isCritical(): bool
    {
        return in_array($this, [
            self::CHARGE_PAID,
            self::CHARGE_CANCELLED,
            self::CHARGE_REFUNDED,
        ]);
    }

    /**
     * Label humanizado
     */
    public function label(): string
    {
        return match($this) {
            self::CHARGE_CREATED => 'Cobrança Criada',
            self::CHARGE_UPDATED => 'Cobrança Atualizada',
            self::CHARGE_PAID => 'Cobrança Paga',
            self::CHARGE_CANCELLED => 'Cobrança Cancelada',
            self::CHARGE_REFUNDED => 'Cobrança Reembolsada',
            self::CHARGE_EXPIRED => 'Cobrança Expirada',
            self::PAYMENT_RECEIVED => 'Pagamento Recebido',
            self::UNKNOWN => 'Desconhecido',
        };
    }
}
```

#### 1.2 WebhookStatus
```php
<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status de processamento do webhook
 */
enum WebhookStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';
    case FAILED = 'failed';
    case IGNORED = 'ignored'; // Quando não há ação necessária

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pendente',
            self::PROCESSING => 'Processando',
            self::PROCESSED => 'Processado',
            self::FAILED => 'Falhou',
            self::IGNORED => 'Ignorado',
        };
    }

    /**
     * Verifica se pode ser reprocessado
     */
    public function canRetry(): bool
    {
        return in_array($this, [self::FAILED]);
    }
}
```

**Tarefas:**
- [ ] Criar `app/Enums/WebhookEventType.php`
- [ ] Criar `app/Enums/WebhookStatus.php`

---

### 2. Custom Exceptions (app/Exceptions/)

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

abstract class WebhookException extends Exception
{
    protected int $statusCode = 500;

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => $this->getErrorCode(),
        ], $this->statusCode);
    }

    abstract protected function getErrorCode(): string;
}

/**
 * Webhook com assinatura inválida (já existe em PaymentGatewayException)
 * Reutilizar: InvalidWebhookSignatureException
 */

/**
 * Webhook não pode ser processado
 */
class WebhookProcessingException extends WebhookException
{
    protected int $statusCode = 422;

    public function __construct(string $reason)
    {
        parent::__construct("Webhook processing failed: {$reason}");
    }

    protected function getErrorCode(): string
    {
        return 'webhook_processing_failed';
    }
}

/**
 * Cobrança referenciada no webhook não foi encontrada
 */
class WebhookChargeNotFoundException extends WebhookException
{
    protected int $statusCode = 404;

    public function __construct(string $gatewayChargeId)
    {
        parent::__construct("Charge not found for webhook: {$gatewayChargeId}");
    }

    protected function getErrorCode(): string
    {
        return 'webhook_charge_not_found';
    }
}
```

**Tarefas:**
- [ ] Criar `app/Exceptions/WebhookException.php`
- [ ] Criar `app/Exceptions/WebhookProcessingException.php`
- [ ] Criar `app/Exceptions/WebhookChargeNotFoundException.php`
- [ ] Registrar no `app/Exceptions/Handler.php`:
```php
$this->renderable(function (WebhookException $e) {
    return $e->render();
});
```

---

### 3. DTOs (app/DTOs/Webhook/)

```php
<?php

declare(strict_types=1);

namespace App\DTOs\Webhook;

use App\Enums\PaymentGatewayType;
use App\Enums\WebhookEventType;

/**
 * DTO para payload de webhook recebido
 */
readonly class WebhookPayloadDTO
{
    public function __construct(
        public PaymentGatewayType $gateway,
        public WebhookEventType $eventType,
        public array $payload,
        public ?string $signature = null,
    ) {}

    public static function fromRequest(
        PaymentGatewayType $gateway,
        array $data,
        ?string $signature = null
    ): self {
        return new self(
            gateway: $gateway,
            eventType: self::detectEventType($gateway, $data),
            payload: $data,
            signature: $signature,
        );
    }

    public function toArray(): array
    {
        return [
            'gateway' => $this->gateway->value,
            'event_type' => $this->eventType->value,
            'payload' => $this->payload,
            'signature' => $this->signature,
        ];
    }

    /**
     * Detecta tipo de evento baseado no payload do gateway
     */
    private static function detectEventType(PaymentGatewayType $gateway, array $data): WebhookEventType
    {
        return match($gateway) {
            PaymentGatewayType::PAGSEGURO => self::detectPagSeguroEventType($data),
            PaymentGatewayType::ASAAS => self::detectAsaasEventType($data),
            PaymentGatewayType::STONE => self::detectStoneEventType($data),
        };
    }

    private static function detectPagSeguroEventType(array $data): WebhookEventType
    {
        $status = $data['status'] ?? '';

        return match(strtolower($status)) {
            'paid', 'approved' => WebhookEventType::CHARGE_PAID,
            'cancelled', 'canceled' => WebhookEventType::CHARGE_CANCELLED,
            'refunded' => WebhookEventType::CHARGE_REFUNDED,
            'expired' => WebhookEventType::CHARGE_EXPIRED,
            default => WebhookEventType::UNKNOWN,
        };
    }

    private static function detectAsaasEventType(array $data): WebhookEventType
    {
        $event = $data['event'] ?? '';

        return match(strtolower($event)) {
            'payment_created' => WebhookEventType::CHARGE_CREATED,
            'payment_confirmed' => WebhookEventType::CHARGE_PAID,
            'payment_deleted' => WebhookEventType::CHARGE_CANCELLED,
            'payment_refunded' => WebhookEventType::CHARGE_REFUNDED,
            default => WebhookEventType::UNKNOWN,
        };
    }

    private static function detectStoneEventType(array $data): WebhookEventType
    {
        // Lógica específica do Stone
        return WebhookEventType::UNKNOWN;
    }
}
```

**Tarefas:**
- [ ] Criar `app/DTOs/Webhook/WebhookPayloadDTO.php`

---

### 4. Migration & Model

#### 4.1 Migration
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 50); // pagseguro, asaas, stone
            $table->string('event_type', 50)->default('unknown');
            $table->string('status', 20)->default('pending');
            $table->json('payload');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Índices para performance
            $table->index(['gateway', 'status']);
            $table->index('event_type');
            $table->index('status');
            $table->index('created_at');
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
```

#### 4.2 Model
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentGatewayType;
use App\Enums\WebhookEventType;
use App\Enums\WebhookStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'gateway',
        'event_type',
        'status',
        'payload',
        'error_message',
        'retry_count',
        'processed_at',
    ];

    protected $casts = [
        'gateway' => PaymentGatewayType::class,
        'event_type' => WebhookEventType::class,
        'status' => WebhookStatus::class,
        'payload' => 'array',
        'processed_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    /**
     * Scope: Apenas webhooks pendentes
     */
    public function scopePending($query)
    {
        return $query->where('status', WebhookStatus::PENDING);
    }

    /**
     * Scope: Apenas webhooks falhados
     */
    public function scopeFailed($query)
    {
        return $query->where('status', WebhookStatus::FAILED);
    }

    /**
     * Scope: Por gateway
     */
    public function scopeByGateway($query, PaymentGatewayType $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    /**
     * Scope: Por tipo de evento
     */
    public function scopeByEventType($query, WebhookEventType $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Marcar como processando
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => WebhookStatus::PROCESSING,
        ]);
    }

    /**
     * Marcar como processado
     */
    public function markAsProcessed(): void
    {
        $this->update([
            'status' => WebhookStatus::PROCESSED,
            'processed_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Marcar como falhado
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => WebhookStatus::FAILED,
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Marcar como ignorado
     */
    public function markAsIgnored(string $reason): void
    {
        $this->update([
            'status' => WebhookStatus::IGNORED,
            'error_message' => $reason,
            'processed_at' => now(),
        ]);
    }

    /**
     * Verifica se pode ser reprocessado
     */
    public function canRetry(): bool
    {
        return $this->status->canRetry() && $this->retry_count < 5;
    }
}
```

**Tarefas:**
- [ ] Criar migration `create_webhook_logs_table`
- [ ] Criar `app/Models/WebhookLog.php`
- [ ] Rodar migration: `./vendor/bin/sail artisan migrate`

---

### 5. Events (app/Events/)

```php
<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\WebhookLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: Webhook recebido
 */
class WebhookReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly WebhookLog $webhookLog
    ) {}
}

/**
 * Event: Webhook processado com sucesso
 */
class WebhookProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly WebhookLog $webhookLog
    ) {}
}

/**
 * Event: Webhook falhou ao processar
 */
class WebhookFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly WebhookLog $webhookLog,
        public readonly string $errorMessage
    ) {}
}
```

**Tarefas:**
- [ ] Criar `app/Events/WebhookReceived.php`
- [ ] Criar `app/Events/WebhookProcessed.php`
- [ ] Criar `app/Events/WebhookFailed.php`

---

### 6. Actions (Write Operations)

#### 6.1 ProcessWebhookAction
```php
<?php

declare(strict_types=1);

namespace App\Actions\Webhook;

use App\DTOs\PaymentGateway\WebhookPayloadDTO as GatewayWebhookPayloadDTO;
use App\Enums\ChargeStatus;
use App\Events\ChargePaid;
use App\Events\WebhookProcessed;
use App\Exceptions\WebhookChargeNotFoundException;
use App\Exceptions\WebhookProcessingException;
use App\Models\Charge;
use App\Models\WebhookLog;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Action: Processar webhook de gateway
 *
 * Retorna: WebhookLog model atualizado
 * Lança: WebhookProcessingException, WebhookChargeNotFoundException
 */
class ProcessWebhookAction
{
    /**
     * Processar webhook e atualizar cobrança
     *
     * @throws WebhookProcessingException
     * @throws WebhookChargeNotFoundException
     */
    public function execute(WebhookLog $webhookLog): WebhookLog
    {
        // Marcar como processando
        $webhookLog->markAsProcessing();

        try {
            // 1. Criar instância do gateway
            $gateway = PaymentGatewayFactory::create($webhookLog->gateway);

            // 2. Processar payload do gateway (normalizar dados)
            $processedData = $gateway->processWebhook($webhookLog->payload);

            // 3. Buscar cobrança pelo gateway_charge_id
            $charge = Charge::where('gateway_charge_id', $processedData->gatewayChargeId)
                ->first();

            if (!$charge) {
                // Cobrança não encontrada - marcar como ignorado
                $webhookLog->markAsIgnored("Charge not found: {$processedData->gatewayChargeId}");

                Log::warning('Webhook ignored: charge not found', [
                    'webhook_log_id' => $webhookLog->id,
                    'gateway' => $webhookLog->gateway->value,
                    'gateway_charge_id' => $processedData->gatewayChargeId,
                ]);

                throw new WebhookChargeNotFoundException($processedData->gatewayChargeId);
            }

            // 4. Mapear status do gateway para status interno
            $newStatus = $processedData->status->toChargeStatus();

            // 5. Atualizar status se mudou (idempotência)
            if ($charge->status !== $newStatus) {
                DB::transaction(function () use ($charge, $newStatus, $processedData) {
                    $updateData = ['status' => $newStatus];

                    // Se pago, atualizar paid_at
                    if ($newStatus === ChargeStatus::PAID && $processedData->paidAt) {
                        $updateData['paid_at'] = $processedData->paidAt;
                    }

                    // Atualizar metadata do gateway
                    if ($processedData->metadata) {
                        $updateData['gateway_metadata'] = $processedData->metadata;
                    }

                    $charge->update($updateData);

                    Log::info('Charge status updated via webhook', [
                        'charge_id' => $charge->id,
                        'old_status' => $charge->getOriginal('status'),
                        'new_status' => $newStatus->value,
                    ]);
                });

                // 6. Disparar eventos de negócio
                if ($newStatus === ChargeStatus::PAID) {
                    event(new ChargePaid($charge->fresh()));
                }
            }

            // 7. Marcar webhook como processado
            $webhookLog->markAsProcessed();

            // 8. Disparar evento
            event(new WebhookProcessed($webhookLog->fresh()));

            Log::info('Webhook processed successfully', [
                'webhook_log_id' => $webhookLog->id,
                'gateway' => $webhookLog->gateway->value,
                'charge_id' => $charge->id,
                'event_type' => $webhookLog->event_type->value,
            ]);

            return $webhookLog->fresh();

        } catch (WebhookChargeNotFoundException $e) {
            // Não é um erro crítico, apenas não encontramos a cobrança
            throw $e;

        } catch (\Throwable $e) {
            // Erro crítico - marcar como falhado
            $webhookLog->markAsFailed($e->getMessage());

            Log::error('Webhook processing failed', [
                'webhook_log_id' => $webhookLog->id,
                'gateway' => $webhookLog->gateway->value,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new WebhookProcessingException($e->getMessage());
        }
    }
}
```

#### 6.2 ValidateWebhookSignatureAction
```php
<?php

declare(strict_types=1);

namespace App\Actions\Webhook;

use App\Enums\PaymentGatewayType;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use Illuminate\Support\Facades\Log;

/**
 * Action: Validar assinatura de webhook
 *
 * Retorna: bool (true se válida)
 * Lança: InvalidWebhookSignatureException
 */
class ValidateWebhookSignatureAction
{
    /**
     * Validar assinatura do webhook
     *
     * @throws InvalidWebhookSignatureException
     */
    public function execute(
        PaymentGatewayType $gateway,
        string $signature,
        array $payload
    ): bool {
        // Criar instância do gateway
        $gatewayInstance = PaymentGatewayFactory::create($gateway);

        // Validar assinatura
        $isValid = $gatewayInstance->validateWebhookSignature($signature, $payload);

        if (!$isValid) {
            Log::warning('Invalid webhook signature', [
                'gateway' => $gateway->value,
                'signature' => substr($signature, 0, 10) . '...', // Log parcial
            ]);

            throw new InvalidWebhookSignatureException($gateway->value);
        }

        return true;
    }
}
```

**Tarefas:**
- [ ] Criar `app/Actions/Webhook/ProcessWebhookAction.php`
- [ ] Criar `app/Actions/Webhook/ValidateWebhookSignatureAction.php`

---

### 7. Queries (Read Operations)

```php
<?php

declare(strict_types=1);

namespace App\Queries\Webhook;

use App\Enums\PaymentGatewayType;
use App\Enums\WebhookStatus;
use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Query: Buscar webhooks pendentes
 */
class GetPendingWebhooksQuery
{
    /**
     * Buscar webhooks pendentes para reprocessamento
     */
    public function execute(int $limit = 100): Collection
    {
        return WebhookLog::pending()
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }
}

/**
 * Query: Buscar webhooks falhados
 */
class GetFailedWebhooksQuery
{
    /**
     * Buscar webhooks falhados que podem ser retentados
     */
    public function execute(int $limit = 100): Collection
    {
        return WebhookLog::failed()
            ->where('retry_count', '<', 5)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }
}

/**
 * Query: Buscar webhooks por gateway
 */
class GetWebhooksByGatewayQuery
{
    /**
     * Buscar webhooks de um gateway específico (paginado)
     */
    public function execute(PaymentGatewayType $gateway, int $perPage = 15): LengthAwarePaginator
    {
        return WebhookLog::byGateway($gateway)
            ->latest()
            ->paginate($perPage);
    }
}

/**
 * Query: Estatísticas de webhooks
 */
class GetWebhookStatsQuery
{
    /**
     * Obter estatísticas de webhooks por gateway
     */
    public function execute(?PaymentGatewayType $gateway = null): array
    {
        $query = WebhookLog::query();

        if ($gateway) {
            $query->byGateway($gateway);
        }

        return [
            'total' => $query->count(),
            'pending' => (clone $query)->where('status', WebhookStatus::PENDING)->count(),
            'processing' => (clone $query)->where('status', WebhookStatus::PROCESSING)->count(),
            'processed' => (clone $query)->where('status', WebhookStatus::PROCESSED)->count(),
            'failed' => (clone $query)->where('status', WebhookStatus::FAILED)->count(),
            'ignored' => (clone $query)->where('status', WebhookStatus::IGNORED)->count(),
        ];
    }
}
```

**Tarefas:**
- [ ] Criar `app/Queries/Webhook/GetPendingWebhooksQuery.php`
- [ ] Criar `app/Queries/Webhook/GetFailedWebhooksQuery.php`
- [ ] Criar `app/Queries/Webhook/GetWebhooksByGatewayQuery.php`
- [ ] Criar `app/Queries/Webhook/GetWebhookStatsQuery.php`

---

### 8. Jobs (Processamento Assíncrono)

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Webhook\ProcessWebhookAction;
use App\Events\WebhookFailed;
use App\Models\WebhookLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job: Processar webhook assincronamente
 */
class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public int $backoff = 300; // 5 minutos entre retries

    public function __construct(
        private readonly int $webhookLogId
    ) {}

    public function handle(ProcessWebhookAction $action): void
    {
        $webhookLog = WebhookLog::find($this->webhookLogId);

        if (!$webhookLog) {
            Log::error('Webhook log not found', [
                'webhook_log_id' => $this->webhookLogId,
            ]);
            return;
        }

        try {
            // Action cuida de toda lógica de processamento
            $action->execute($webhookLog);

        } catch (\Throwable $e) {
            Log::error('Webhook job failed', [
                'webhook_log_id' => $webhookLog->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Re-lançar para acionar retry automático
            throw $e;
        }
    }

    /**
     * Job falhou permanentemente (após todos os retries)
     */
    public function failed(\Throwable $exception): void
    {
        $webhookLog = WebhookLog::find($this->webhookLogId);

        if ($webhookLog) {
            $webhookLog->markAsFailed("Job failed permanently: {$exception->getMessage()}");
            event(new WebhookFailed($webhookLog, $exception->getMessage()));
        }

        Log::error('Webhook job failed permanently', [
            'webhook_log_id' => $this->webhookLogId,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

**Tarefas:**
- [ ] Criar `app/Jobs/ProcessWebhookJob.php`

---

### 9. Controller (HTTP Layer)

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Webhook\WebhookPayloadDTO;
use App\Enums\PaymentGatewayType;
use App\Enums\WebhookStatus;
use App\Events\WebhookReceived;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookJob;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Webhook PagSeguro
     */
    public function pagseguro(Request $request): Response
    {
        return $this->processWebhook(
            PaymentGatewayType::PAGSEGURO,
            $request
        );
    }

    /**
     * Webhook Asaas
     */
    public function asaas(Request $request): Response
    {
        return $this->processWebhook(
            PaymentGatewayType::ASAAS,
            $request
        );
    }

    /**
     * Webhook Stone
     */
    public function stone(Request $request): Response
    {
        return $this->processWebhook(
            PaymentGatewayType::STONE,
            $request
        );
    }

    /**
     * Processar webhook genérico (thin controller)
     */
    private function processWebhook(PaymentGatewayType $gateway, Request $request): Response
    {
        // 1. Logar recebimento
        Log::info('Webhook received', [
            'gateway' => $gateway->value,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // 2. Criar DTO
        $dto = WebhookPayloadDTO::fromRequest(
            gateway: $gateway,
            data: $request->all(),
            signature: $request->header('X-Signature')
                ?? $request->header('X-Hub-Signature')
                ?? $request->input('signature')
        );

        // 3. Criar log do webhook
        $webhookLog = WebhookLog::create([
            'gateway' => $dto->gateway,
            'event_type' => $dto->eventType,
            'status' => WebhookStatus::PENDING,
            'payload' => $dto->payload,
        ]);

        // 4. Disparar evento
        event(new WebhookReceived($webhookLog));

        // 5. Processar assincronamente (NÃO bloquear resposta)
        ProcessWebhookJob::dispatch($webhookLog->id);

        // 6. Retornar 204 rapidamente (< 1s)
        return response()->noContent();
    }
}
```

**Tarefas:**
- [ ] Criar `app/Http/Controllers/Api/V1/WebhookController.php`

---

### 10. Middleware (Validação de Assinatura)

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Webhook\ValidateWebhookSignatureAction;
use App\Enums\PaymentGatewayType;
use App\Exceptions\InvalidWebhookSignatureException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Middleware: Validar assinatura de webhooks
 */
class ValidateWebhookSignature
{
    public function __construct(
        private readonly ValidateWebhookSignatureAction $action
    ) {}

    public function handle(Request $request, Closure $next, string $gateway): Response
    {
        try {
            $gatewayType = PaymentGatewayType::from($gateway);
        } catch (\ValueError $e) {
            return response()->json(['error' => 'Invalid gateway'], 400);
        }

        // Extrair assinatura de múltiplas fontes possíveis
        $signature = $request->header('X-Signature')
            ?? $request->header('X-Hub-Signature')
            ?? $request->header('X-Webhook-Signature')
            ?? $request->input('signature');

        if (!$signature) {
            Log::warning('Webhook signature missing', [
                'gateway' => $gatewayType->value,
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Signature missing'], 401);
        }

        try {
            // Action valida assinatura
            $this->action->execute(
                gateway: $gatewayType,
                signature: $signature,
                payload: $request->all()
            );

            // Assinatura válida, continuar
            return $next($request);

        } catch (InvalidWebhookSignatureException $e) {
            // Exception já loga warning
            return $e->render();
        }
    }
}
```

**Tarefas:**
- [ ] Criar `app/Http/Middleware/ValidateWebhookSignature.php`
- [ ] Registrar middleware em `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'validate.webhook.signature' => \App\Http\Middleware\ValidateWebhookSignature::class,
    ]);
})
```

---

### 11. Routes (API)

```php
// routes/api.php

use App\Http\Controllers\Api\V1\WebhookController;

// Webhooks (sem autenticação, mas COM validação de assinatura)
Route::prefix('webhooks')->group(function () {
    Route::post('pagseguro', [WebhookController::class, 'pagseguro'])
        ->middleware('validate.webhook.signature:pagseguro')
        ->name('webhooks.pagseguro');

    Route::post('asaas', [WebhookController::class, 'asaas'])
        ->middleware('validate.webhook.signature:asaas')
        ->name('webhooks.asaas');

    Route::post('stone', [WebhookController::class, 'stone'])
        ->middleware('validate.webhook.signature:stone')
        ->name('webhooks.stone');
});
```

**Tarefas:**
- [ ] Adicionar rotas em `routes/api.php`

---

### 12. Commands (Artisan)

#### 12.1 RetryFailedWebhooksCommand
```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessWebhookJob;
use App\Queries\Webhook\GetFailedWebhooksQuery;
use Illuminate\Console\Command;

class RetryFailedWebhooksCommand extends Command
{
    protected $signature = 'webhooks:retry-failed
                            {--limit=100 : Maximum webhooks to retry}';

    protected $description = 'Retry failed webhook processing';

    public function handle(GetFailedWebhooksQuery $query): int
    {
        $limit = (int) $this->option('limit');
        $failedWebhooks = $query->execute($limit);

        if ($failedWebhooks->isEmpty()) {
            $this->info('No failed webhooks to retry');
            return self::SUCCESS;
        }

        $this->info("Found {$failedWebhooks->count()} failed webhooks");

        $bar = $this->output->createProgressBar($failedWebhooks->count());

        foreach ($failedWebhooks as $webhookLog) {
            if ($webhookLog->canRetry()) {
                ProcessWebhookJob::dispatch($webhookLog->id);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info('Failed webhooks dispatched for retry');

        return self::SUCCESS;
    }
}
```

#### 12.2 CleanOldWebhookLogsCommand
```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\WebhookStatus;
use App\Models\WebhookLog;
use Illuminate\Console\Command;

class CleanOldWebhookLogsCommand extends Command
{
    protected $signature = 'webhooks:clean
                            {--days=30 : Days to keep}';

    protected $description = 'Clean old processed webhook logs';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $date = now()->subDays($days);

        $count = WebhookLog::where('created_at', '<', $date)
            ->where('status', WebhookStatus::PROCESSED)
            ->delete();

        $this->info("Deleted {$count} old webhook logs");

        return self::SUCCESS;
    }
}
```

#### 12.3 WebhookStatsCommand
```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Queries\Webhook\GetWebhookStatsQuery;
use Illuminate\Console\Command;

class WebhookStatsCommand extends Command
{
    protected $signature = 'webhooks:stats';

    protected $description = 'Show webhook processing statistics';

    public function handle(GetWebhookStatsQuery $query): int
    {
        $stats = $query->execute();

        $this->table(
            ['Status', 'Count'],
            [
                ['Total', $stats['total']],
                ['Pending', $stats['pending']],
                ['Processing', $stats['processing']],
                ['Processed', $stats['processed']],
                ['Failed', $stats['failed']],
                ['Ignored', $stats['ignored']],
            ]
        );

        return self::SUCCESS;
    }
}
```

**Tarefas:**
- [ ] Criar `app/Console/Commands/RetryFailedWebhooksCommand.php`
- [ ] Criar `app/Console/Commands/CleanOldWebhookLogsCommand.php`
- [ ] Criar `app/Console/Commands/WebhookStatsCommand.php`

---

### 13. Scheduled Tasks

```php
// app/Console/Kernel.php (Laravel 10) ou routes/console.php (Laravel 11)

use Illuminate\Support\Facades\Schedule;

Schedule::command('webhooks:retry-failed --limit=50')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('webhooks:clean --days=30')
    ->daily()
    ->at('02:00');
```

**Tarefas:**
- [ ] Configurar scheduled tasks em `routes/console.php` (Laravel 11) ou `app/Console/Kernel.php` (Laravel 10)
- [ ] Configurar cron: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`

---

### 14. Testes

#### 14.1 Feature Test - Webhooks
```php
<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PaymentGatewayType;
use App\Enums\WebhookStatus;
use App\Jobs\ProcessWebhookJob;
use App\Models\WebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagseguro_webhook_creates_log(): void
    {
        Queue::fake();

        $response = $this->postJson(route('webhooks.pagseguro'), [
            'id' => 'CHG_123',
            'status' => 'paid',
        ], [
            'X-Signature' => 'valid-signature',
        ]);

        $response->assertNoContent();

        $this->assertDatabaseHas('webhook_logs', [
            'gateway' => PaymentGatewayType::PAGSEGURO->value,
            'status' => WebhookStatus::PENDING->value,
        ]);

        Queue::assertPushed(ProcessWebhookJob::class);
    }

    public function test_webhook_without_signature_is_rejected(): void
    {
        $response = $this->postJson(route('webhooks.pagseguro'), [
            'id' => 'CHG_123',
            'status' => 'paid',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Signature missing']);
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        // Mock gateway com assinatura inválida
        // ...
    }
}
```

#### 14.2 Unit Test - ProcessWebhookAction
```php
<?php

namespace Tests\Unit\Actions\Webhook;

use App\Actions\Webhook\ProcessWebhookAction;
use App\Enums\ChargeStatus;
use App\Enums\PaymentGatewayType;
use App\Enums\WebhookStatus;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\PaymentGateway;
use App\Models\WebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProcessWebhookActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_processes_webhook_and_updates_charge(): void
    {
        Event::fake();

        // Arrange
        $customer = Customer::factory()->create();
        $gateway = PaymentGateway::factory()->pagseguro()->create();
        $charge = Charge::factory()->create([
            'customer_id' => $customer->id,
            'gateway_charge_id' => 'CHG_123',
            'payment_gateway_id' => $gateway->id,
            'status' => ChargeStatus::PENDING,
        ]);

        $webhookLog = WebhookLog::create([
            'gateway' => PaymentGatewayType::PAGSEGURO,
            'event_type' => 'charge.paid',
            'status' => WebhookStatus::PENDING,
            'payload' => [
                'id' => 'CHG_123',
                'status' => 'paid',
            ],
        ]);

        // Act
        $action = new ProcessWebhookAction();
        $result = $action->execute($webhookLog);

        // Assert
        $this->assertEquals(WebhookStatus::PROCESSED, $result->status);
        $this->assertEquals(ChargeStatus::PAID, $charge->fresh()->status);
    }
}
```

#### 14.3 Unit Test - Commands
```php
<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\RetryFailedWebhooksCommand;
use App\Jobs\ProcessWebhookJob;
use App\Models\WebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RetryFailedWebhooksCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_retries_failed_webhooks(): void
    {
        Queue::fake();

        // Criar webhooks falhados
        $webhooks = WebhookLog::factory()->failed()->count(3)->create();

        $this->artisan(RetryFailedWebhooksCommand::class)
            ->expectsOutput('Found 3 failed webhooks')
            ->assertExitCode(0);

        Queue::assertPushed(ProcessWebhookJob::class, 3);
    }
}
```

**Tarefas:**
- [ ] Criar `tests/Feature/Api/V1/WebhookTest.php`
- [ ] Criar `tests/Unit/Actions/Webhook/ProcessWebhookActionTest.php`
- [ ] Criar `tests/Unit/Jobs/ProcessWebhookJobTest.php`
- [ ] Criar `tests/Unit/Console/Commands/RetryFailedWebhooksCommandTest.php`
- [ ] Criar `database/factories/WebhookLogFactory.php`
- [ ] Rodar testes: `./vendor/bin/sail artisan test`

---

### 15. Factory para Testes

```php
<?php

namespace Database\Factories;

use App\Enums\PaymentGatewayType;
use App\Enums\WebhookEventType;
use App\Enums\WebhookStatus;
use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookLogFactory extends Factory
{
    protected $model = WebhookLog::class;

    public function definition(): array
    {
        return [
            'gateway' => fake()->randomElement(PaymentGatewayType::cases()),
            'event_type' => fake()->randomElement(WebhookEventType::cases()),
            'status' => WebhookStatus::PENDING,
            'payload' => [
                'id' => fake()->uuid(),
                'status' => 'paid',
            ],
            'error_message' => null,
            'retry_count' => 0,
            'processed_at' => null,
        ];
    }

    public function processed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebhookStatus::PROCESSED,
            'processed_at' => now(),
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebhookStatus::FAILED,
            'error_message' => 'Test error',
            'retry_count' => 1,
        ]);
    }
}
```

**Tarefas:**
- [ ] Criar `database/factories/WebhookLogFactory.php`

---

## Checklist de Qualidade

### Arquitetura
- [ ] **Processamento assíncrono** (Jobs com retry)
- [ ] **Event-Driven** (disparar eventos de negócio)
- [ ] **Idempotência** (processar múltiplas vezes = mesmo resultado)
- [ ] **Actions** retornam Models (não JsonResponse)
- [ ] **Queries** usam Eloquent diretamente
- [ ] **Exceptions** controlam status codes via render()

### Código
- [ ] Type hints completos (PHP 8.2+)
- [ ] Readonly DTOs
- [ ] Exception handling robusto
- [ ] Transactions em operações críticas
- [ ] Logging estruturado

### Segurança
- [ ] **Validação de assinatura obrigatória** (hash_equals)
- [ ] Logging de tentativas suspeitas
- [ ] Rate limiting (opcional)
- [ ] Middleware de validação

### Performance
- [ ] **Resposta rápida** (< 1s, apenas criar log e disparar job)
- [ ] Processamento assíncrono via queue
- [ ] Queue workers configurados
- [ ] Timeout apropriado (60s)

### Confiabilidade
- [ ] **Retry logic** com backoff exponencial (5 minutos)
- [ ] Máximo de 3 tentativas automáticas
- [ ] Command para retry manual
- [ ] Limpeza automática de logs antigos (30 dias)
- [ ] Logging completo de todas as operações

---

## Critérios de Aceitação

✅ **Funcionalidade**
- Webhooks sendo recebidos dos 3 gateways
- Jobs processando assincronamente
- Status de cobranças atualizados corretamente
- Logs sendo criados e atualizados
- Retry automático funcionando

✅ **Segurança**
- Assinaturas validadas via middleware
- Webhooks inválidos rejeitados (401)
- Logs de tentativas suspeitas

✅ **Confiabilidade**
- Retry em caso de falha (3 tentativas)
- Idempotência garantida (mesmo webhook múltiplas vezes = mesmo resultado)
- Logs completos persistidos
- Commands de retry e limpeza funcionando

✅ **Performance**
- Resposta HTTP < 1s
- Processing assíncrono via queue
- Queue workers ativos (`php artisan queue:work`)

---

## Comandos Úteis

```bash
# Criar migration
./vendor/bin/sail artisan make:migration create_webhook_logs_table

# Rodar migrations
./vendor/bin/sail artisan migrate

# Rodar queue worker (development)
./vendor/bin/sail artisan queue:work --tries=3 --timeout=60

# Rodar queue worker (production com Supervisor)
./vendor/bin/sail artisan queue:work redis --tries=3 --timeout=60 --sleep=3

# Ver failed jobs
./vendor/bin/sail artisan queue:failed

# Retry specific failed job
./vendor/bin/sail artisan queue:retry {id}

# Retry all failed jobs
./vendor/bin/sail artisan queue:retry all

# Limpar failed jobs
./vendor/bin/sail artisan queue:flush

# Commands customizados
./vendor/bin/sail artisan webhooks:retry-failed --limit=100
./vendor/bin/sail artisan webhooks:clean --days=30
./vendor/bin/sail artisan webhooks:stats

# Rodar testes
./vendor/bin/sail artisan test --filter=Webhook
```

---

## Notas Importantes

### ⚠️ Idempotência

**CRÍTICO**: Processar o mesmo webhook múltiplas vezes deve resultar no mesmo estado final.

```php
// ✅ CORRETO: Comparar antes de atualizar
if ($charge->status !== $newStatus) {
    $charge->update(['status' => $newStatus]);
}

// ❌ ERRADO: Atualizar sempre (não idempotente)
$charge->update(['status' => $newStatus]);
```

### ⚠️ Resposta Rápida

**SEMPRE** retornar 200/204 em < 1s:

```php
// ✅ CORRETO: Criar log e dispatchar job
$webhookLog = WebhookLog::create([...]);
ProcessWebhookJob::dispatch($webhookLog->id);
return response()->noContent(); // < 1s

// ❌ ERRADO: Processar sincronamente
$action->execute($webhookLog); // Pode demorar 10s+
return response()->noContent();
```

### ⚠️ Validação de Assinatura

**OBRIGATÓRIA** para evitar webhooks falsos:

```php
// ✅ CORRETO: Usar hash_equals (previne timing attacks)
if (!hash_equals($expectedSignature, $signature)) {
    throw new InvalidWebhookSignatureException();
}

// ❌ ERRADO: Comparação direta (vulnerável a timing attacks)
if ($expectedSignature !== $signature) {
    throw new InvalidWebhookSignatureException();
}
```

### ⚠️ Queue Workers

**Production**: Usar Supervisor para manter workers ativos:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --tries=3 --timeout=60
autostart=true
autorestart=true
numprocs=3
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/laravel-worker.log
```

### ⚠️ Monitoramento

**Recomendações:**
- Monitorar failed jobs (alertar se > 10)
- Dashboard de webhooks recebidos/processados
- Logs centralizados (ELK, Splunk, etc.)
- Métricas de tempo de processamento

### ⚠️ Diferença: Actions vs Jobs

**Neste projeto:**
- ✅ **Actions**: Lógica de negócio pura (ProcessWebhookAction)
  - Retorna Models
  - Pode ser chamada de qualquer lugar
  - Testável unitariamente

- ✅ **Jobs**: Orquestração assíncrona (ProcessWebhookJob)
  - Chama Actions
  - Gerencia retry logic
  - Lida com failed()

```php
// Job (orquestração)
class ProcessWebhookJob {
    public function handle(ProcessWebhookAction $action) {
        $action->execute($this->webhookLog); // Delega para Action
    }
}

// Action (lógica de negócio)
class ProcessWebhookAction {
    public function execute(WebhookLog $webhookLog): WebhookLog {
        // Toda lógica aqui
    }
}
```

---

## Exemplo de Uso Completo

```php
// 1. Gateway envia webhook
POST /api/webhooks/pagseguro
Headers: X-Signature: abc123
Body: {"id": "CHG_123", "status": "paid"}

// 2. Middleware valida assinatura
ValidateWebhookSignature->execute() ✅

// 3. Controller cria log e dispatch job (< 1s)
$webhookLog = WebhookLog::create([...]);
ProcessWebhookJob::dispatch($webhookLog->id);
return 204; // Resposta rápida

// 4. Job processa assincronamente
ProcessWebhookJob->handle() {
    ProcessWebhookAction->execute($webhookLog) {
        // Buscar cobrança
        // Atualizar status (idempotente)
        // Disparar eventos
        // Marcar webhook como processado
    }
}

// 5. Se falhar, retry automático (3x, 5min backoff)
// 6. Se falhar 3x, marcar como failed
// 7. Command manual: webhooks:retry-failed
```

---

## Referências

- [Prompt.MD](../Prompt.MD): Arquitetura completa do projeto
- [Task 02](02-CHARGE-DOMAIN.md): Events e Listeners
- [Task 03](03-PAYMENT-GATEWAY-DOMAIN.md): Strategy Pattern e Gateway integrations
- Laravel Queues: https://laravel.com/docs/11.x/queues
- Laravel Events: https://laravel.com/docs/11.x/events
- Webhook Best Practices: https://webhooks.fyi/best-practices/
