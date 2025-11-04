# 04 - Webhook Domain (Domínio de Webhooks)

## Objetivo
Implementar processamento assíncrono e seguro de webhooks dos gateways de pagamento.

## Prioridade
🔴 ALTA - Crítico para atualização de status de cobranças

## Dependências
- Task 00 (Setup Inicial)
- Task 02 (Charge Domain)
- Task 03 (Payment Gateway Domain)

---

## Ordem de Implementação

### 1. Enum
- [ ] Criar `app/Enums/WebhookEventType.php`
  ```php
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
  }
  ```

- [ ] Criar `app/Enums/WebhookStatus.php`
  ```php
  enum WebhookStatus: string
  {
      case PENDING = 'pending';
      case PROCESSING = 'processing';
      case PROCESSED = 'processed';
      case FAILED = 'failed';
      case IGNORED = 'ignored';
  }
  ```

### 2. DTOs
- [ ] Criar `app/DTOs/Webhook/WebhookPayloadDTO.php`
  ```php
  readonly class WebhookPayloadDTO
  {
      public function __construct(
          public PaymentGatewayType $gateway,
          public WebhookEventType $eventType,
          public array $payload,
          public ?string $signature = null
      ) {}

      public static function fromRequest(PaymentGatewayType $gateway, array $data): self;
      public function toArray(): array;
  }
  ```

### 3. Migration & Model
- [ ] Criar migration `create_webhook_logs_table`
  ```php
  Schema::create('webhook_logs', function (Blueprint $table) {
      $table->id();
      $table->enum('gateway', ['pagseguro', 'asaas', 'stone']);
      $table->enum('event_type', [
          'charge.created',
          'charge.updated',
          'charge.paid',
          'charge.cancelled',
          'charge.refunded',
          'charge.expired',
          'payment.received',
          'unknown'
      ])->default('unknown');
      $table->enum('status', ['pending', 'processing', 'processed', 'failed', 'ignored'])
            ->default('pending');
      $table->json('payload');
      $table->text('error_message')->nullable();
      $table->integer('retry_count')->default(0);
      $table->timestamp('processed_at')->nullable();
      $table->timestamps();

      // Índices
      $table->index(['gateway', 'status']);
      $table->index('event_type');
      $table->index('status');
      $table->index('created_at');
  });
  ```

- [ ] Criar `app/Models/WebhookLog.php`
  - Casts: gateway -> PaymentGatewayType
  - Casts: event_type -> WebhookEventType
  - Casts: status -> WebhookStatus
  - Casts: payload -> array
  - Casts: processed_at -> datetime
  - Scopes: `scopePending()`, `scopeFailed()`, `scopeByGateway()`
  - Método: `markAsProcessing()`, `markAsProcessed()`, `markAsFailed(string $error)`

### 4. Events
- [ ] Criar `app/Events/WebhookReceived.php`
  ```php
  class WebhookReceived
  {
      public function __construct(
          public readonly WebhookLog $webhookLog
      ) {}
  }
  ```

- [ ] Criar `app/Events/WebhookProcessed.php`
- [ ] Criar `app/Events/WebhookFailed.php`

### 5. Jobs
- [ ] Criar `app/Jobs/ProcessWebhook.php`
  ```php
  class ProcessWebhook implements ShouldQueue
  {
      use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

      public int $tries = 3;
      public int $timeout = 60;
      public int $backoff = 300; // 5 minutos entre retries

      public function __construct(
          private readonly PaymentGatewayType $gateway,
          private readonly array $payload,
          private readonly ?int $webhookLogId = null
      ) {}

      public function handle(
          ChargeRepositoryInterface $chargeRepository,
          ChargeService $chargeService
      ): void {
          $webhookLog = $this->getOrCreateWebhookLog();
          $webhookLog->markAsProcessing();

          try {
              // 1. Obter gateway
              $gateway = PaymentGatewayFactory::create($this->gateway);

              // 2. Validar assinatura (se fornecida)
              if (isset($this->payload['signature'])) {
                  $isValid = $gateway->validateWebhookSignature(
                      $this->payload['signature'],
                      $this->payload
                  );

                  if (!$isValid) {
                      throw new PaymentGatewayException('Invalid webhook signature');
                  }
              }

              // 3. Processar payload
              $processedData = $gateway->processWebhook($this->payload);

              // 4. Buscar cobrança
              $charge = $chargeRepository->findByGatewayChargeId(
                  $processedData['charge_id']
              );

              if (!$charge) {
                  Log::warning('Charge not found for webhook', [
                      'gateway' => $this->gateway->value,
                      'charge_id' => $processedData['charge_id'],
                      'payload' => $this->payload
                  ]);

                  $webhookLog->update([
                      'status' => WebhookStatus::IGNORED,
                      'error_message' => 'Charge not found',
                      'processed_at' => now()
                  ]);

                  return;
              }

              // 5. Atualizar status se mudou
              $newStatus = ChargeStatus::from($processedData['status']);

              if ($charge->status !== $newStatus) {
                  DB::transaction(function () use ($chargeService, $charge, $newStatus, $processedData) {
                      $chargeService->updateStatus($charge->id, $newStatus);

                      // Atualizar paid_at se pago
                      if ($newStatus === ChargeStatus::PAID && isset($processedData['paid_at'])) {
                          $charge->update(['paid_at' => $processedData['paid_at']]);
                      }
                  });

                  // Disparar evento
                  if ($newStatus === ChargeStatus::PAID) {
                      event(new ChargePaid($charge));
                  }
              }

              // 6. Marcar webhook como processado
              $webhookLog->markAsProcessed();

              // 7. Disparar evento
              event(new WebhookProcessed($webhookLog));

              Log::info('Webhook processed successfully', [
                  'webhook_log_id' => $webhookLog->id,
                  'gateway' => $this->gateway->value,
                  'charge_id' => $charge->id
              ]);

          } catch (\\Throwable $e) {
              $webhookLog->markAsFailed($e->getMessage());

              Log::error('Webhook processing failed', [
                  'webhook_log_id' => $webhookLog->id,
                  'gateway' => $this->gateway->value,
                  'error' => $e->getMessage(),
                  'trace' => $e->getTraceAsString()
              ]);

              event(new WebhookFailed($webhookLog));

              throw $e; // Re-throw para retry
          }
      }

      public function failed(\\Throwable $exception): void
      {
          if ($this->webhookLogId) {
              $webhookLog = WebhookLog::find($this->webhookLogId);
              $webhookLog?->markAsFailed($exception->getMessage());
          }

          Log::error('Webhook job failed permanently', [
              'gateway' => $this->gateway->value,
              'error' => $exception->getMessage(),
              'payload' => $this->payload
          ]);
      }

      private function getOrCreateWebhookLog(): WebhookLog
      {
          if ($this->webhookLogId) {
              return WebhookLog::find($this->webhookLogId);
          }

          return WebhookLog::create([
              'gateway' => $this->gateway,
              'event_type' => $this->detectEventType(),
              'status' => WebhookStatus::PENDING,
              'payload' => $this->payload,
          ]);
      }

      private function detectEventType(): WebhookEventType
      {
          // Lógica para detectar tipo de evento baseado no payload
          // Cada gateway tem sua estrutura
          return WebhookEventType::UNKNOWN;
      }
  }
  ```

### 6. Middleware
- [ ] Criar `app/Http/Middleware/ValidateWebhookSignature.php`
  ```php
  class ValidateWebhookSignature
  {
      public function handle(Request $request, Closure $next, string $gateway): Response
      {
          $gatewayType = PaymentGatewayType::from($gateway);
          $gateway = PaymentGatewayFactory::create($gatewayType);

          $signature = $request->header('X-Signature')
                    ?? $request->header('X-Hub-Signature')
                    ?? $request->input('signature');

          if (!$signature) {
              Log::warning('Webhook signature missing', [
                  'gateway' => $gatewayType->value,
                  'headers' => $request->headers->all()
              ]);

              return response()->json([
                  'error' => 'Signature missing'
              ], 401);
          }

          $isValid = $gateway->validateWebhookSignature(
              $signature,
              $request->all()
          );

          if (!$isValid) {
              Log::warning('Invalid webhook signature', [
                  'gateway' => $gatewayType->value,
                  'signature' => $signature
              ]);

              return response()->json([
                  'error' => 'Invalid signature'
              ], 401);
          }

          return $next($request);
      }
  }
  ```

- [ ] Registrar middleware em `app/Http/Kernel.php`

### 7. Controller
- [ ] Criar `app/Http/Controllers/Api/V1/WebhookController.php`
  ```php
  class WebhookController extends Controller
  {
      /**
       * Webhook PagSeguro
       */
      public function pagseguro(Request $request): Response
      {
          return $this->processWebhook(PaymentGatewayType::PAGSEGURO, $request);
      }

      /**
       * Webhook Asaas
       */
      public function asaas(Request $request): Response
      {
          return $this->processWebhook(PaymentGatewayType::ASAAS, $request);
      }

      /**
       * Webhook Stone
       */
      public function stone(Request $request): Response
      {
          return $this->processWebhook(PaymentGatewayType::STONE, $request);
      }

      /**
       * Processar webhook genérico
       */
      private function processWebhook(PaymentGatewayType $gateway, Request $request): Response
      {
          // 1. Logar webhook recebido
          Log::info('Webhook received', [
              'gateway' => $gateway->value,
              'ip' => $request->ip(),
              'user_agent' => $request->userAgent()
          ]);

          // 2. Criar log do webhook
          $webhookLog = WebhookLog::create([
              'gateway' => $gateway,
              'event_type' => WebhookEventType::UNKNOWN,
              'status' => WebhookStatus::PENDING,
              'payload' => $request->all(),
          ]);

          // 3. Disparar evento
          event(new WebhookReceived($webhookLog));

          // 4. Processar assincronamente
          ProcessWebhook::dispatch(
              $gateway,
              $request->all(),
              $webhookLog->id
          );

          // 5. Retornar 200 rapidamente
          return response()->noContent();
      }
  }
  ```

### 8. Routes
- [ ] Adicionar rotas em `routes/api.php`
  ```php
  // Webhooks (sem autenticação, mas com validação de assinatura)
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

### 9. Repository
- [ ] Criar `app/Repositories/Contracts/WebhookLogRepositoryInterface.php`
  ```php
  interface WebhookLogRepositoryInterface
  {
      public function find(int $id): ?WebhookLog;
      public function create(array $data): WebhookLog;
      public function getPending(int $limit = 100): Collection;
      public function getFailed(int $limit = 100): Collection;
      public function getByGateway(PaymentGatewayType $gateway, int $perPage = 15): LengthAwarePaginator;
      public function retryFailed(): int;
  }
  ```

- [ ] Criar `app/Repositories/Eloquent/WebhookLogRepository.php`
- [ ] Registrar binding no `AppServiceProvider`

### 10. Commands (Artisan)
- [ ] Criar `app/Console/Commands/RetryFailedWebhooks.php`
  ```php
  class RetryFailedWebhooks extends Command
  {
      protected $signature = 'webhooks:retry-failed
                            {--limit=100 : Maximum webhooks to retry}';

      protected $description = 'Retry failed webhook processing';

      public function handle(WebhookLogRepositoryInterface $repository): int
      {
          $limit = (int) $this->option('limit');

          $failedWebhooks = $repository->getFailed($limit);

          if ($failedWebhooks->isEmpty()) {
              $this->info('No failed webhooks to retry');
              return Command::SUCCESS;
          }

          $this->info("Found {$failedWebhooks->count()} failed webhooks");

          $bar = $this->output->createProgressBar($failedWebhooks->count());

          foreach ($failedWebhooks as $webhookLog) {
              ProcessWebhook::dispatch(
                  $webhookLog->gateway,
                  $webhookLog->payload,
                  $webhookLog->id
              );

              $bar->advance();
          }

          $bar->finish();
          $this->newLine();
          $this->info('Failed webhooks dispatched for retry');

          return Command::SUCCESS;
      }
  }
  ```

- [ ] Criar `app/Console/Commands/CleanOldWebhookLogs.php`
  ```php
  class CleanOldWebhookLogs extends Command
  {
      protected $signature = 'webhooks:clean
                            {--days=30 : Days to keep}';

      protected $description = 'Clean old webhook logs';

      public function handle(): int
      {
          $days = (int) $this->option('days');
          $date = now()->subDays($days);

          $count = WebhookLog::where('created_at', '<', $date)
              ->where('status', WebhookStatus::PROCESSED)
              ->delete();

          $this->info("Deleted {$count} old webhook logs");

          return Command::SUCCESS;
      }
  }
  ```

### 11. Scheduled Tasks
- [ ] Adicionar em `app/Console/Kernel.php`
  ```php
  protected function schedule(Schedule $schedule): void
  {
      // Retentar webhooks falhos a cada hora
      $schedule->command('webhooks:retry-failed --limit=50')
          ->hourly()
          ->withoutOverlapping();

      // Limpar logs antigos diariamente
      $schedule->command('webhooks:clean --days=30')
          ->daily()
          ->at('02:00');
  }
  ```

### 12. Testes
- [ ] Criar `tests/Feature/Api/V1/WebhookTest.php`
  - `test_pagseguro_webhook_is_processed()`
  - `test_asaas_webhook_is_processed()`
  - `test_stone_webhook_is_processed()`
  - `test_webhook_creates_log()`
  - `test_webhook_dispatches_job()`
  - `test_webhook_returns_204()`
  - `test_invalid_signature_is_rejected()`
  - `test_missing_signature_is_rejected()`

- [ ] Criar `tests/Unit/Jobs/ProcessWebhookTest.php`
  - `test_updates_charge_status()`
  - `test_ignores_unknown_charge()`
  - `test_validates_signature()`
  - `test_marks_webhook_as_processed()`
  - `test_marks_webhook_as_failed_on_error()`
  - `test_dispatches_events()`
  - `test_retry_logic()`

- [ ] Criar `tests/Unit/Commands/RetryFailedWebhooksTest.php`

---

## Checklist de Qualidade

### Arquitetura
- [ ] Processamento assíncrono (Jobs)
- [ ] Event-Driven (disparar eventos)
- [ ] Idempotência (processar múltiplas vezes = mesmo resultado)
- [ ] Retry logic robusto
- [ ] Logging completo

### Código
- [ ] Type hints completos
- [ ] Exception handling robusto
- [ ] Transactions em operações críticas
- [ ] Validação de assinatura

### Segurança
- [ ] Validação de assinatura obrigatória
- [ ] Logging de tentativas suspeitas
- [ ] Rate limiting (opcional)
- [ ] Whitelist de IPs (opcional)

### Performance
- [ ] Resposta rápida (< 1s)
- [ ] Processamento assíncrono
- [ ] Queue workers configurados
- [ ] Timeout apropriado

### Confiabilidade
- [ ] Retry logic com backoff
- [ ] Logging de todas as operações
- [ ] Webhook logs persistidos
- [ ] Command para retry manual
- [ ] Limpeza de logs antigos

---

## Critérios de Aceitação

✅ **Funcionalidade**
- Webhooks sendo recebidos
- Jobs processando assincronamente
- Status de cobranças atualizados
- Logs sendo criados
- Retry automático funcionando

✅ **Segurança**
- Assinaturas validadas
- Webhooks inválidos rejeitados
- Logs de tentativas suspeitas

✅ **Confiabilidade**
- Retry em caso de falha
- Idempotência garantida
- Logs completos
- Commands de retry funcionando

✅ **Performance**
- Resposta < 1s
- Processing assíncrono
- Queue workers ativos

---

## Exemplos de Payloads

### PagSeguro
```json
{
  "id": "CHG-123456",
  "status": "paid",
  "amount": 150.50,
  "paid_at": "2024-10-15T10:30:00Z",
  "payment_method": "pix"
}
```

### Asaas
```json
{
  "id": "pay_123456",
  "status": "CONFIRMED",
  "value": 150.50,
  "confirmedDate": "2024-10-15",
  "billingType": "PIX"
}
```

---

## Notas Importantes

⚠️ **Atenção**
- SEMPRE retornar 200/204 rapidamente
- NUNCA processar webhook sincronamente
- SEMPRE validar assinatura
- SEMPRE logar webhooks recebidos
- Implementar idempotência
- Usar transactions em atualizações
- Configurar queue workers adequadamente
- Monitorar failed jobs

⚠️ **Idempotência**
Processar o mesmo webhook múltiplas vezes deve resultar no mesmo estado final:
- Verificar se já foi processado
- Usar transactions
- Comparar status antes de atualizar

⚠️ **Monitoramento**
- Monitorar failed jobs
- Alertar sobre webhooks com muitas falhas
- Dashboards de webhooks recebidos/processados
- Logs centralizados

📚 **Referências**
- Prompt.MD: webhook_processing, event_driven_architecture
- Laravel Queues & Jobs
- Laravel Events
- Webhook best practices
