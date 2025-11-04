# 03 - Payment Gateway Domain (Domínio de Gateways de Pagamento)

## Objetivo
Implementar a infraestrutura de integração com múltiplos gateways de pagamento usando **Strategy Pattern** e **Factory Pattern** com arquitetura baseada em Actions/Queries/Exceptions.

## Prioridade
🔴 ALTA - Necessário para processar pagamentos

## Dependências
- Task 00 (Setup Inicial)
- Task 01 (Customer Domain)
- Task 02 (Charge Domain)

---

## ⚠️ Por que Repository Pattern AQUI?

Este é o **único domínio** onde Repository Pattern é apropriado:
- ✅ Múltiplas implementações de gateways (PagSeguro, Asaas, Stone)
- ✅ Necessidade de Strategy Pattern para trocar gateways dinamicamente
- ✅ Abstração necessária para isolar lógica de integração
- ✅ Facilita testes com mocks

**Customer e Charge** não precisam de Repository porque usam apenas Eloquent (implementação única).

---

## Ordem de Implementação

### 1. Enums (app/Enums/)

#### 1.1 PaymentGatewayType
```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentGatewayType: string
{
    case PAGSEGURO = 'pagseguro';
    case ASAAS = 'asaas';
    case STONE = 'stone';

    /**
     * Retorna nome humanizado
     */
    public function label(): string
    {
        return match($this) {
            self::PAGSEGURO => 'PagSeguro',
            self::ASAAS => 'Asaas',
            self::STONE => 'Stone',
        };
    }

    /**
     * Retorna URL do gateway
     */
    public function apiUrl(): string
    {
        return match($this) {
            self::PAGSEGURO => config('services.pagseguro.api_url'),
            self::ASAAS => config('services.asaas.api_url'),
            self::STONE => config('services.stone.api_url'),
        };
    }

    /**
     * Retorna configurações do gateway
     */
    public function credentials(): array
    {
        return match($this) {
            self::PAGSEGURO => [
                'api_key' => config('services.pagseguro.api_key'),
                'api_token' => config('services.pagseguro.api_token'),
            ],
            self::ASAAS => [
                'api_key' => config('services.asaas.api_key'),
            ],
            self::STONE => [
                'api_key' => config('services.stone.api_key'),
                'api_secret' => config('services.stone.api_secret'),
            ],
        };
    }
}
```

#### 1.2 GatewayChargeStatus (para mapeamento)
```php
<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status internos do gateway (antes de mapear para ChargeStatus)
 */
enum GatewayChargeStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case FAILED = 'failed';
    case EXPIRED = 'expired';

    /**
     * Mapeia status do gateway para status interno da aplicação
     */
    public function toChargeStatus(): ChargeStatus
    {
        return match($this) {
            self::PAID => ChargeStatus::PAID,
            self::CANCELLED => ChargeStatus::CANCELLED,
            self::REFUNDED => ChargeStatus::REFUNDED,
            self::FAILED, self::EXPIRED => ChargeStatus::CANCELLED,
            default => ChargeStatus::PENDING,
        };
    }
}
```

**Tarefas:**
- [ ] Criar `app/Enums/PaymentGatewayType.php`
- [ ] Criar `app/Enums/GatewayChargeStatus.php`

---

### 2. Custom Exceptions (app/Exceptions/)

#### 2.1 Base Exception
```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

abstract class PaymentGatewayException extends Exception
{
    protected int $statusCode = 500;

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => $this->getErrorCode(),
        ], $this->statusCode);
    }

    /**
     * Código de erro único para identificação
     */
    abstract protected function getErrorCode(): string;
}
```

#### 2.2 Specific Exceptions
```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\PaymentGatewayType;
use Illuminate\Http\JsonResponse;

/**
 * Gateway não encontrado ou inativo
 */
class GatewayNotFoundException extends PaymentGatewayException
{
    protected int $statusCode = 404;

    public function __construct(PaymentGatewayType $type)
    {
        parent::__construct("Payment gateway '{$type->value}' not found or inactive");
    }

    protected function getErrorCode(): string
    {
        return 'gateway_not_found';
    }
}

/**
 * Erro de conexão com gateway
 */
class GatewayConnectionException extends PaymentGatewayException
{
    protected int $statusCode = 503;

    public function __construct(string $gateway, string $reason)
    {
        parent::__construct("Failed to connect to {$gateway}: {$reason}");
    }

    protected function getErrorCode(): string
    {
        return 'gateway_connection_failed';
    }
}

/**
 * Erro de autenticação com gateway
 */
class GatewayAuthenticationException extends PaymentGatewayException
{
    protected int $statusCode = 401;

    public function __construct(string $gateway)
    {
        parent::__construct("Authentication failed for gateway {$gateway}");
    }

    protected function getErrorCode(): string
    {
        return 'gateway_authentication_failed';
    }
}

/**
 * Resposta inválida do gateway
 */
class InvalidGatewayResponseException extends PaymentGatewayException
{
    protected int $statusCode = 502;

    public function __construct(string $gateway, string $reason)
    {
        parent::__construct("Invalid response from {$gateway}: {$reason}");
    }

    protected function getErrorCode(): string
    {
        return 'invalid_gateway_response';
    }
}

/**
 * Webhook com assinatura inválida
 */
class InvalidWebhookSignatureException extends PaymentGatewayException
{
    protected int $statusCode = 401;

    public function __construct(string $gateway)
    {
        parent::__construct("Invalid webhook signature from {$gateway}");
    }

    protected function getErrorCode(): string
    {
        return 'invalid_webhook_signature';
    }
}

/**
 * Tipo de gateway não suportado
 */
class UnsupportedGatewayException extends PaymentGatewayException
{
    protected int $statusCode = 400;

    public function __construct(string $type)
    {
        parent::__construct("Payment gateway type '{$type}' is not supported");
    }

    protected function getErrorCode(): string
    {
        return 'unsupported_gateway';
    }
}
```

**Tarefas:**
- [ ] Criar `app/Exceptions/PaymentGatewayException.php`
- [ ] Criar `app/Exceptions/GatewayNotFoundException.php`
- [ ] Criar `app/Exceptions/GatewayConnectionException.php`
- [ ] Criar `app/Exceptions/GatewayAuthenticationException.php`
- [ ] Criar `app/Exceptions/InvalidGatewayResponseException.php`
- [ ] Criar `app/Exceptions/InvalidWebhookSignatureException.php`
- [ ] Criar `app/Exceptions/UnsupportedGatewayException.php`
- [ ] Registrar exceptions no `app/Exceptions/Handler.php`:
```php
$this->renderable(function (PaymentGatewayException $e) {
    return $e->render();
});
```

---

### 3. DTOs (app/DTOs/PaymentGateway/)

#### 3.1 CreateGatewayChargeDTO
```php
<?php

declare(strict_types=1);

namespace App\DTOs\PaymentGateway;

use App\DTOs\Customer\CustomerDTO;
use App\Enums\PaymentMethod;

/**
 * DTO para criar cobrança no gateway externo
 */
readonly class CreateGatewayChargeDTO
{
    public function __construct(
        public CustomerDTO $customer,
        public float $amount,
        public string $description,
        public PaymentMethod $paymentMethod,
        public string $dueDate, // Y-m-d
        public ?string $internalChargeId = null,
    ) {}

    public function toArray(): array
    {
        return [
            'customer' => $this->customer->toArray(),
            'amount' => $this->amount,
            'description' => $this->description,
            'payment_method' => $this->paymentMethod->value,
            'due_date' => $this->dueDate,
            'internal_charge_id' => $this->internalChargeId,
        ];
    }

    /**
     * Criar a partir de Charge model
     */
    public static function fromCharge(\App\Models\Charge $charge): self
    {
        return new self(
            customer: CustomerDTO::fromModel($charge->customer),
            amount: $charge->amount,
            description: $charge->description,
            paymentMethod: $charge->payment_method,
            dueDate: $charge->due_date->format('Y-m-d'),
            internalChargeId: (string) $charge->id,
        );
    }
}
```

#### 3.2 GatewayChargeResponseDTO
```php
<?php

declare(strict_types=1);

namespace App\DTOs\PaymentGateway;

use App\Enums\GatewayChargeStatus;

/**
 * DTO para resposta do gateway após criar cobrança
 */
readonly class GatewayChargeResponseDTO
{
    public function __construct(
        public string $gatewayChargeId,
        public GatewayChargeStatus $status,
        public string $paymentUrl,
        public ?string $barcode = null,
        public ?string $qrCode = null,
        public ?array $metadata = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            gatewayChargeId: $data['gateway_charge_id'],
            status: GatewayChargeStatus::from($data['status']),
            paymentUrl: $data['payment_url'],
            barcode: $data['barcode'] ?? null,
            qrCode: $data['qr_code'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'gateway_charge_id' => $this->gatewayChargeId,
            'status' => $this->status->value,
            'payment_url' => $this->paymentUrl,
            'barcode' => $this->barcode,
            'qr_code' => $this->qrCode,
            'metadata' => $this->metadata,
        ];
    }
}
```

#### 3.3 WebhookPayloadDTO
```php
<?php

declare(strict_types=1);

namespace App\DTOs\PaymentGateway;

use App\Enums\GatewayChargeStatus;

/**
 * DTO para payload processado de webhook
 */
readonly class WebhookPayloadDTO
{
    public function __construct(
        public string $gatewayChargeId,
        public GatewayChargeStatus $status,
        public ?string $paidAt = null,
        public ?string $paymentMethod = null,
        public ?array $metadata = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            gatewayChargeId: $data['gateway_charge_id'],
            status: GatewayChargeStatus::from($data['status']),
            paidAt: $data['paid_at'] ?? null,
            paymentMethod: $data['payment_method'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'gateway_charge_id' => $this->gatewayChargeId,
            'status' => $this->status->value,
            'paid_at' => $this->paidAt,
            'payment_method' => $this->paymentMethod,
            'metadata' => $this->metadata,
        ];
    }
}
```

**Tarefas:**
- [ ] Criar `app/DTOs/PaymentGateway/CreateGatewayChargeDTO.php`
- [ ] Criar `app/DTOs/PaymentGateway/GatewayChargeResponseDTO.php`
- [ ] Criar `app/DTOs/PaymentGateway/WebhookPayloadDTO.php`
- [ ] Criar `app/DTOs/Customer/CustomerDTO.php` (se ainda não existe)

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
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->unique(); // pagseguro, asaas, stone
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->text('credentials')->nullable(); // Criptografado
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
```

#### 4.2 Model
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentGatewayType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'is_active',
        'credentials',
        'settings',
    ];

    protected $casts = [
        'type' => PaymentGatewayType::class,
        'is_active' => 'boolean',
        'credentials' => 'encrypted:array', // Criptografado
        'settings' => 'array',
    ];

    /**
     * Relacionamento: Gateway tem muitas cobranças
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    /**
     * Scope: Apenas gateways ativos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Gateway por tipo
     */
    public function scopeByType($query, PaymentGatewayType $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Verifica se gateway está ativo
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Obtém configuração específica
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }
}
```

**Tarefas:**
- [ ] Criar migration `create_payment_gateways_table`
- [ ] Criar `app/Models/PaymentGateway.php`
- [ ] Adicionar coluna `payment_gateway_id` na migration de `charges` (se ainda não existe)
- [ ] Adicionar relacionamento `belongsTo(PaymentGateway::class)` no modelo `Charge`
- [ ] Rodar migration: `./vendor/bin/sail artisan migrate`

---

### 5. Strategy Pattern - Interface e Implementações

#### 5.1 PaymentGatewayInterface (Strategy)
```php
<?php

declare(strict_types=1);

namespace App\Services\PaymentGateway\Contracts;

use App\DTOs\PaymentGateway\CreateGatewayChargeDTO;
use App\DTOs\PaymentGateway\GatewayChargeResponseDTO;
use App\DTOs\PaymentGateway\WebhookPayloadDTO;

/**
 * Interface comum para todos os gateways (Strategy Pattern)
 */
interface PaymentGatewayInterface
{
    /**
     * Criar cobrança no gateway externo
     *
     * @throws \App\Exceptions\GatewayConnectionException
     * @throws \App\Exceptions\GatewayAuthenticationException
     * @throws \App\Exceptions\InvalidGatewayResponseException
     */
    public function createCharge(CreateGatewayChargeDTO $dto): GatewayChargeResponseDTO;

    /**
     * Buscar status da cobrança no gateway
     *
     * @throws \App\Exceptions\GatewayConnectionException
     * @throws \App\Exceptions\InvalidGatewayResponseException
     */
    public function getChargeStatus(string $gatewayChargeId): GatewayChargeResponseDTO;

    /**
     * Cancelar cobrança no gateway
     *
     * @throws \App\Exceptions\GatewayConnectionException
     * @throws \App\Exceptions\InvalidGatewayResponseException
     */
    public function cancelCharge(string $gatewayChargeId): bool;

    /**
     * Processar payload de webhook e normalizar dados
     *
     * @throws \App\Exceptions\InvalidGatewayResponseException
     */
    public function processWebhook(array $payload): WebhookPayloadDTO;

    /**
     * Validar assinatura do webhook
     *
     * @throws \App\Exceptions\InvalidWebhookSignatureException
     */
    public function validateWebhookSignature(string $signature, array $payload): bool;

    /**
     * Verificar conectividade com gateway (health check)
     */
    public function healthCheck(): bool;
}
```

**Tarefas:**
- [ ] Criar `app/Services/PaymentGateway/Contracts/PaymentGatewayInterface.php`

---

#### 5.2 Implementação - PagSeguroGateway
```php
<?php

declare(strict_types=1);

namespace App\Services\PaymentGateway\Implementations;

use App\DTOs\PaymentGateway\CreateGatewayChargeDTO;
use App\DTOs\PaymentGateway\GatewayChargeResponseDTO;
use App\DTOs\PaymentGateway\WebhookPayloadDTO;
use App\Enums\GatewayChargeStatus;
use App\Enums\PaymentGatewayType;
use App\Exceptions\GatewayAuthenticationException;
use App\Exceptions\GatewayConnectionException;
use App\Exceptions\InvalidGatewayResponseException;
use App\Exceptions\InvalidWebhookSignatureException;
use App\Services\PaymentGateway\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PagSeguroGateway implements PaymentGatewayInterface
{
    private string $apiUrl;
    private string $apiKey;
    private string $apiToken;

    public function __construct()
    {
        $this->apiUrl = config('services.pagseguro.api_url');
        $this->apiKey = config('services.pagseguro.api_key');
        $this->apiToken = config('services.pagseguro.api_token');
    }

    public function createCharge(CreateGatewayChargeDTO $dto): GatewayChargeResponseDTO
    {
        try {
            $response = Http::timeout(30)
                ->retry(3, 1000)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiToken}",
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->apiUrl}/charges", [
                    'reference_id' => $dto->internalChargeId,
                    'customer' => [
                        'name' => $dto->customer->name,
                        'email' => $dto->customer->email,
                        'tax_id' => $dto->customer->document,
                    ],
                    'amount' => [
                        'value' => (int) ($dto->amount * 100), // Centavos
                        'currency' => 'BRL',
                    ],
                    'description' => $dto->description,
                    'payment_method' => $this->mapPaymentMethod($dto->paymentMethod->value),
                    'due_date' => $dto->dueDate,
                ]);

            if ($response->unauthorized()) {
                throw new GatewayAuthenticationException(PaymentGatewayType::PAGSEGURO->value);
            }

            if ($response->failed()) {
                throw new InvalidGatewayResponseException(
                    PaymentGatewayType::PAGSEGURO->value,
                    $response->body()
                );
            }

            $data = $response->json();

            return new GatewayChargeResponseDTO(
                gatewayChargeId: $data['id'],
                status: $this->mapStatus($data['status']),
                paymentUrl: $data['links'][0]['href'] ?? '',
                barcode: $data['barcode'] ?? null,
                qrCode: $data['qr_code'] ?? null,
                metadata: $data,
            );

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('PagSeguro connection failed', [
                'error' => $e->getMessage(),
                'dto' => $dto->toArray(),
            ]);

            throw new GatewayConnectionException(
                PaymentGatewayType::PAGSEGURO->value,
                $e->getMessage()
            );
        }
    }

    public function getChargeStatus(string $gatewayChargeId): GatewayChargeResponseDTO
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiToken}",
                ])
                ->get("{$this->apiUrl}/charges/{$gatewayChargeId}");

            if ($response->failed()) {
                throw new InvalidGatewayResponseException(
                    PaymentGatewayType::PAGSEGURO->value,
                    $response->body()
                );
            }

            $data = $response->json();

            return new GatewayChargeResponseDTO(
                gatewayChargeId: $data['id'],
                status: $this->mapStatus($data['status']),
                paymentUrl: $data['links'][0]['href'] ?? '',
                barcode: $data['barcode'] ?? null,
                qrCode: $data['qr_code'] ?? null,
                metadata: $data,
            );

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new GatewayConnectionException(
                PaymentGatewayType::PAGSEGURO->value,
                $e->getMessage()
            );
        }
    }

    public function cancelCharge(string $gatewayChargeId): bool
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiToken}",
                ])
                ->post("{$this->apiUrl}/charges/{$gatewayChargeId}/cancel");

            return $response->successful();

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new GatewayConnectionException(
                PaymentGatewayType::PAGSEGURO->value,
                $e->getMessage()
            );
        }
    }

    public function processWebhook(array $payload): WebhookPayloadDTO
    {
        if (!isset($payload['id'], $payload['status'])) {
            throw new InvalidGatewayResponseException(
                PaymentGatewayType::PAGSEGURO->value,
                'Missing required fields: id, status'
            );
        }

        return new WebhookPayloadDTO(
            gatewayChargeId: $payload['id'],
            status: $this->mapStatus($payload['status']),
            paidAt: $payload['paid_at'] ?? null,
            paymentMethod: $payload['payment_method']['type'] ?? null,
            metadata: $payload,
        );
    }

    public function validateWebhookSignature(string $signature, array $payload): bool
    {
        $expectedSignature = hash_hmac(
            'sha256',
            json_encode($payload),
            $this->apiToken
        );

        if (!hash_equals($expectedSignature, $signature)) {
            throw new InvalidWebhookSignatureException(PaymentGatewayType::PAGSEGURO->value);
        }

        return true;
    }

    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiToken}",
                ])
                ->get("{$this->apiUrl}/health");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Mapeia status do PagSeguro para status interno
     */
    private function mapStatus(string $status): GatewayChargeStatus
    {
        return match (strtolower($status)) {
            'paid', 'approved' => GatewayChargeStatus::PAID,
            'pending', 'waiting_payment' => GatewayChargeStatus::PENDING,
            'cancelled', 'canceled' => GatewayChargeStatus::CANCELLED,
            'refunded' => GatewayChargeStatus::REFUNDED,
            'processing' => GatewayChargeStatus::PROCESSING,
            'failed', 'declined' => GatewayChargeStatus::FAILED,
            'expired' => GatewayChargeStatus::EXPIRED,
            default => GatewayChargeStatus::PENDING,
        };
    }

    /**
     * Mapeia método de pagamento para formato do PagSeguro
     */
    private function mapPaymentMethod(string $method): string
    {
        return match ($method) {
            'credit_card' => 'CREDIT_CARD',
            'bank_slip' => 'BOLETO',
            'pix' => 'PIX',
            default => 'BOLETO',
        };
    }
}
```

**Tarefas:**
- [ ] Criar `app/Services/PaymentGateway/Implementations/PagSeguroGateway.php`
- [ ] Criar `app/Services/PaymentGateway/Implementations/AsaasGateway.php` (similar ao PagSeguro)
- [ ] Criar `app/Services/PaymentGateway/Implementations/StoneGateway.php` (similar ao PagSeguro)

---

### 6. Factory Pattern

```php
<?php

declare(strict_types=1);

namespace App\Services\PaymentGateway;

use App\Enums\PaymentGatewayType;
use App\Exceptions\UnsupportedGatewayException;
use App\Services\PaymentGateway\Contracts\PaymentGatewayInterface;
use App\Services\PaymentGateway\Implementations\AsaasGateway;
use App\Services\PaymentGateway\Implementations\PagSeguroGateway;
use App\Services\PaymentGateway\Implementations\StoneGateway;

/**
 * Factory para criar instâncias de gateways (Factory Pattern)
 */
class PaymentGatewayFactory
{
    /**
     * Criar instância do gateway baseado no tipo
     *
     * @throws UnsupportedGatewayException
     */
    public static function create(PaymentGatewayType $type): PaymentGatewayInterface
    {
        return match ($type) {
            PaymentGatewayType::PAGSEGURO => app(PagSeguroGateway::class),
            PaymentGatewayType::ASAAS => app(AsaasGateway::class),
            PaymentGatewayType::STONE => app(StoneGateway::class),
        };
    }

    /**
     * Criar instância do gateway padrão (configurável via .env)
     */
    public static function createDefault(): PaymentGatewayInterface
    {
        $defaultType = config('services.payment_gateway.default', 'pagseguro');

        try {
            $type = PaymentGatewayType::from($defaultType);
            return self::create($type);
        } catch (\ValueError $e) {
            throw new UnsupportedGatewayException($defaultType);
        }
    }

    /**
     * Criar gateway a partir de string (útil para webhooks)
     */
    public static function createFromString(string $type): PaymentGatewayInterface
    {
        try {
            $gatewayType = PaymentGatewayType::from($type);
            return self::create($gatewayType);
        } catch (\ValueError $e) {
            throw new UnsupportedGatewayException($type);
        }
    }
}
```

**Tarefas:**
- [ ] Criar `app/Services/PaymentGateway/PaymentGatewayFactory.php`

---

### 7. Actions (Write Operations)

#### 7.1 CreateChargeOnGatewayAction
```php
<?php

declare(strict_types=1);

namespace App\Actions\PaymentGateway;

use App\DTOs\PaymentGateway\CreateGatewayChargeDTO;
use App\DTOs\PaymentGateway\GatewayChargeResponseDTO;
use App\Enums\PaymentGatewayType;
use App\Exceptions\GatewayNotFoundException;
use App\Models\Charge;
use App\Models\PaymentGateway;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Action: Criar cobrança em gateway externo
 *
 * Retorna: Charge model atualizado
 * Lança: GatewayNotFoundException, GatewayConnectionException, etc.
 */
class CreateChargeOnGatewayAction
{
    /**
     * Criar cobrança no gateway externo e atualizar Charge local
     *
     * @throws GatewayNotFoundException
     * @throws \App\Exceptions\GatewayConnectionException
     * @throws \App\Exceptions\InvalidGatewayResponseException
     */
    public function execute(Charge $charge, PaymentGatewayType $gatewayType): Charge
    {
        // Verificar se gateway existe e está ativo
        $paymentGateway = PaymentGateway::active()
            ->byType($gatewayType)
            ->first();

        if (!$paymentGateway) {
            throw new GatewayNotFoundException($gatewayType);
        }

        // Criar instância do gateway via Factory
        $gateway = PaymentGatewayFactory::create($gatewayType);

        // Preparar DTO
        $dto = CreateGatewayChargeDTO::fromCharge($charge);

        // Criar cobrança no gateway externo
        $response = $gateway->createCharge($dto);

        // Atualizar Charge com dados do gateway (transação)
        return DB::transaction(function () use ($charge, $response, $paymentGateway) {
            $charge->update([
                'gateway_charge_id' => $response->gatewayChargeId,
                'payment_gateway_id' => $paymentGateway->id,
                'payment_url' => $response->paymentUrl,
                'barcode' => $response->barcode,
                'qr_code' => $response->qrCode,
                'gateway_metadata' => $response->metadata,
            ]);

            Log::info('Charge created on gateway', [
                'charge_id' => $charge->id,
                'gateway' => $paymentGateway->type->value,
                'gateway_charge_id' => $response->gatewayChargeId,
            ]);

            return $charge->fresh();
        });
    }
}
```

#### 7.2 SyncChargeStatusFromGatewayAction
```php
<?php

declare(strict_types=1);

namespace App\Actions\PaymentGateway;

use App\Exceptions\InvalidGatewayResponseException;
use App\Models\Charge;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use Illuminate\Support\Facades\Log;

/**
 * Action: Sincronizar status da cobrança com o gateway
 *
 * Retorna: Charge model atualizado
 */
class SyncChargeStatusFromGatewayAction
{
    /**
     * Buscar status atualizado no gateway e sincronizar localmente
     *
     * @throws InvalidGatewayResponseException
     */
    public function execute(Charge $charge): Charge
    {
        if (!$charge->gateway_charge_id || !$charge->paymentGateway) {
            throw new InvalidGatewayResponseException(
                'unknown',
                'Charge has no gateway information'
            );
        }

        // Criar instância do gateway
        $gateway = PaymentGatewayFactory::create($charge->paymentGateway->type);

        // Buscar status no gateway
        $response = $gateway->getChargeStatus($charge->gateway_charge_id);

        // Atualizar status local
        $newStatus = $response->status->toChargeStatus();

        if ($charge->status !== $newStatus) {
            $charge->update([
                'status' => $newStatus,
                'gateway_metadata' => $response->metadata,
            ]);

            Log::info('Charge status synced from gateway', [
                'charge_id' => $charge->id,
                'old_status' => $charge->getOriginal('status'),
                'new_status' => $newStatus->value,
            ]);
        }

        return $charge->fresh();
    }
}
```

#### 7.3 CancelChargeOnGatewayAction
```php
<?php

declare(strict_types=1);

namespace App\Actions\PaymentGateway;

use App\Models\Charge;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use Illuminate\Support\Facades\Log;

/**
 * Action: Cancelar cobrança no gateway externo
 *
 * Retorna: bool (sucesso)
 */
class CancelChargeOnGatewayAction
{
    /**
     * Cancelar cobrança no gateway externo
     *
     * @throws \App\Exceptions\GatewayConnectionException
     */
    public function execute(Charge $charge): bool
    {
        if (!$charge->gateway_charge_id || !$charge->paymentGateway) {
            Log::warning('Attempted to cancel charge without gateway info', [
                'charge_id' => $charge->id,
            ]);
            return false;
        }

        // Criar instância do gateway
        $gateway = PaymentGatewayFactory::create($charge->paymentGateway->type);

        // Cancelar no gateway
        $success = $gateway->cancelCharge($charge->gateway_charge_id);

        if ($success) {
            Log::info('Charge cancelled on gateway', [
                'charge_id' => $charge->id,
                'gateway' => $charge->paymentGateway->type->value,
            ]);
        }

        return $success;
    }
}
```

**Tarefas:**
- [ ] Criar `app/Actions/PaymentGateway/CreateChargeOnGatewayAction.php`
- [ ] Criar `app/Actions/PaymentGateway/SyncChargeStatusFromGatewayAction.php`
- [ ] Criar `app/Actions/PaymentGateway/CancelChargeOnGatewayAction.php`

---

### 8. Queries (Read Operations)

```php
<?php

declare(strict_types=1);

namespace App\Queries\PaymentGateway;

use App\Enums\PaymentGatewayType;
use App\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Collection;

/**
 * Query: Buscar gateways ativos
 */
class GetActiveGatewaysQuery
{
    /**
     * Buscar todos os gateways ativos
     */
    public function execute(): Collection
    {
        return PaymentGateway::active()
            ->orderBy('name')
            ->get();
    }
}

/**
 * Query: Buscar gateway por tipo
 */
class GetGatewayByTypeQuery
{
    /**
     * Buscar gateway específico por tipo
     */
    public function execute(PaymentGatewayType $type): ?PaymentGateway
    {
        return PaymentGateway::active()
            ->byType($type)
            ->first();
    }
}

/**
 * Query: Buscar gateway padrão
 */
class GetDefaultGatewayQuery
{
    /**
     * Buscar gateway configurado como padrão
     */
    public function execute(): ?PaymentGateway
    {
        $defaultType = PaymentGatewayType::from(
            config('services.payment_gateway.default', 'pagseguro')
        );

        return PaymentGateway::active()
            ->byType($defaultType)
            ->first();
    }
}
```

**Tarefas:**
- [ ] Criar `app/Queries/PaymentGateway/GetActiveGatewaysQuery.php`
- [ ] Criar `app/Queries/PaymentGateway/GetGatewayByTypeQuery.php`
- [ ] Criar `app/Queries/PaymentGateway/GetDefaultGatewayQuery.php`

---

### 9. Configurações

#### 9.1 config/services.php
```php
return [
    // ... outras configurações

    'payment_gateway' => [
        'default' => env('PAYMENT_GATEWAY_DEFAULT', 'pagseguro'),
    ],

    'pagseguro' => [
        'api_url' => env('PAGSEGURO_API_URL', 'https://sandbox.api.pagseguro.com'),
        'api_key' => env('PAGSEGURO_API_KEY'),
        'api_token' => env('PAGSEGURO_API_TOKEN'),
    ],

    'asaas' => [
        'api_url' => env('ASAAS_API_URL', 'https://sandbox.asaas.com/api/v3'),
        'api_key' => env('ASAAS_API_KEY'),
    ],

    'stone' => [
        'api_url' => env('STONE_API_URL', 'https://sandbox.stone.com.br/api'),
        'api_key' => env('STONE_API_KEY'),
        'api_secret' => env('STONE_API_SECRET'),
    ],
];
```

#### 9.2 .env.example
```env
# Payment Gateway Configuration
PAYMENT_GATEWAY_DEFAULT=pagseguro

# PagSeguro
PAGSEGURO_API_URL=https://sandbox.api.pagseguro.com
PAGSEGURO_API_KEY=your-api-key-here
PAGSEGURO_API_TOKEN=your-api-token-here

# Asaas
ASAAS_API_URL=https://sandbox.asaas.com/api/v3
ASAAS_API_KEY=your-api-key-here

# Stone
STONE_API_URL=https://sandbox.stone.com.br/api
STONE_API_KEY=your-api-key-here
STONE_API_SECRET=your-api-secret-here
```

**Tarefas:**
- [ ] Adicionar configurações em `config/services.php`
- [ ] Atualizar `.env.example`

---

### 10. Seeders

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PaymentGatewayType;
use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $gateways = [
            [
                'type' => PaymentGatewayType::PAGSEGURO,
                'name' => 'PagSeguro',
                'is_active' => true,
                'settings' => [
                    'timeout' => 30,
                    'retry_attempts' => 3,
                    'retry_delay_ms' => 1000,
                ],
            ],
            [
                'type' => PaymentGatewayType::ASAAS,
                'name' => 'Asaas',
                'is_active' => true,
                'settings' => [
                    'timeout' => 30,
                    'retry_attempts' => 3,
                    'retry_delay_ms' => 1000,
                ],
            ],
            [
                'type' => PaymentGatewayType::STONE,
                'name' => 'Stone',
                'is_active' => false, // Desabilitado por padrão
                'settings' => [
                    'timeout' => 30,
                    'retry_attempts' => 3,
                    'retry_delay_ms' => 1000,
                ],
            ],
        ];

        foreach ($gateways as $gateway) {
            PaymentGateway::updateOrCreate(
                ['type' => $gateway['type']],
                $gateway
            );
        }

        $this->command->info('Payment gateways seeded successfully!');
    }
}
```

**Tarefas:**
- [ ] Criar `database/seeders/PaymentGatewaySeeder.php`
- [ ] Adicionar ao `DatabaseSeeder.php`: `$this->call(PaymentGatewaySeeder::class);`
- [ ] Rodar seeder: `./vendor/bin/sail artisan db:seed --class=PaymentGatewaySeeder`

---

### 11. Testes

#### 11.1 Unit Test - PagSeguroGateway
```php
<?php

namespace Tests\Unit\Services\PaymentGateway;

use App\DTOs\Customer\CustomerDTO;
use App\DTOs\PaymentGateway\CreateGatewayChargeDTO;
use App\Enums\PaymentMethod;
use App\Exceptions\GatewayAuthenticationException;
use App\Exceptions\GatewayConnectionException;
use App\Services\PaymentGateway\Implementations\PagSeguroGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PagSeguroGatewayTest extends TestCase
{
    private PagSeguroGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new PagSeguroGateway();
    }

    public function test_create_charge_successfully(): void
    {
        Http::fake([
            '*/charges' => Http::response([
                'id' => 'CHARGE_123',
                'status' => 'pending',
                'links' => [['href' => 'https://payment.url']],
                'barcode' => '12345678901234567890',
            ], 200),
        ]);

        $customer = new CustomerDTO(
            name: 'John Doe',
            email: 'john@example.com',
            document: '12345678900',
        );

        $dto = new CreateGatewayChargeDTO(
            customer: $customer,
            amount: 100.00,
            description: 'Test charge',
            paymentMethod: PaymentMethod::BANK_SLIP,
            dueDate: '2025-12-31',
        );

        $response = $this->gateway->createCharge($dto);

        $this->assertEquals('CHARGE_123', $response->gatewayChargeId);
        $this->assertEquals('https://payment.url', $response->paymentUrl);
        $this->assertEquals('12345678901234567890', $response->barcode);
    }

    public function test_create_charge_throws_authentication_exception(): void
    {
        Http::fake([
            '*/charges' => Http::response([], 401),
        ]);

        $customer = new CustomerDTO(
            name: 'John Doe',
            email: 'john@example.com',
            document: '12345678900',
        );

        $dto = new CreateGatewayChargeDTO(
            customer: $customer,
            amount: 100.00,
            description: 'Test charge',
            paymentMethod: PaymentMethod::BANK_SLIP,
            dueDate: '2025-12-31',
        );

        $this->expectException(GatewayAuthenticationException::class);
        $this->gateway->createCharge($dto);
    }

    public function test_health_check_returns_true_when_gateway_is_up(): void
    {
        Http::fake([
            '*/health' => Http::response([], 200),
        ]);

        $this->assertTrue($this->gateway->healthCheck());
    }

    public function test_health_check_returns_false_when_gateway_is_down(): void
    {
        Http::fake([
            '*/health' => Http::response([], 500),
        ]);

        $this->assertFalse($this->gateway->healthCheck());
    }
}
```

#### 11.2 Unit Test - PaymentGatewayFactory
```php
<?php

namespace Tests\Unit\Services\PaymentGateway;

use App\Enums\PaymentGatewayType;
use App\Exceptions\UnsupportedGatewayException;
use App\Services\PaymentGateway\Implementations\AsaasGateway;
use App\Services\PaymentGateway\Implementations\PagSeguroGateway;
use App\Services\PaymentGateway\Implementations\StoneGateway;
use App\Services\PaymentGateway\PaymentGatewayFactory;
use Tests\TestCase;

class PaymentGatewayFactoryTest extends TestCase
{
    public function test_create_pagseguro_gateway(): void
    {
        $gateway = PaymentGatewayFactory::create(PaymentGatewayType::PAGSEGURO);
        $this->assertInstanceOf(PagSeguroGateway::class, $gateway);
    }

    public function test_create_asaas_gateway(): void
    {
        $gateway = PaymentGatewayFactory::create(PaymentGatewayType::ASAAS);
        $this->assertInstanceOf(AsaasGateway::class, $gateway);
    }

    public function test_create_stone_gateway(): void
    {
        $gateway = PaymentGatewayFactory::create(PaymentGatewayType::STONE);
        $this->assertInstanceOf(StoneGateway::class, $gateway);
    }

    public function test_create_default_gateway(): void
    {
        config(['services.payment_gateway.default' => 'pagseguro']);
        $gateway = PaymentGatewayFactory::createDefault();
        $this->assertInstanceOf(PagSeguroGateway::class, $gateway);
    }

    public function test_create_from_string_throws_exception_for_invalid_type(): void
    {
        $this->expectException(UnsupportedGatewayException::class);
        PaymentGatewayFactory::createFromString('invalid_gateway');
    }
}
```

#### 11.3 Feature Test - CreateChargeOnGatewayAction
```php
<?php

namespace Tests\Feature\Actions\PaymentGateway;

use App\Actions\PaymentGateway\CreateChargeOnGatewayAction;
use App\Enums\ChargeStatus;
use App\Enums\PaymentGatewayType;
use App\Enums\PaymentMethod;
use App\Exceptions\GatewayNotFoundException;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CreateChargeOnGatewayActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_charge_on_gateway_successfully(): void
    {
        Http::fake([
            '*/charges' => Http::response([
                'id' => 'GATEWAY_123',
                'status' => 'pending',
                'links' => [['href' => 'https://payment.url']],
                'barcode' => '12345',
            ], 200),
        ]);

        $gateway = PaymentGateway::factory()->create([
            'type' => PaymentGatewayType::PAGSEGURO,
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create();
        $charge = Charge::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 100.00,
            'status' => ChargeStatus::PENDING,
        ]);

        $action = new CreateChargeOnGatewayAction();
        $result = $action->execute($charge, PaymentGatewayType::PAGSEGURO);

        $this->assertEquals('GATEWAY_123', $result->gateway_charge_id);
        $this->assertEquals($gateway->id, $result->payment_gateway_id);
        $this->assertEquals('https://payment.url', $result->payment_url);
    }

    public function test_throws_exception_when_gateway_not_found(): void
    {
        $customer = Customer::factory()->create();
        $charge = Charge::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $action = new CreateChargeOnGatewayAction();

        $this->expectException(GatewayNotFoundException::class);
        $action->execute($charge, PaymentGatewayType::PAGSEGURO);
    }
}
```

**Tarefas:**
- [ ] Criar `tests/Unit/Services/PaymentGateway/PagSeguroGatewayTest.php`
- [ ] Criar `tests/Unit/Services/PaymentGateway/AsaasGatewayTest.php`
- [ ] Criar `tests/Unit/Services/PaymentGateway/StoneGatewayTest.php`
- [ ] Criar `tests/Unit/Services/PaymentGateway/PaymentGatewayFactoryTest.php`
- [ ] Criar `tests/Feature/Actions/PaymentGateway/CreateChargeOnGatewayActionTest.php`
- [ ] Criar `tests/Feature/Actions/PaymentGateway/SyncChargeStatusFromGatewayActionTest.php`
- [ ] Criar `tests/Feature/Actions/PaymentGateway/CancelChargeOnGatewayActionTest.php`
- [ ] Rodar testes: `./vendor/bin/sail artisan test`

---

### 12. Factory para Testes

```php
<?php

namespace Database\Factories;

use App\Enums\PaymentGatewayType;
use App\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentGatewayFactory extends Factory
{
    protected $model = PaymentGateway::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(PaymentGatewayType::cases()),
            'name' => fake()->company(),
            'is_active' => true,
            'credentials' => [
                'api_key' => fake()->uuid(),
                'api_token' => fake()->sha256(),
            ],
            'settings' => [
                'timeout' => 30,
                'retry_attempts' => 3,
            ],
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function pagseguro(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => PaymentGatewayType::PAGSEGURO,
            'name' => 'PagSeguro',
        ]);
    }

    public function asaas(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => PaymentGatewayType::ASAAS,
            'name' => 'Asaas',
        ]);
    }
}
```

**Tarefas:**
- [ ] Criar `database/factories/PaymentGatewayFactory.php`

---

## Checklist de Qualidade

### Arquitetura
- [ ] **Strategy Pattern** implementado corretamente (PaymentGatewayInterface)
- [ ] **Factory Pattern** implementado (PaymentGatewayFactory)
- [ ] Interface comum para todos os gateways
- [ ] Facilidade de adicionar novos gateways (Open/Closed Principle)
- [ ] Gateways intercambiáveis (Liskov Substitution Principle)
- [ ] **Actions** retornam Models (não JsonResponse)
- [ ] **Queries** usam Eloquent diretamente
- [ ] **Exceptions** controlam status codes via render()

### Código
- [ ] Type hints completos (PHP 8.2+)
- [ ] Readonly DTOs
- [ ] Enum para tipos de gateway
- [ ] Exception handling robusto
- [ ] Logging de todas as operações
- [ ] Retry logic implementado (Http::retry)
- [ ] Timeout configurável

### Segurança
- [ ] Credenciais criptografadas no banco (`encrypted:array`)
- [ ] Credenciais em variáveis de ambiente
- [ ] Validação de assinatura de webhooks (hash_hmac)
- [ ] Nunca logar credenciais
- [ ] hash_equals() para comparar assinaturas (previne timing attacks)

### Performance
- [ ] HTTP Client com timeout (30s)
- [ ] Retry logic com delay configurável
- [ ] Logs estruturados (contexto completo)

### Testes
- [ ] Unit tests para cada gateway (mock HTTP)
- [ ] Unit tests para Factory
- [ ] Feature tests para Actions
- [ ] Testar casos de sucesso e falha
- [ ] Cobertura > 80%

---

## Critérios de Aceitação

✅ **Arquitetura**
- Strategy Pattern implementado (PaymentGatewayInterface + Implementations)
- Factory Pattern implementado (PaymentGatewayFactory)
- Fácil adicionar novos gateways (1 arquivo + 1 enum case)
- Interface comum funcionando

✅ **Funcionalidade**
- Criar cobrança nos gateways (PagSeguro, Asaas, Stone)
- Buscar status nos gateways
- Cancelar cobrança nos gateways
- Processar webhooks (normalização de dados)
- Validar assinaturas de webhooks

✅ **Segurança**
- Credenciais protegidas (encrypted no DB, env no código)
- Webhooks validados (assinatura obrigatória)
- Logs sem dados sensíveis

✅ **Qualidade**
- Todos os testes passando (`./vendor/bin/sail artisan test`)
- PHPStan sem erros (`./vendor/bin/phpstan analyse`)
- Exception handling robusto
- Logging adequado

---

## Comandos Úteis

```bash
# Criar migration
./vendor/bin/sail artisan make:migration create_payment_gateways_table

# Rodar migrations
./vendor/bin/sail artisan migrate

# Rodar seeders
./vendor/bin/sail artisan db:seed --class=PaymentGatewaySeeder

# Rodar testes
./vendor/bin/sail artisan test
./vendor/bin/sail artisan test --filter=PaymentGateway

# Code style
./vendor/bin/pint

# Static analysis
./vendor/bin/phpstan analyse

# Criar factory
./vendor/bin/sail artisan make:factory PaymentGatewayFactory --model=PaymentGateway
```

---

## Notas Importantes

### ⚠️ Repository Pattern - APENAS AQUI

Este é o **único domínio** onde Repository faz sentido:
- ❌ Customer: não precisa (só Eloquent)
- ❌ Charge: não precisa (só Eloquent)
- ✅ **PaymentGateway**: precisa (múltiplas implementações externas)

**Diferença:**
```php
// Customer/Charge - Eloquent direto (Query)
class GetActiveCustomersQuery {
    public function execute() {
        return Customer::where('status', 'active')->get();
    }
}

// PaymentGateway - Strategy Pattern (múltiplas APIs externas)
interface PaymentGatewayInterface {
    public function createCharge(...);
}
```

### ⚠️ Segurança

- **NUNCA** commitar credenciais reais
- Sempre validar assinatura de webhooks antes de processar
- Usar `hash_equals()` para comparar assinaturas (previne timing attacks)
- Criptografar credenciais no banco (`encrypted:array`)
- Usar sandbox em desenvolvimento

### ⚠️ Adicionar Novo Gateway

Para adicionar um novo gateway (ex: Stripe):

1. **Enum**: Adicionar caso em `PaymentGatewayType`
```php
case STRIPE = 'stripe';
```

2. **Implementação**: Criar `StripeGateway implements PaymentGatewayInterface`
```php
class StripeGateway implements PaymentGatewayInterface { ... }
```

3. **Factory**: Adicionar match case
```php
PaymentGatewayType::STRIPE => app(StripeGateway::class),
```

4. **Config**: Adicionar em `config/services.php`
```php
'stripe' => ['api_key' => env('STRIPE_API_KEY')],
```

5. **Env**: Adicionar em `.env.example`
```env
STRIPE_API_KEY=
```

6. **Seeder**: Adicionar gateway no seeder

7. **Testes**: Criar `StripeGatewayTest.php`

8. **Documentação**: Atualizar README

### ⚠️ Diferença: Actions vs Services

**Neste projeto:**
- ✅ **Actions**: Write operations (criar, atualizar, deletar)
  - Retornam Models
  - Lançam Custom Exceptions
  - Reutilizáveis (Controllers, Jobs, Commands)

- ❌ **Services**: REMOVIDOS (substituídos por Actions + Factory)
  - Antigamente: `PaymentGatewayService->createCharge()`
  - Agora: `CreateChargeOnGatewayAction->execute()`

**Por quê?**
- Actions são mais específicas e testáveis
- Factory cria gateways (Strategy Pattern)
- Sem camada intermediária desnecessária

---

## Exemplo de Uso Completo

```php
// Controller (thin, apenas orquestra)
class ChargeController extends Controller
{
    public function sendToGateway(
        Charge $charge,
        CreateChargeOnGatewayAction $action
    ): JsonResponse {
        try {
            // Action retorna Model
            $charge = $action->execute($charge, PaymentGatewayType::PAGSEGURO);

            // Controller define status de sucesso
            return response()->json([
                'data' => new ChargeResource($charge),
                'message' => 'Charge sent to gateway successfully',
            ], 200);

        } catch (PaymentGatewayException $e) {
            // Exception já tem render() com status code
            throw $e; // Handler cuida
        }
    }
}

// Job (assíncrono)
class SyncChargeStatusJob implements ShouldQueue
{
    public function handle(SyncChargeStatusFromGatewayAction $action): void
    {
        $charge = Charge::find($this->chargeId);
        $action->execute($charge); // Retorna Model atualizado
    }
}

// Command (CLI)
class SyncAllChargesCommand extends Command
{
    public function handle(SyncChargeStatusFromGatewayAction $action): void
    {
        Charge::whereNotNull('gateway_charge_id')->each(function ($charge) use ($action) {
            $action->execute($charge);
        });
    }
}
```

---

## Referências

- [Prompt.MD](../Prompt.MD): Arquitetura completa do projeto
- [Task 01](01-CUSTOMER-DOMAIN.md): Exemplo de domínio sem Repository
- [Task 02](02-CHARGE-DOMAIN.md): Exemplo de domínio sem Repository
- Laravel HTTP Client: https://laravel.com/docs/11.x/http-client
- Laravel Encryption: https://laravel.com/docs/11.x/encryption
- Strategy Pattern: https://refactoring.guru/design-patterns/strategy
- Factory Pattern: https://refactoring.guru/design-patterns/factory-method
