# 02 - Charge Domain (Domínio de Cobranças)

## Objetivo
Implementar o domínio completo de gerenciamento de cobranças com CRUD, seguindo a arquitetura baseada em Actions/Queries/Exceptions.

## Prioridade
🔴 ALTA - Domínio core do sistema

## Dependências
- Setup Inicial (Task 00)
- Customer Domain (Task 01)

---

## Ordem de Implementação

### 1. Enums
- [ ] Criar `app/Enums/ChargeStatus.php`
  ```php
  enum ChargeStatus: string
  {
      case PENDING = 'pending';
      case PAID = 'paid';
      case CANCELLED = 'cancelled';
      case REFUNDED = 'refunded';
      case EXPIRED = 'expired';
      case FAILED = 'failed';
  }
  ```

- [ ] Criar `app/Enums/PaymentMethod.php`
  ```php
  enum PaymentMethod: string
  {
      case CREDIT_CARD = 'credit_card';
      case DEBIT_CARD = 'debit_card';
      case BOLETO = 'boleto';
      case PIX = 'pix';
  }
  ```

### 2. Custom Exceptions
- [ ] Criar `app/Exceptions/ChargeException.php` (Base)
  ```php
  abstract class ChargeException extends Exception
  {
      protected int $statusCode = 400;

      public function getStatusCode(): int
      {
          return $this->statusCode;
      }

      abstract public function render(): JsonResponse;
  }
  ```

- [ ] Criar `app/Exceptions/ChargeNotFoundException.php`
  ```php
  class ChargeNotFoundException extends ChargeException
  {
      protected int $statusCode = 404;

      public function __construct(int $id)
      {
          parent::__construct("Charge #{$id} not found");
      }

      public function render(): JsonResponse
      {
          return response()->json([
              'message' => $this->getMessage(),
              'error' => 'charge_not_found',
          ], $this->statusCode);
      }
  }
  ```

- [ ] Criar `app/Exceptions/ChargeCannotBeCancelledException.php`
  ```php
  class ChargeCannotBeCancelledException extends ChargeException
  {
      protected int $statusCode = 422;

      public function __construct(string $reason)
      {
          parent::__construct("Charge cannot be cancelled: {$reason}");
      }

      public function render(): JsonResponse
      {
          return response()->json([
              'message' => $this->getMessage(),
              'error' => 'charge_cannot_be_cancelled',
          ], $this->statusCode);
      }
  }
  ```

- [ ] Criar `app/Exceptions/ChargeAlreadyPaidException.php`
  ```php
  class ChargeAlreadyPaidException extends ChargeException
  {
      protected int $statusCode = 422;

      public function __construct(int $id)
      {
          parent::__construct("Charge #{$id} is already paid and cannot be modified");
      }

      public function render(): JsonResponse
      {
          return response()->json([
              'message' => $this->getMessage(),
              'error' => 'charge_already_paid',
          ], $this->statusCode);
      }
  }
  ```

- [ ] Criar `app/Exceptions/InvalidChargeDataException.php`
  ```php
  class InvalidChargeDataException extends ChargeException
  {
      protected int $statusCode = 422;

      public function __construct(string $field, string $reason)
      {
          parent::__construct("Invalid charge data: {$field} - {$reason}");
      }

      public function render(): JsonResponse
      {
          return response()->json([
              'message' => $this->getMessage(),
              'error' => 'invalid_charge_data',
          ], $this->statusCode);
      }
  }
  ```

### 3. DTOs (Data Transfer Objects)
- [ ] Criar `app/DTOs/Charge/CreateChargeDTO.php`
  ```php
  readonly class CreateChargeDTO
  {
      public function __construct(
          public int $customerId,
          public float $amount,
          public string $description,
          public PaymentMethod $paymentMethod,
          public string $dueDate,
          public ?int $paymentGatewayId = null,
          public ?array $metadata = null
      ) {}

      public static function fromRequest(array $data): self
      {
          return new self(
              customerId: $data['customer_id'],
              amount: $data['amount'],
              description: $data['description'],
              paymentMethod: PaymentMethod::from($data['payment_method']),
              dueDate: $data['due_date'],
              paymentGatewayId: $data['payment_gateway_id'] ?? null,
              metadata: $data['metadata'] ?? null
          );
      }

      public function toArray(): array
      {
          return [
              'customer_id' => $this->customerId,
              'payment_gateway_id' => $this->paymentGatewayId,
              'amount' => $this->amount,
              'description' => $this->description,
              'payment_method' => $this->paymentMethod,
              'status' => ChargeStatus::PENDING,
              'due_date' => $this->dueDate,
              'metadata' => $this->metadata,
          ];
      }
  }
  ```

- [ ] Criar `app/DTOs/Charge/UpdateChargeDTO.php`
  ```php
  readonly class UpdateChargeDTO
  {
      public function __construct(
          public ?float $amount = null,
          public ?string $description = null,
          public ?string $dueDate = null,
          public ?array $metadata = null
      ) {}

      public static function fromRequest(array $data): self
      {
          return new self(
              amount: $data['amount'] ?? null,
              description: $data['description'] ?? null,
              dueDate: $data['due_date'] ?? null,
              metadata: $data['metadata'] ?? null
          );
      }

      public function toArray(): array
      {
          return array_filter([
              'amount' => $this->amount,
              'description' => $this->description,
              'due_date' => $this->dueDate,
              'metadata' => $this->metadata,
          ], fn($value) => $value !== null);
      }
  }
  ```

### 4. Migration & Model
- [ ] Criar migration `create_charges_table`
  ```php
  Schema::create('charges', function (Blueprint $table) {
      $table->id();
      $table->foreignId('customer_id')->constrained()->onDelete('cascade');
      $table->foreignId('payment_gateway_id')->nullable()->constrained();
      $table->string('gateway_charge_id')->nullable()->unique();
      $table->decimal('amount', 10, 2);
      $table->string('description');
      $table->enum('payment_method', ['credit_card', 'debit_card', 'boleto', 'pix']);
      $table->enum('status', ['pending', 'paid', 'cancelled', 'refunded', 'expired', 'failed'])
            ->default('pending');
      $table->date('due_date');
      $table->timestamp('paid_at')->nullable();
      $table->json('metadata')->nullable();
      $table->timestamps();
      $table->softDeletes();

      // Índices para performance
      $table->index(['customer_id', 'status']);
      $table->index('gateway_charge_id');
      $table->index('due_date');
      $table->index('status');
      $table->index('created_at');
  });
  ```

- [ ] Criar `app/Models/Charge.php`
  ```php
  class Charge extends Model
  {
      use HasFactory, SoftDeletes;

      protected $fillable = [
          'customer_id',
          'payment_gateway_id',
          'gateway_charge_id',
          'amount',
          'description',
          'payment_method',
          'status',
          'due_date',
          'paid_at',
          'metadata',
      ];

      protected $casts = [
          'amount' => 'decimal:2',
          'payment_method' => PaymentMethod::class,
          'status' => ChargeStatus::class,
          'due_date' => 'date',
          'paid_at' => 'datetime',
          'metadata' => 'array',
          'created_at' => 'datetime',
          'updated_at' => 'datetime',
          'deleted_at' => 'datetime',
      ];

      // Relationships
      public function customer(): BelongsTo
      {
          return $this->belongsTo(Customer::class);
      }

      public function paymentGateway(): BelongsTo
      {
          return $this->belongsTo(PaymentGateway::class);
      }

      // Scopes
      public function scopePaid(Builder $query): void
      {
          $query->where('status', ChargeStatus::PAID);
      }

      public function scopePending(Builder $query): void
      {
          $query->where('status', ChargeStatus::PENDING);
      }

      public function scopeOverdue(Builder $query): void
      {
          $query->where('status', ChargeStatus::PENDING)
              ->where('due_date', '<', now());
      }

      public function scopeDueToday(Builder $query): void
      {
          $query->where('status', ChargeStatus::PENDING)
              ->whereDate('due_date', today());
      }

      public function scopeByCustomer(Builder $query, int $customerId): void
      {
          $query->where('customer_id', $customerId);
      }

      // Accessors
      public function isPaid(): bool
      {
          return $this->status === ChargeStatus::PAID;
      }

      public function isOverdue(): bool
      {
          return $this->due_date->isPast() && !$this->isPaid();
      }

      public function canBeCancelled(): bool
      {
          return !in_array($this->status, [
              ChargeStatus::PAID,
              ChargeStatus::CANCELLED,
              ChargeStatus::REFUNDED
          ]);
      }

      public function canBeUpdated(): bool
      {
          return !in_array($this->status, [
              ChargeStatus::PAID,
              ChargeStatus::CANCELLED,
              ChargeStatus::REFUNDED
          ]);
      }
  }
  ```

### 5. Actions (Write Operations)
- [ ] Criar `app/Actions/Charge/CreateChargeAction.php`
  ```php
  class CreateChargeAction
  {
      /**
       * Cria uma nova cobrança
       *
       * @throws CustomerNotFoundException
       * @throws InvalidChargeDataException
       */
      public function execute(CreateChargeDTO $dto): Charge
      {
          // Validar customer existe
          $customer = Customer::find($dto->customerId);
          if (!$customer) {
              throw new CustomerNotFoundException($dto->customerId);
          }

          // Validar amount positivo
          if ($dto->amount <= 0) {
              throw new InvalidChargeDataException('amount', 'Amount must be greater than 0');
          }

          // Validar due_date não está no passado
          $dueDate = Carbon::parse($dto->dueDate);
          if ($dueDate->isPast()) {
              throw new InvalidChargeDataException('due_date', 'Due date cannot be in the past');
          }

          return DB::transaction(function () use ($dto) {
              $charge = Charge::create($dto->toArray());

              event(new ChargeCreated($charge));

              return $charge;
          });
      }
  }
  ```

- [ ] Criar `app/Actions/Charge/UpdateChargeAction.php`
  ```php
  class UpdateChargeAction
  {
      /**
       * Atualiza cobrança existente
       *
       * @throws ChargeNotFoundException
       * @throws ChargeAlreadyPaidException
       * @throws InvalidChargeDataException
       */
      public function execute(int $id, UpdateChargeDTO $dto): Charge
      {
          $charge = Charge::find($id);

          if (!$charge) {
              throw new ChargeNotFoundException($id);
          }

          // Validar se cobrança pode ser atualizada
          if (!$charge->canBeUpdated()) {
              throw new ChargeAlreadyPaidException($id);
          }

          // Validar amount positivo (se estiver sendo alterado)
          if ($dto->amount !== null && $dto->amount <= 0) {
              throw new InvalidChargeDataException('amount', 'Amount must be greater than 0');
          }

          // Validar due_date não está no passado (se estiver sendo alterado)
          if ($dto->dueDate !== null) {
              $dueDate = Carbon::parse($dto->dueDate);
              if ($dueDate->isPast()) {
                  throw new InvalidChargeDataException('due_date', 'Due date cannot be in the past');
              }
          }

          return DB::transaction(function () use ($charge, $dto) {
              $charge->update($dto->toArray());
              $charge->refresh();

              event(new ChargeUpdated($charge));

              return $charge;
          });
      }
  }
  ```

- [ ] Criar `app/Actions/Charge/CancelChargeAction.php`
  ```php
  class CancelChargeAction
  {
      /**
       * Cancela cobrança
       *
       * @throws ChargeNotFoundException
       * @throws ChargeCannotBeCancelledException
       */
      public function execute(int $id, string $reason): Charge
      {
          $charge = Charge::find($id);

          if (!$charge) {
              throw new ChargeNotFoundException($id);
          }

          if ($charge->status === ChargeStatus::PAID) {
              throw new ChargeCannotBeCancelledException('Charge already paid');
          }

          if ($charge->status === ChargeStatus::CANCELLED) {
              throw new ChargeCannotBeCancelledException('Charge already cancelled');
          }

          if ($charge->status === ChargeStatus::REFUNDED) {
              throw new ChargeCannotBeCancelledException('Charge already refunded');
          }

          return DB::transaction(function () use ($charge, $reason) {
              $charge->update([
                  'status' => ChargeStatus::CANCELLED,
                  'metadata' => array_merge($charge->metadata ?? [], [
                      'cancellation_reason' => $reason,
                      'cancelled_at' => now()->toIso8601String(),
                  ])
              ]);

              event(new ChargeCancelled($charge));

              return $charge->fresh();
          });
      }
  }
  ```

- [ ] Criar `app/Actions/Charge/MarkChargeAsPaidAction.php`
  ```php
  class MarkChargeAsPaidAction
  {
      /**
       * Marca cobrança como paga
       *
       * @throws ChargeNotFoundException
       * @throws ChargeCannotBeCancelledException
       */
      public function execute(int $id, ?string $paidAt = null): Charge
      {
          $charge = Charge::find($id);

          if (!$charge) {
              throw new ChargeNotFoundException($id);
          }

          if ($charge->status === ChargeStatus::PAID) {
              return $charge; // Já está paga, retorna sem erro
          }

          if ($charge->status === ChargeStatus::CANCELLED) {
              throw new ChargeCannotBeCancelledException('Cannot mark cancelled charge as paid');
          }

          return DB::transaction(function () use ($charge, $paidAt) {
              $charge->update([
                  'status' => ChargeStatus::PAID,
                  'paid_at' => $paidAt ? Carbon::parse($paidAt) : now(),
              ]);

              event(new ChargePaid($charge));

              return $charge->fresh();
          });
      }
  }
  ```

- [ ] Criar `app/Actions/Charge/RefundChargeAction.php`
  ```php
  class RefundChargeAction
  {
      /**
       * Reembolsa cobrança paga
       *
       * @throws ChargeNotFoundException
       * @throws ChargeCannotBeCancelledException
       */
      public function execute(int $id, string $reason): Charge
      {
          $charge = Charge::find($id);

          if (!$charge) {
              throw new ChargeNotFoundException($id);
          }

          if ($charge->status !== ChargeStatus::PAID) {
              throw new ChargeCannotBeCancelledException('Only paid charges can be refunded');
          }

          return DB::transaction(function () use ($charge, $reason) {
              $charge->update([
                  'status' => ChargeStatus::REFUNDED,
                  'metadata' => array_merge($charge->metadata ?? [], [
                      'refund_reason' => $reason,
                      'refunded_at' => now()->toIso8601String(),
                  ])
              ]);

              event(new ChargeRefunded($charge));

              return $charge->fresh();
          });
      }
  }
  ```

- [ ] Criar `app/Actions/Charge/UpdateChargeStatusAction.php`
  ```php
  class UpdateChargeStatusAction
  {
      /**
       * Atualiza status da cobrança (usado por sincronização com gateway)
       *
       * @throws ChargeNotFoundException
       */
      public function execute(int $id, ChargeStatus $status, ?array $metadata = null): Charge
      {
          $charge = Charge::find($id);

          if (!$charge) {
              throw new ChargeNotFoundException($id);
          }

          return DB::transaction(function () use ($charge, $status, $metadata) {
              $updateData = ['status' => $status];

              // Se mudou para pago, adicionar paid_at
              if ($status === ChargeStatus::PAID && !$charge->paid_at) {
                  $updateData['paid_at'] = now();
              }

              // Merge metadata se fornecido
              if ($metadata) {
                  $updateData['metadata'] = array_merge($charge->metadata ?? [], $metadata);
              }

              $charge->update($updateData);

              // Disparar evento apropriado
              if ($status === ChargeStatus::PAID) {
                  event(new ChargePaid($charge));
              }

              return $charge->fresh();
          });
      }
  }
  ```

### 6. Queries (Read Operations)
- [ ] Criar `app/Queries/Charge/GetChargeByIdQuery.php`
  ```php
  class GetChargeByIdQuery
  {
      /**
       * Busca cobrança por ID
       */
      public function execute(int $id): ?Charge
      {
          return Charge::with(['customer', 'paymentGateway'])
              ->find($id);
      }
  }
  ```

- [ ] Criar `app/Queries/Charge/GetAllChargesQuery.php`
  ```php
  class GetAllChargesQuery
  {
      /**
       * Lista todas as cobranças paginadas
       */
      public function execute(int $perPage = 15): LengthAwarePaginator
      {
          return Charge::with(['customer', 'paymentGateway'])
              ->latest()
              ->paginate($perPage);
      }
  }
  ```

- [ ] Criar `app/Queries/Charge/GetCustomerChargesQuery.php`
  ```php
  class GetCustomerChargesQuery
  {
      /**
       * Lista cobranças de um cliente específico
       */
      public function execute(int $customerId, int $perPage = 15): LengthAwarePaginator
      {
          return Charge::query()
              ->where('customer_id', $customerId)
              ->with(['paymentGateway'])
              ->latest()
              ->paginate($perPage);
      }
  }
  ```

- [ ] Criar `app/Queries/Charge/GetPendingChargesQuery.php`
  ```php
  class GetPendingChargesQuery
  {
      /**
       * Lista apenas cobranças pendentes
       */
      public function execute(int $perPage = 15): LengthAwarePaginator
      {
          return Charge::pending()
              ->with(['customer', 'paymentGateway'])
              ->latest()
              ->paginate($perPage);
      }
  }
  ```

- [ ] Criar `app/Queries/Charge/GetOverdueChargesQuery.php`
  ```php
  class GetOverdueChargesQuery
  {
      /**
       * Lista apenas cobranças vencidas
       */
      public function execute(int $perPage = 15): LengthAwarePaginator
      {
          return Charge::overdue()
              ->with(['customer', 'paymentGateway'])
              ->oldest('due_date')
              ->paginate($perPage);
      }
  }
  ```

- [ ] Criar `app/Queries/Charge/GetChargesWithFiltersQuery.php`
  ```php
  class GetChargesWithFiltersQuery
  {
      /**
       * Busca cobranças com filtros avançados
       */
      public function execute(
          ?array $statuses = null,
          ?string $dateFrom = null,
          ?string $dateTo = null,
          ?int $customerId = null,
          int $perPage = 15
      ): LengthAwarePaginator {
          return Charge::query()
              ->with(['customer', 'paymentGateway'])
              ->when($statuses, fn($q) => $q->whereIn('status', $statuses))
              ->when($dateFrom, fn($q) => $q->whereDate('due_date', '>=', $dateFrom))
              ->when($dateTo, fn($q) => $q->whereDate('due_date', '<=', $dateTo))
              ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
              ->latest()
              ->paginate($perPage);
      }
  }
  ```

- [ ] Criar `app/Queries/Charge/GetChargeByGatewayIdQuery.php`
  ```php
  class GetChargeByGatewayIdQuery
  {
      /**
       * Busca cobrança por ID do gateway
       */
      public function execute(string $gatewayChargeId): ?Charge
      {
          return Charge::where('gateway_charge_id', $gatewayChargeId)
              ->with(['customer', 'paymentGateway'])
              ->first();
      }
  }
  ```

### 7. Events
- [ ] Criar `app/Events/ChargeCreated.php`
  ```php
  class ChargeCreated
  {
      public function __construct(
          public readonly Charge $charge
      ) {}
  }
  ```

- [ ] Criar `app/Events/ChargeUpdated.php`
  ```php
  class ChargeUpdated
  {
      public function __construct(
          public readonly Charge $charge
      ) {}
  }
  ```

- [ ] Criar `app/Events/ChargePaid.php`
  ```php
  class ChargePaid
  {
      public function __construct(
          public readonly Charge $charge
      ) {}
  }
  ```

- [ ] Criar `app/Events/ChargeCancelled.php`
  ```php
  class ChargeCancelled
  {
      public function __construct(
          public readonly Charge $charge
      ) {}
  }
  ```

- [ ] Criar `app/Events/ChargeRefunded.php`
  ```php
  class ChargeRefunded
  {
      public function __construct(
          public readonly Charge $charge
      ) {}
  }
  ```

### 8. Listeners
- [ ] Criar `app/Listeners/SendChargeNotification.php`
  ```php
  class SendChargeNotification implements ShouldQueue
  {
      public function handle(ChargeCreated $event): void
      {
          // Enviar notificação (email/SMS) quando cobrança é criada
          $charge = $event->charge;
          $customer = $charge->customer;

          // TODO: Implementar envio de notificação
          Log::info("Charge created notification sent", [
              'charge_id' => $charge->id,
              'customer_id' => $customer->id,
          ]);
      }
  }
  ```

- [ ] Criar `app/Listeners/SendPaymentConfirmation.php`
  ```php
  class SendPaymentConfirmation implements ShouldQueue
  {
      public function handle(ChargePaid $event): void
      {
          // Enviar confirmação de pagamento
          $charge = $event->charge;
          $customer = $charge->customer;

          // TODO: Implementar envio de confirmação
          Log::info("Payment confirmation sent", [
              'charge_id' => $charge->id,
              'customer_id' => $customer->id,
          ]);
      }
  }
  ```

- [ ] Registrar listeners em `EventServiceProvider`
  ```php
  protected $listen = [
      ChargeCreated::class => [
          SendChargeNotification::class,
      ],
      ChargePaid::class => [
          SendPaymentConfirmation::class,
      ],
  ];
  ```

### 9. Jobs
- [ ] Criar `app/Jobs/SyncChargeStatusJob.php`
  ```php
  class SyncChargeStatusJob implements ShouldQueue
  {
      use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

      public $tries = 3;
      public $backoff = [60, 300, 900]; // 1min, 5min, 15min

      public function __construct(
          public int $chargeId
      ) {}

      public function handle(UpdateChargeStatusAction $action): void
      {
          $charge = Charge::find($this->chargeId);

          if (!$charge || !$charge->gateway_charge_id) {
              return;
          }

          // TODO: Buscar status do gateway
          // $gatewayStatus = PaymentGatewayService::getChargeStatus($charge->gateway_charge_id);
          // $action->execute($charge->id, ChargeStatus::from($gatewayStatus), ['synced_at' => now()]);

          Log::info("Charge status synced", ['charge_id' => $this->chargeId]);
      }

      public function failed(Throwable $exception): void
      {
          Log::error("Failed to sync charge status", [
              'charge_id' => $this->chargeId,
              'error' => $exception->getMessage(),
          ]);
      }
  }
  ```

### 10. Form Requests
- [ ] Criar `app/Http/Requests/Charge/StoreChargeRequest.php`
  ```php
  class StoreChargeRequest extends FormRequest
  {
      public function authorize(): bool
      {
          return true;
      }

      public function rules(): array
      {
          return [
              'customer_id' => ['required', 'integer', 'exists:customers,id'],
              'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
              'description' => ['required', 'string', 'min:3', 'max:500'],
              'payment_method' => ['required', 'string', 'in:credit_card,debit_card,boleto,pix'],
              'due_date' => ['required', 'date', 'after_or_equal:today'],
              'payment_gateway_id' => ['nullable', 'integer', 'exists:payment_gateways,id'],
              'metadata' => ['nullable', 'array'],
          ];
      }

      public function messages(): array
      {
          return [
              'customer_id.required' => 'Customer is required',
              'customer_id.exists' => 'Customer not found',
              'amount.required' => 'Amount is required',
              'amount.min' => 'Amount must be at least 0.01',
              'description.required' => 'Description is required',
              'description.min' => 'Description must be at least 3 characters',
              'payment_method.required' => 'Payment method is required',
              'payment_method.in' => 'Invalid payment method',
              'due_date.required' => 'Due date is required',
              'due_date.after_or_equal' => 'Due date must be today or in the future',
          ];
      }

      protected function failedValidation(Validator $validator)
      {
          throw new HttpResponseException(
              response()->json([
                  'message' => 'Validation failed',
                  'errors' => $validator->errors()
              ], 422)
          );
      }
  }
  ```

- [ ] Criar `app/Http/Requests/Charge/UpdateChargeRequest.php`
  ```php
  class UpdateChargeRequest extends FormRequest
  {
      public function authorize(): bool
      {
          return true;
      }

      public function rules(): array
      {
          return [
              'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:999999.99'],
              'description' => ['sometimes', 'string', 'min:3', 'max:500'],
              'due_date' => ['sometimes', 'date', 'after_or_equal:today'],
              'metadata' => ['nullable', 'array'],
          ];
      }

      public function messages(): array
      {
          return [
              'amount.min' => 'Amount must be at least 0.01',
              'description.min' => 'Description must be at least 3 characters',
              'due_date.after_or_equal' => 'Due date must be today or in the future',
          ];
      }
  }
  ```

- [ ] Criar `app/Http/Requests/Charge/ListChargesRequest.php`
  ```php
  class ListChargesRequest extends FormRequest
  {
      public function authorize(): bool
      {
          return true;
      }

      public function rules(): array
      {
          return [
              'status' => ['nullable', 'array'],
              'status.*' => ['string', 'in:pending,paid,cancelled,refunded,expired,failed'],
              'date_from' => ['nullable', 'date'],
              'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
              'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
              'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
          ];
      }
  }
  ```

- [ ] Criar `app/Http/Requests/Charge/CancelChargeRequest.php`
  ```php
  class CancelChargeRequest extends FormRequest
  {
      public function authorize(): bool
      {
          return true;
      }

      public function rules(): array
      {
          return [
              'reason' => ['nullable', 'string', 'max:500'],
          ];
      }
  }
  ```

### 11. API Resources
- [ ] Criar `app/Http/Resources/ChargeResource.php`
  ```php
  class ChargeResource extends JsonResource
  {
      public function toArray(Request $request): array
      {
          return [
              'id' => $this->id,
              'customer' => new CustomerResource($this->whenLoaded('customer')),
              'payment_gateway_id' => $this->payment_gateway_id,
              'gateway_charge_id' => $this->gateway_charge_id,
              'amount' => $this->amount,
              'description' => $this->description,
              'payment_method' => $this->payment_method->value,
              'status' => $this->status->value,
              'due_date' => $this->due_date->toDateString(),
              'paid_at' => $this->paid_at?->toIso8601String(),
              'metadata' => $this->metadata,
              'is_paid' => $this->isPaid(),
              'is_overdue' => $this->isOverdue(),
              'can_be_cancelled' => $this->canBeCancelled(),
              'can_be_updated' => $this->canBeUpdated(),
              'created_at' => $this->created_at->toIso8601String(),
              'updated_at' => $this->updated_at->toIso8601String(),
          ];
      }
  }
  ```

- [ ] Criar `app/Http/Resources/ChargeCollection.php`
  ```php
  class ChargeCollection extends ResourceCollection
  {
      public function toArray(Request $request): array
      {
          return [
              'data' => $this->collection,
              'meta' => [
                  'total' => $this->total(),
                  'per_page' => $this->perPage(),
                  'current_page' => $this->currentPage(),
                  'last_page' => $this->lastPage(),
              ],
          ];
      }
  }
  ```

### 12. Controller
- [ ] Criar `app/Http/Controllers/Api/V1/ChargeController.php`
  ```php
  class ChargeController extends Controller
  {
      /**
       * Lista todas as cobranças
       */
      public function index(
          ListChargesRequest $request,
          GetChargesWithFiltersQuery $query
      ): AnonymousResourceCollection {
          $charges = $query->execute(
              statuses: $request->input('status'),
              dateFrom: $request->input('date_from'),
              dateTo: $request->input('date_to'),
              customerId: $request->input('customer_id'),
              perPage: $request->input('per_page', 15)
          );

          return ChargeResource::collection($charges);
      }

      /**
       * Exibe cobrança específica
       */
      public function show(int $id, GetChargeByIdQuery $query): ChargeResource
      {
          $charge = $query->execute($id);

          if (!$charge) {
              throw new ChargeNotFoundException($id);
          }

          return new ChargeResource($charge);
      }

      /**
       * Cria nova cobrança
       */
      public function store(
          StoreChargeRequest $request,
          CreateChargeAction $action
      ): JsonResponse {
          $dto = CreateChargeDTO::fromRequest($request->validated());

          $charge = $action->execute($dto);

          return (new ChargeResource($charge))
              ->response()
              ->setStatusCode(201);
      }

      /**
       * Atualiza cobrança
       */
      public function update(
          int $id,
          UpdateChargeRequest $request,
          UpdateChargeAction $action
      ): ChargeResource {
          $dto = UpdateChargeDTO::fromRequest($request->validated());

          $charge = $action->execute($id, $dto);

          return new ChargeResource($charge);
      }

      /**
       * Remove cobrança (soft delete)
       */
      public function destroy(
          int $id,
          CancelChargeAction $action
      ): JsonResponse {
          $action->execute($id, 'Deleted via API');

          return response()->json(null, 204);
      }

      /**
       * Cancela cobrança
       */
      public function cancel(
          int $id,
          CancelChargeRequest $request,
          CancelChargeAction $action
      ): ChargeResource {
          $charge = $action->execute($id, $request->input('reason', ''));

          return new ChargeResource($charge);
      }

      /**
       * Sincroniza status com gateway
       */
      public function syncWithGateway(int $id): JsonResponse
      {
          $charge = Charge::find($id);

          if (!$charge) {
              throw new ChargeNotFoundException($id);
          }

          SyncChargeStatusJob::dispatch($charge->id);

          return response()->json([
              'message' => 'Charge sync queued successfully',
              'charge_id' => $charge->id,
          ], 202);
      }
  }
  ```

- [ ] Adicionar método em `app/Http/Controllers/Api/V1/CustomerController.php`
  ```php
  /**
   * Lista cobranças do cliente
   */
  public function charges(
      int $id,
      ListChargesRequest $request,
      GetCustomerChargesQuery $query
  ): AnonymousResourceCollection {
      // Verificar se customer existe
      $customer = Customer::find($id);
      if (!$customer) {
          throw new CustomerNotFoundException($id);
      }

      $charges = $query->execute(
          customerId: $id,
          perPage: $request->input('per_page', 15)
      );

      return ChargeResource::collection($charges);
  }
  ```

### 13. Routes
- [ ] Adicionar rotas em `routes/api.php`
  ```php
  Route::prefix('v1')->group(function () {
      // Charges CRUD
      Route::apiResource('charges', ChargeController::class);

      // Charges - Ações adicionais
      Route::post('charges/{id}/cancel', [ChargeController::class, 'cancel']);
      Route::post('charges/{id}/sync', [ChargeController::class, 'syncWithGateway']);

      // Cobranças de um cliente específico
      Route::get('customers/{id}/charges', [CustomerController::class, 'charges']);
  });
  ```

### 14. Exception Handler
- [ ] Registrar exceptions em `app/Exceptions/Handler.php`
  ```php
  public function register(): void
  {
      $this->renderable(function (ChargeException $e) {
          return $e->render();
      });
  }
  ```

### 15. Testes
- [ ] Criar `tests/Feature/Api/V1/ChargeTest.php`
  ```php
  class ChargeTest extends TestCase
  {
      use RefreshDatabase;

      /** @test */
      public function it_can_create_a_charge(): void
      {
          $customer = Customer::factory()->create();

          $response = $this->postJson('/api/v1/charges', [
              'customer_id' => $customer->id,
              'amount' => 150.50,
              'description' => 'Test Charge',
              'payment_method' => 'pix',
              'due_date' => now()->addDays(7)->toDateString(),
          ]);

          $response->assertCreated();
          $response->assertJsonStructure(['data' => ['id', 'amount', 'status']]);
          $this->assertDatabaseHas('charges', ['description' => 'Test Charge']);
      }

      /** @test */
      public function it_validates_customer_exists(): void
      {
          $response = $this->postJson('/api/v1/charges', [
              'customer_id' => 999,
              'amount' => 150.50,
              'description' => 'Test Charge',
              'payment_method' => 'pix',
              'due_date' => now()->addDays(7)->toDateString(),
          ]);

          $response->assertStatus(404);
          $response->assertJson(['error' => 'customer_not_found']);
      }

      /** @test */
      public function it_validates_amount_positive(): void
      {
          $customer = Customer::factory()->create();

          $response = $this->postJson('/api/v1/charges', [
              'customer_id' => $customer->id,
              'amount' => -10,
              'description' => 'Test Charge',
              'payment_method' => 'pix',
              'due_date' => now()->addDays(7)->toDateString(),
          ]);

          $response->assertStatus(422);
      }

      /** @test */
      public function it_cannot_cancel_paid_charge(): void
      {
          $charge = Charge::factory()->create(['status' => ChargeStatus::PAID]);

          $response = $this->postJson("/api/v1/charges/{$charge->id}/cancel", [
              'reason' => 'Test cancellation',
          ]);

          $response->assertStatus(422);
          $response->assertJson(['error' => 'charge_cannot_be_cancelled']);
      }

      /** @test */
      public function it_can_cancel_pending_charge(): void
      {
          $charge = Charge::factory()->create(['status' => ChargeStatus::PENDING]);

          $response = $this->postJson("/api/v1/charges/{$charge->id}/cancel", [
              'reason' => 'Test cancellation',
          ]);

          $response->assertOk();
          $this->assertDatabaseHas('charges', [
              'id' => $charge->id,
              'status' => ChargeStatus::CANCELLED->value,
          ]);
      }

      /** @test */
      public function it_returns_404_for_nonexistent_charge(): void
      {
          $response = $this->getJson('/api/v1/charges/999');

          $response->assertNotFound();
          $response->assertJson(['error' => 'charge_not_found']);
      }

      /** @test */
      public function it_can_filter_charges_by_status(): void
      {
          $customer = Customer::factory()->create();
          Charge::factory()->create(['customer_id' => $customer->id, 'status' => ChargeStatus::PENDING]);
          Charge::factory()->create(['customer_id' => $customer->id, 'status' => ChargeStatus::PAID]);

          $response = $this->getJson('/api/v1/charges?status[]=pending');

          $response->assertOk();
          $response->assertJsonCount(1, 'data');
      }

      /** @test */
      public function it_can_filter_charges_by_date_range(): void
      {
          $customer = Customer::factory()->create();
          $date1 = now()->addDays(5);
          $date2 = now()->addDays(15);

          Charge::factory()->create(['customer_id' => $customer->id, 'due_date' => $date1]);
          Charge::factory()->create(['customer_id' => $customer->id, 'due_date' => $date2]);

          $response = $this->getJson("/api/v1/charges?date_from={$date1->toDateString()}&date_to={$date1->toDateString()}");

          $response->assertOk();
          $response->assertJsonCount(1, 'data');
      }
  }
  ```

- [ ] Criar `tests/Unit/Actions/Charge/CreateChargeActionTest.php`
  ```php
  class CreateChargeActionTest extends TestCase
  {
      use RefreshDatabase;

      private CreateChargeAction $action;

      protected function setUp(): void
      {
          parent::setUp();
          $this->action = new CreateChargeAction();
      }

      /** @test */
      public function it_creates_charge_successfully(): void
      {
          $customer = Customer::factory()->create();

          $dto = new CreateChargeDTO(
              customerId: $customer->id,
              amount: 150.50,
              description: 'Test Charge',
              paymentMethod: PaymentMethod::PIX,
              dueDate: now()->addDays(7)->toDateString()
          );

          $charge = $this->action->execute($dto);

          $this->assertInstanceOf(Charge::class, $charge);
          $this->assertEquals(150.50, $charge->amount);
          $this->assertDatabaseHas('charges', ['description' => 'Test Charge']);
      }

      /** @test */
      public function it_throws_exception_for_nonexistent_customer(): void
      {
          $dto = new CreateChargeDTO(
              customerId: 999,
              amount: 150.50,
              description: 'Test Charge',
              paymentMethod: PaymentMethod::PIX,
              dueDate: now()->addDays(7)->toDateString()
          );

          $this->expectException(CustomerNotFoundException::class);

          $this->action->execute($dto);
      }

      /** @test */
      public function it_throws_exception_for_negative_amount(): void
      {
          $customer = Customer::factory()->create();

          $dto = new CreateChargeDTO(
              customerId: $customer->id,
              amount: -10,
              description: 'Test Charge',
              paymentMethod: PaymentMethod::PIX,
              dueDate: now()->addDays(7)->toDateString()
          );

          $this->expectException(InvalidChargeDataException::class);

          $this->action->execute($dto);
      }

      /** @test */
      public function it_throws_exception_for_past_due_date(): void
      {
          $customer = Customer::factory()->create();

          $dto = new CreateChargeDTO(
              customerId: $customer->id,
              amount: 150.50,
              description: 'Test Charge',
              paymentMethod: PaymentMethod::PIX,
              dueDate: now()->subDays(1)->toDateString()
          );

          $this->expectException(InvalidChargeDataException::class);

          $this->action->execute($dto);
      }
  }
  ```

- [ ] Criar `tests/Unit/Actions/Charge/CancelChargeActionTest.php`
  ```php
  class CancelChargeActionTest extends TestCase
  {
      use RefreshDatabase;

      private CancelChargeAction $action;

      protected function setUp(): void
      {
          parent::setUp();
          $this->action = new CancelChargeAction();
      }

      /** @test */
      public function it_cancels_pending_charge_successfully(): void
      {
          $charge = Charge::factory()->create(['status' => ChargeStatus::PENDING]);

          $result = $this->action->execute($charge->id, 'Test reason');

          $this->assertEquals(ChargeStatus::CANCELLED, $result->status);
          $this->assertArrayHasKey('cancellation_reason', $result->metadata);
      }

      /** @test */
      public function it_throws_exception_when_cancelling_paid_charge(): void
      {
          $charge = Charge::factory()->create(['status' => ChargeStatus::PAID]);

          $this->expectException(ChargeCannotBeCancelledException::class);

          $this->action->execute($charge->id, 'Test reason');
      }

      /** @test */
      public function it_throws_exception_for_nonexistent_charge(): void
      {
          $this->expectException(ChargeNotFoundException::class);

          $this->action->execute(999, 'Test reason');
      }
  }
  ```

- [ ] Criar `tests/Unit/Queries/Charge/GetChargeByIdQueryTest.php`
- [ ] Criar `tests/Unit/DTOs/Charge/CreateChargeDTOTest.php`
- [ ] Criar `tests/Unit/Models/ChargeTest.php` (testar scopes e accessors)

---

## Checklist de Qualidade

### Arquitetura
- [ ] Actions retornam Models (não JsonResponse)
- [ ] Queries usam Eloquent diretamente
- [ ] Custom Exceptions com render()
- [ ] DTOs readonly criados
- [ ] Sem Repository (não há múltiplas implementações)
- [ ] SOLID principles seguidos

### Código
- [ ] Type hints completos
- [ ] Sem else desnecessário
- [ ] Nomes descritivos
- [ ] Métodos pequenos (<20 linhas)
- [ ] Actions sem HTTP concerns

### Validação
- [ ] FormRequest para validação HTTP (422)
- [ ] Custom Exceptions para erros de negócio (404, 422)
- [ ] Regras de negócio nas Actions
- [ ] Cobrança paga não pode ser cancelada
- [ ] Cobrança paga não pode ser editada

### Performance
- [ ] Eager loading implementado: `->with(['customer', 'paymentGateway'])`
- [ ] Índices compostos criados
- [ ] Paginação obrigatória

### Testes
- [ ] Feature tests (Controllers)
- [ ] Unit tests (Actions/Queries)
- [ ] Cobertura >80%

---

## Critérios de Aceitação

✅ **Funcionalidade**
- CRUD completo funcionando
- Validações impedindo cancelamento de cobranças pagas
- Validações impedindo edição de cobranças pagas
- Filtros por status e data funcionando
- Paginação funcionando

✅ **Arquitetura**
- Actions retornam Charge (não JsonResponse)
- Exceptions controlam status codes (404, 422)
- Controller define status de sucesso (200, 201, 204)
- Queries usam Eloquent direto (sem Repository desnecessário)

✅ **Qualidade**
- Todos os testes passando
- Type hints completos
- Actions reutilizáveis
- Events disparados corretamente

✅ **Regras de Negócio**
- Cobrança paga não pode ser cancelada
- Cobrança paga não pode ser editada
- Due date >= hoje
- Amount > 0
- Customer deve existir

✅ **API**
- Status codes corretos
- Mensagens de erro claras
- Paginação nos listings
- Filtros funcionando

---

## Exemplos de Uso da API

```bash
# Criar cobrança
POST /api/v1/charges
{
  "customer_id": 1,
  "amount": 150.50,
  "description": "Mensalidade Outubro 2024",
  "payment_method": "pix",
  "due_date": "2024-10-30",
  "metadata": {
    "reference": "REF-001"
  }
}

# Response 201
{
  "data": {
    "id": 1,
    "customer": { ... },
    "amount": "150.50",
    "description": "Mensalidade Outubro 2024",
    "payment_method": "pix",
    "status": "pending",
    "due_date": "2024-10-30",
    "is_paid": false,
    "is_overdue": false,
    "can_be_cancelled": true,
    ...
  }
}

# Listar cobranças com filtros
GET /api/v1/charges?status[]=pending&status[]=paid&date_from=2024-10-01&date_to=2024-10-31&per_page=20

# Listar cobranças de um cliente
GET /api/v1/customers/1/charges?status[]=pending

# Exibir cobrança específica
GET /api/v1/charges/1

# Atualizar cobrança
PUT /api/v1/charges/1
{
  "amount": 200.00,
  "description": "Mensalidade Outubro 2024 - Atualizado"
}

# Cancelar cobrança
POST /api/v1/charges/1/cancel
{
  "reason": "Cancelado a pedido do cliente"
}

# Response 200
{
  "data": {
    "id": 1,
    "status": "cancelled",
    "metadata": {
      "cancellation_reason": "Cancelado a pedido do cliente",
      "cancelled_at": "2024-10-15T10:30:00.000000Z"
    },
    ...
  }
}

# Tentar cancelar cobrança paga - Response 422
{
  "message": "Charge cannot be cancelled: Charge already paid",
  "error": "charge_cannot_be_cancelled"
}

# Not Found - Response 404
{
  "message": "Charge #999 not found",
  "error": "charge_not_found"
}

# Sincronizar com gateway
POST /api/v1/charges/1/sync

# Response 202
{
  "message": "Charge sync queued successfully",
  "charge_id": 1
}
```

---

## Notas Importantes

⚠️ **Actions vs Services**
- Actions retornam Models (Charge)
- Actions NUNCA retornam JsonResponse
- Actions lançam Custom Exceptions
- Actions são reutilizáveis em Jobs/Commands

⚠️ **Queries vs Repositories**
- Use Eloquent DIRETO (Charge::find())
- NÃO criar Repository para CRUD simples
- Queries são classes específicas
- Eager loading explícito: `->with(['customer', 'paymentGateway'])`

⚠️ **Exceptions**
- Controlam status codes (404, 422, etc)
- Método render() retorna JsonResponse
- Controller não sabe de status de erro
- Registrar no Exception Handler

⚠️ **Regras de Negócio Críticas**
- Cobrança com status PAID não pode ser cancelada
- Cobrança com status PAID não pode ser editada
- Cobrança com status CANCELLED não pode ser cancelada novamente
- Cobrança com status REFUNDED não pode ser cancelada
- Apenas cobrança PAID pode ser reembolsada (REFUNDED)
- Due date deve ser hoje ou no futuro
- Amount deve ser maior que 0
- Customer deve existir

⚠️ **Transactions**
- Usar DB::transaction() em todas as Actions
- Disparar events DENTRO da transaction
- Usar fresh() após updates

⚠️ **Events & Listeners**
- ChargeCreated -> SendChargeNotification (queued)
- ChargePaid -> SendPaymentConfirmation (queued)
- Listeners implementam ShouldQueue
- Registrar no EventServiceProvider

⚠️ **Jobs**
- SyncChargeStatusJob para sincronização com gateway
- Retry logic: 3 tentativas
- Backoff: 1min, 5min, 15min
- Implementar método failed()

📚 **Referências**
- Prompt.MD: Action Pattern, Query Pattern, Custom Exceptions
- SOLID Principles
- Object Calisthenics
- Laravel Events & Listeners
- Laravel Jobs & Queues
