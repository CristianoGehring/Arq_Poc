# 02 - Charge Domain (Domínio de Cobranças)

## Objetivo
Implementar o domínio completo de gerenciamento de cobranças, incluindo integração com gateways de pagamento.

## Prioridade
🔴 ALTA - Domínio core do sistema

## Dependências
- Task 00 (Setup Inicial)
- Task 01 (Customer Domain)

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

### 2. Exceptions
- [ ] Criar `app/Exceptions/ChargeException.php`
- [ ] Criar `app/Exceptions/ChargeNotFoundException.php`
- [ ] Criar `app/Exceptions/ChargeCannotBeCancelledException.php`
- [ ] Criar `app/Exceptions/ChargeAlreadyPaidException.php`

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
          public ?array $metadata = null
      ) {}

      public static function fromRequest(array $data): self;
      public function toArray(): array;
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

      public static function fromRequest(array $data): self;
      public function toArray(): array;
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
  - Casts: amount -> decimal, status -> ChargeStatus, payment_method -> PaymentMethod
  - Casts: metadata -> array, paid_at -> datetime, due_date -> date
  - Relacionamento `belongsTo(Customer::class)`
  - Relacionamento `belongsTo(PaymentGateway::class)`
  - Scopes: `scopePaid()`, `scopePending()`, `scopeDueToday()`, `scopeOverdue()`
  - Acessor `isPaid()`: bool
  - Acessor `isOverdue()`: bool
  - Acessor `canBeCancelled()`: bool

### 5. Repository Pattern
- [ ] Criar `app/Repositories/Contracts/ChargeRepositoryInterface.php`
  ```php
  interface ChargeRepositoryInterface
  {
      public function find(int $id): ?Charge;
      public function findByGatewayChargeId(string $gatewayChargeId): ?Charge;
      public function create(array $data): Charge;
      public function update(int $id, array $data): Charge;
      public function delete(int $id): bool;
      public function findByCustomer(int $customerId, int $perPage = 15): LengthAwarePaginator;
      public function findByCustomerWithFilters(
          int $customerId,
          ?array $statuses = null,
          ?string $dateFrom = null,
          ?string $dateTo = null,
          int $perPage = 15
      ): LengthAwarePaginator;
      public function findPendingCharges(int $perPage = 15): LengthAwarePaginator;
      public function findOverdueCharges(int $perPage = 15): LengthAwarePaginator;
  }
  ```

- [ ] Criar `app/Repositories/Eloquent/ChargeRepository.php`
  - Implementar todos os métodos
  - Usar Eager Loading: `with(['customer', 'paymentGateway'])`
  - Queries otimizadas com when() para filtros opcionais

- [ ] Registrar binding no `AppServiceProvider`

### 6. Services (CQRS Leve)
- [ ] Criar `app/Services/Charge/ChargeService.php` (Commands)
  ```php
  public function create(CreateChargeDTO $dto): Charge;
  public function update(int $id, UpdateChargeDTO $dto): Charge;
  public function cancel(int $id, string $reason): Charge;
  public function markAsPaid(int $id, ?string $paidAt = null): Charge;
  public function refund(int $id, string $reason): Charge;
  public function updateStatus(int $id, ChargeStatus $status): Charge;
  public function syncWithGateway(int $id): Charge;
  ```

- [ ] Criar `app/Services/Charge/ChargeQueryService.php` (Queries)
  ```php
  public function findById(int $id): ?Charge;
  public function getByCustomer(int $customerId, int $perPage = 15): LengthAwarePaginator;
  public function getByCustomerWithFilters(
      int $customerId,
      ?array $statuses = null,
      ?string $dateFrom = null,
      ?string $dateTo = null,
      int $perPage = 15
  ): LengthAwarePaginator;
  public function getPending(int $perPage = 15): LengthAwarePaginator;
  public function getOverdue(int $perPage = 15): LengthAwarePaginator;
  public function getDueToday(int $perPage = 15): LengthAwarePaginator;
  ```

### 7. Events
- [ ] Criar `app/Events/ChargeCreated.php`
- [ ] Criar `app/Events/ChargeUpdated.php`
- [ ] Criar `app/Events/ChargePaid.php`
- [ ] Criar `app/Events/ChargeCancelled.php`
- [ ] Criar `app/Events/ChargeRefunded.php`

### 8. Listeners
- [ ] Criar `app/Listeners/SendChargeNotification.php`
  - Implements ShouldQueue
  - Enviar email/SMS quando cobrança criada

- [ ] Criar `app/Listeners/SendPaymentConfirmation.php`
  - Implements ShouldQueue
  - Enviar confirmação quando cobrança paga

### 9. Jobs
- [ ] Criar `app/Jobs/SyncChargeStatus.php`
  - Job para sincronizar status com gateway
  - Implements ShouldQueue
  - Retry logic: 3 tentativas

### 10. Form Requests
- [ ] Criar `app/Http/Requests/Charge/StoreChargeRequest.php`
  ```php
  rules: [
      'customer_id' => ['required', 'integer', 'exists:customers,id'],
      'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
      'description' => ['required', 'string', 'min:3', 'max:500'],
      'payment_method' => ['required', 'string', 'in:credit_card,debit_card,boleto,pix'],
      'due_date' => ['required', 'date', 'after_or_equal:today'],
      'metadata' => ['nullable', 'array'],
  ]
  ```

- [ ] Criar `app/Http/Requests/Charge/UpdateChargeRequest.php`

- [ ] Criar `app/Http/Requests/Charge/ListChargesRequest.php`
  ```php
  rules: [
      'status' => ['nullable', 'array'],
      'status.*' => ['string', 'in:pending,paid,cancelled,refunded,expired,failed'],
      'date_from' => ['nullable', 'date'],
      'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
      'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
  ]
  ```

### 11. API Resources
- [ ] Criar `app/Http/Resources/ChargeResource.php`
  ```php
  return [
      'id' => $this->id,
      'customer' => new CustomerResource($this->whenLoaded('customer')),
      'amount' => $this->amount,
      'description' => $this->description,
      'payment_method' => $this->payment_method->value,
      'status' => $this->status->value,
      'due_date' => $this->due_date->toDateString(),
      'paid_at' => $this->paid_at?->toIso8601String(),
      'gateway_charge_id' => $this->gateway_charge_id,
      'metadata' => $this->metadata,
      'is_overdue' => $this->isOverdue(),
      'can_be_cancelled' => $this->canBeCancelled(),
      'created_at' => $this->created_at->toIso8601String(),
      'updated_at' => $this->updated_at->toIso8601String(),
  ];
  ```

- [ ] Criar `app/Http/Resources/ChargeCollection.php`

### 12. Controllers
- [ ] Criar `app/Http/Controllers/Api/V1/ChargeController.php`
  - `index()`: Listar todas as cobranças (com filtros)
  - `store(StoreChargeRequest)`: Criar cobrança
  - `show(int $id)`: Mostrar cobrança específica
  - `update(UpdateChargeRequest, int $id)`: Atualizar cobrança
  - `destroy(int $id)`: Cancelar cobrança

- [ ] Adicionar método em `CustomerController.php`
  - `charges(int $customerId, ListChargesRequest)`: Listar cobranças do cliente

### 13. Routes
- [ ] Adicionar rotas em `routes/api.php`
  ```php
  Route::prefix('v1')->group(function () {
      Route::apiResource('charges', ChargeController::class);
      Route::get('customers/{customer}/charges', [CustomerController::class, 'charges']);
      Route::post('charges/{charge}/cancel', [ChargeController::class, 'cancel']);
      Route::post('charges/{charge}/sync', [ChargeController::class, 'syncWithGateway']);
  });
  ```

### 14. Testes
- [ ] Criar `tests/Feature/Api/V1/ChargeTest.php`
  - `test_can_list_charges()`
  - `test_can_create_charge()`
  - `test_can_show_charge()`
  - `test_can_update_charge()`
  - `test_can_cancel_charge()`
  - `test_validates_required_fields()`
  - `test_validates_customer_exists()`
  - `test_validates_amount_positive()`
  - `test_cannot_cancel_paid_charge()`
  - `test_can_filter_charges_by_status()`
  - `test_can_filter_charges_by_date_range()`

- [ ] Criar `tests/Feature/Api/V1/CustomerChargesTest.php`
  - `test_can_list_customer_charges()`
  - `test_can_filter_customer_charges()`
  - `test_returns_404_for_nonexistent_customer()`

- [ ] Criar `tests/Unit/Services/Charge/ChargeServiceTest.php`
- [ ] Criar `tests/Unit/Models/ChargeTest.php`
  - Testar scopes
  - Testar acessors (isPaid, isOverdue, canBeCancelled)

---

## Checklist de Qualidade

### Arquitetura
- [ ] SOLID principles seguidos
- [ ] Repository Pattern implementado
- [ ] DTOs readonly criados
- [ ] Services separados (Command/Query)
- [ ] Event-Driven Architecture para notificações

### Código
- [ ] Type hints completos
- [ ] Sem else desnecessário
- [ ] Nomes descritivos
- [ ] Métodos pequenos (<20 linhas)
- [ ] Object Calisthenics aplicado

### Validação
- [ ] FormRequest para validação HTTP
- [ ] Validação de domínio no DTO
- [ ] Regras de negócio no Service
- [ ] Impedir cancelamento de cobrança paga

### Performance
- [ ] Eager loading (customer, paymentGateway)
- [ ] Índices compostos: (customer_id, status)
- [ ] Queries otimizadas com when()
- [ ] Paginação obrigatória

### Testes
- [ ] Feature tests completos
- [ ] Unit tests para lógica de negócio
- [ ] Cobertura > 80%
- [ ] Testar edge cases

---

## Critérios de Aceitação

✅ **Funcionalidade**
- CRUD completo funcionando
- Filtros por status e data funcionando
- Cancelamento de cobranças funcionando
- Status sendo atualizados corretamente
- Events sendo disparados

✅ **Regras de Negócio**
- Cobrança paga não pode ser cancelada
- Cobrança paga não pode ser editada
- Due date deve ser hoje ou futuro
- Amount deve ser positivo

✅ **API**
- Endpoints retornando JSON correto
- Paginação funcionando
- Filtros funcionando
- Status codes apropriados

✅ **Performance**
- Queries otimizadas
- N+1 evitado
- Índices criados

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

# Listar cobranças com filtros
GET /api/v1/charges?status[]=pending&status[]=paid&date_from=2024-10-01&date_to=2024-10-31

# Listar cobranças de um cliente
GET /api/v1/customers/1/charges?status[]=pending

# Cancelar cobrança
POST /api/v1/charges/1/cancel
{
  "reason": "Cancelado a pedido do cliente"
}

# Sincronizar com gateway
POST /api/v1/charges/1/sync
```

---

## Notas Importantes

⚠️ **Atenção**
- Usar transactions em operações críticas
- Disparar events após mudanças de status
- Validar se cobrança pode ser alterada antes de alterar
- Logar todas as mudanças de status
- Usar Jobs para operações assíncronas

📚 **Referências**
- Prompt.MD: architectural_patterns, event_driven_architecture
- Laravel Events & Listeners
- Laravel Jobs & Queues
