# 01 - Customer Domain (Domínio de Clientes)

## Objetivo
Implementar o domínio completo de gerenciamento de clientes com CRUD, seguindo a arquitetura baseada em Actions/Queries/Exceptions.

## Prioridade
🔴 ALTA - Domínio fundamental do sistema

## Dependências
- Setup Inicial (Task 00)

---

## Ordem de Implementação

### 1. Enums
- [ ] Criar `app/Enums/CustomerStatus.php`
  ```php
  enum CustomerStatus: string
  {
      case ACTIVE = 'active';
      case INACTIVE = 'inactive';
      case BLOCKED = 'blocked';
  }
  ```

### 2. Custom Exceptions
- [ ] Criar `app/Exceptions/CustomerException.php` (Base)
  ```php
  abstract class CustomerException extends Exception
  {
      protected int $statusCode = 400;

      public function getStatusCode(): int
      {
          return $this->statusCode;
      }

      abstract public function render(): JsonResponse;
  }
  ```

- [ ] Criar `app/Exceptions/CustomerNotFoundException.php`
  ```php
  class CustomerNotFoundException extends CustomerException
  {
      protected int $statusCode = 404;

      public function __construct(int $id)
      {
          parent::__construct("Customer #{$id} not found");
      }

      public function render(): JsonResponse
      {
          return response()->json([
              'message' => $this->getMessage(),
              'error' => 'customer_not_found',
          ], $this->statusCode);
      }
  }
  ```

- [ ] Criar `app/Exceptions/CustomerAlreadyExistsException.php`
  ```php
  class CustomerAlreadyExistsException extends CustomerException
  {
      protected int $statusCode = 422;

      public function __construct(string $email)
      {
          parent::__construct("Customer with email '{$email}' already exists");
      }

      public function render(): JsonResponse
      {
          return response()->json([
              'message' => $this->getMessage(),
              'error' => 'customer_already_exists',
              'details' => [
                  'field' => 'email',
                  'issue' => 'duplicate'
              ]
          ], $this->statusCode);
      }
  }
  ```

- [ ] Criar `app/Exceptions/InvalidCustomerDataException.php`
  ```php
  class InvalidCustomerDataException extends CustomerException
  {
      protected int $statusCode = 422;

      public function __construct(string $field, string $reason)
      {
          parent::__construct("Invalid customer data: {$field} - {$reason}");
      }

      public function render(): JsonResponse
      {
          return response()->json([
              'message' => $this->getMessage(),
              'error' => 'invalid_customer_data',
          ], $this->statusCode);
      }
  }
  ```

### 3. DTOs (Data Transfer Objects)
- [ ] Criar `app/DTOs/Customer/CreateCustomerDTO.php`
  ```php
  readonly class CreateCustomerDTO
  {
      public function __construct(
          public string $name,
          public string $email,
          public string $document,
          public ?string $phone = null,
          public ?array $address = null
      ) {}

      public static function fromRequest(array $data): self
      {
          return new self(
              name: $data['name'],
              email: $data['email'],
              document: $data['document'],
              phone: $data['phone'] ?? null,
              address: $data['address'] ?? null
          );
      }

      public function toArray(): array
      {
          return [
              'name' => $this->name,
              'email' => $this->email,
              'document' => $this->document,
              'phone' => $this->phone,
              'address' => $this->address,
              'status' => CustomerStatus::ACTIVE,
          ];
      }
  }
  ```

- [ ] Criar `app/DTOs/Customer/UpdateCustomerDTO.php`
  ```php
  readonly class UpdateCustomerDTO
  {
      public function __construct(
          public ?string $name = null,
          public ?string $email = null,
          public ?string $phone = null,
          public ?array $address = null,
          public ?CustomerStatus $status = null
      ) {}

      public static function fromRequest(array $data): self
      {
          return new self(
              name: $data['name'] ?? null,
              email: $data['email'] ?? null,
              phone: $data['phone'] ?? null,
              address: $data['address'] ?? null,
              status: isset($data['status'])
                  ? CustomerStatus::from($data['status'])
                  : null
          );
      }

      public function toArray(): array
      {
          return array_filter([
              'name' => $this->name,
              'email' => $this->email,
              'phone' => $this->phone,
              'address' => $this->address,
              'status' => $this->status?->value,
          ], fn($value) => $value !== null);
      }
  }
  ```

### 4. Migration & Model
- [ ] Criar migration `create_customers_table`
  ```php
  Schema::create('customers', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->string('email')->unique();
      $table->string('document')->unique(); // CPF/CNPJ
      $table->string('phone')->nullable();
      $table->json('address')->nullable();
      $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
      $table->timestamps();
      $table->softDeletes();

      // Índices para performance
      $table->index('email');
      $table->index('document');
      $table->index('status');
      $table->index('created_at');
  });
  ```

- [ ] Criar `app/Models/Customer.php`
  ```php
  class Customer extends Model
  {
      use HasFactory, SoftDeletes;

      protected $fillable = [
          'name',
          'email',
          'document',
          'phone',
          'address',
          'status',
      ];

      protected $casts = [
          'address' => 'array',
          'status' => CustomerStatus::class,
          'created_at' => 'datetime',
          'updated_at' => 'datetime',
          'deleted_at' => 'datetime',
      ];

      // Relationships
      public function charges(): HasMany
      {
          return $this->hasMany(Charge::class);
      }

      // Scopes
      public function scopeActive(Builder $query): void
      {
          $query->where('status', CustomerStatus::ACTIVE);
      }

      public function scopeByDocument(Builder $query, string $document): void
      {
          $query->where('document', $document);
      }

      // Accessors
      public function isActive(): bool
      {
          return $this->status === CustomerStatus::ACTIVE;
      }
  }
  ```

### 5. Actions (Write Operations)
- [ ] Criar `app/Actions/Customer/CreateCustomerAction.php`
  ```php
  class CreateCustomerAction
  {
      /**
       * Cria um novo cliente
       *
       * @throws CustomerAlreadyExistsException
       */
      public function execute(CreateCustomerDTO $dto): Customer
      {
          // Validação de regra de negócio
          if (Customer::where('email', $dto->email)->exists()) {
              throw new CustomerAlreadyExistsException($dto->email);
          }

          if (Customer::where('document', $dto->document)->exists()) {
              throw new InvalidCustomerDataException(
                  'document',
                  'Document already exists'
              );
          }

          return DB::transaction(function () use ($dto) {
              $customer = Customer::create($dto->toArray());

              event(new CustomerCreated($customer));

              return $customer;
          });
      }
  }
  ```

- [ ] Criar `app/Actions/Customer/UpdateCustomerAction.php`
  ```php
  class UpdateCustomerAction
  {
      /**
       * Atualiza cliente existente
       *
       * @throws CustomerNotFoundException
       * @throws CustomerAlreadyExistsException
       */
      public function execute(int $id, UpdateCustomerDTO $dto): Customer
      {
          $customer = Customer::find($id);

          if (!$customer) {
              throw new CustomerNotFoundException($id);
          }

          // Validar email único (se estiver sendo alterado)
          if ($dto->email && $dto->email !== $customer->email) {
              if (Customer::where('email', $dto->email)->where('id', '!=', $id)->exists()) {
                  throw new CustomerAlreadyExistsException($dto->email);
              }
          }

          return DB::transaction(function () use ($customer, $dto) {
              $customer->update($dto->toArray());
              $customer->refresh();

              event(new CustomerUpdated($customer));

              return $customer;
          });
      }
  }
  ```

- [ ] Criar `app/Actions/Customer/DeleteCustomerAction.php`
  ```php
  class DeleteCustomerAction
  {
      /**
       * Remove cliente (soft delete)
       *
       * @throws CustomerNotFoundException
       */
      public function execute(int $id): bool
      {
          $customer = Customer::find($id);

          if (!$customer) {
              throw new CustomerNotFoundException($id);
          }

          return DB::transaction(function () use ($customer) {
              $deleted = $customer->delete();

              if ($deleted) {
                  event(new CustomerDeleted($customer));
              }

              return $deleted;
          });
      }
  }
  ```

- [ ] Criar `app/Actions/Customer/ActivateCustomerAction.php`
  ```php
  class ActivateCustomerAction
  {
      /**
       * Ativa cliente
       *
       * @throws CustomerNotFoundException
       */
      public function execute(int $id): Customer
      {
          $customer = Customer::find($id);

          if (!$customer) {
              throw new CustomerNotFoundException($id);
          }

          $customer->status = CustomerStatus::ACTIVE;
          $customer->save();

          return $customer;
      }
  }
  ```

- [ ] Criar `app/Actions/Customer/DeactivateCustomerAction.php`
- [ ] Criar `app/Actions/Customer/BlockCustomerAction.php`

### 6. Queries (Read Operations)
- [ ] Criar `app/Queries/Customer/GetCustomerByIdQuery.php`
  ```php
  class GetCustomerByIdQuery
  {
      /**
       * Busca cliente por ID
       */
      public function execute(int $id): ?Customer
      {
          return Customer::with(['charges' => fn($q) => $q->latest()->limit(5)])
              ->find($id);
      }
  }
  ```

- [ ] Criar `app/Queries/Customer/GetAllCustomersQuery.php`
  ```php
  class GetAllCustomersQuery
  {
      /**
       * Lista todos os clientes paginados
       */
      public function execute(int $perPage = 15): LengthAwarePaginator
      {
          return Customer::with(['charges' => fn($q) => $q->latest()->limit(5)])
              ->latest()
              ->paginate($perPage);
      }
  }
  ```

- [ ] Criar `app/Queries/Customer/GetActiveCustomersQuery.php`
  ```php
  class GetActiveCustomersQuery
  {
      /**
       * Lista apenas clientes ativos
       */
      public function execute(int $perPage = 15): LengthAwarePaginator
      {
          return Customer::active()
              ->with(['charges' => fn($q) => $q->latest()->limit(5)])
              ->latest()
              ->paginate($perPage);
      }
  }
  ```

- [ ] Criar `app/Queries/Customer/SearchCustomersByNameQuery.php`
  ```php
  class SearchCustomersByNameQuery
  {
      /**
       * Busca clientes por nome
       */
      public function execute(string $name, int $perPage = 15): LengthAwarePaginator
      {
          return Customer::where('name', 'like', "%{$name}%")
              ->latest()
              ->paginate($perPage);
      }
  }
  ```

- [ ] Criar `app/Queries/Customer/SearchCustomersByDocumentQuery.php`
  ```php
  class SearchCustomersByDocumentQuery
  {
      /**
       * Busca cliente por documento
       */
      public function execute(string $document): ?Customer
      {
          return Customer::where('document', $document)->first();
      }
  }
  ```

### 7. Events
- [ ] Criar `app/Events/CustomerCreated.php`
  ```php
  class CustomerCreated
  {
      public function __construct(
          public readonly Customer $customer
      ) {}
  }
  ```

- [ ] Criar `app/Events/CustomerUpdated.php`
- [ ] Criar `app/Events/CustomerDeleted.php`

### 8. Form Requests
- [ ] Criar `app/Http/Requests/Customer/StoreCustomerRequest.php`
  ```php
  class StoreCustomerRequest extends FormRequest
  {
      public function authorize(): bool
      {
          return true;
      }

      public function rules(): array
      {
          return [
              'name' => ['required', 'string', 'min:3', 'max:255'],
              'email' => ['required', 'email', 'max:255'],
              'document' => ['required', 'string', 'size:11'], // CPF
              'phone' => ['nullable', 'string', 'min:10', 'max:15'],
              'address' => ['nullable', 'array'],
              'address.street' => ['required_with:address', 'string'],
              'address.number' => ['required_with:address', 'string'],
              'address.city' => ['required_with:address', 'string'],
              'address.state' => ['required_with:address', 'string', 'size:2'],
              'address.zip_code' => ['required_with:address', 'string'],
          ];
      }

      public function messages(): array
      {
          return [
              'name.required' => 'Customer name is required',
              'name.min' => 'Customer name must be at least 3 characters',
              'email.required' => 'Email is required',
              'email.email' => 'Email must be a valid email address',
              'document.required' => 'Document (CPF) is required',
              'document.size' => 'Document must be exactly 11 digits',
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

- [ ] Criar `app/Http/Requests/Customer/UpdateCustomerRequest.php`
  ```php
  class UpdateCustomerRequest extends FormRequest
  {
      public function rules(): array
      {
          $customerId = $this->route('customer');

          return [
              'name' => ['sometimes', 'string', 'min:3', 'max:255'],
              'email' => ['sometimes', 'email', 'max:255'],
              'phone' => ['nullable', 'string', 'min:10', 'max:15'],
              'address' => ['nullable', 'array'],
              'status' => ['sometimes', 'string', 'in:active,inactive,blocked'],
          ];
      }
  }
  ```

### 9. API Resources
- [ ] Criar `app/Http/Resources/CustomerResource.php`
  ```php
  class CustomerResource extends JsonResource
  {
      public function toArray(Request $request): array
      {
          return [
              'id' => $this->id,
              'name' => $this->name,
              'email' => $this->email,
              'document' => $this->document,
              'phone' => $this->phone,
              'address' => $this->address,
              'status' => $this->status->value,
              'is_active' => $this->isActive(),
              'charges_count' => $this->whenLoaded('charges',
                  fn() => $this->charges->count()
              ),
              'recent_charges' => ChargeResource::collection(
                  $this->whenLoaded('charges')
              ),
              'created_at' => $this->created_at->toIso8601String(),
              'updated_at' => $this->updated_at->toIso8601String(),
          ];
      }
  }
  ```

- [ ] Criar `app/Http/Resources/CustomerCollection.php`
  ```php
  class CustomerCollection extends ResourceCollection
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

### 10. Controller
- [ ] Criar `app/Http/Controllers/Api/V1/CustomerController.php`
  ```php
  class CustomerController extends Controller
  {
      /**
       * Lista todos clientes
       */
      public function index(GetAllCustomersQuery $query): AnonymousResourceCollection
      {
          $customers = $query->execute(
              perPage: request('per_page', 15)
          );

          return CustomerResource::collection($customers);
      }

      /**
       * Exibe cliente específico
       */
      public function show(int $id, GetCustomerByIdQuery $query): CustomerResource
      {
          $customer = $query->execute($id);

          if (!$customer) {
              throw new CustomerNotFoundException($id);
          }

          return new CustomerResource($customer);
      }

      /**
       * Cria novo cliente
       */
      public function store(
          StoreCustomerRequest $request,
          CreateCustomerAction $action
      ): JsonResponse {
          $dto = CreateCustomerDTO::fromRequest($request->validated());

          $customer = $action->execute($dto);

          return (new CustomerResource($customer))
              ->response()
              ->setStatusCode(201);
      }

      /**
       * Atualiza cliente
       */
      public function update(
          int $id,
          UpdateCustomerRequest $request,
          UpdateCustomerAction $action
      ): CustomerResource {
          $dto = UpdateCustomerDTO::fromRequest($request->validated());

          $customer = $action->execute($id, $dto);

          return new CustomerResource($customer);
      }

      /**
       * Remove cliente (soft delete)
       */
      public function destroy(
          int $id,
          DeleteCustomerAction $action
      ): JsonResponse {
          $action->execute($id);

          return response()->json(null, 204);
      }
  }
  ```

### 11. Routes
- [ ] Adicionar rotas em `routes/api.php`
  ```php
  Route::prefix('v1')->group(function () {
      Route::apiResource('customers', CustomerController::class);

      // Rotas adicionais
      Route::post('customers/{id}/activate', [CustomerController::class, 'activate']);
      Route::post('customers/{id}/deactivate', [CustomerController::class, 'deactivate']);
  });
  ```

### 12. Exception Handler
- [ ] Registrar exceptions em `app/Exceptions/Handler.php`
  ```php
  public function register(): void
  {
      $this->renderable(function (CustomerException $e) {
          return $e->render();
      });
  }
  ```

### 13. Testes
- [ ] Criar `tests/Feature/Api/V1/CustomerTest.php`
  ```php
  class CustomerTest extends TestCase
  {
      use RefreshDatabase;

      /** @test */
      public function it_can_create_a_customer(): void
      {
          $response = $this->postJson('/api/v1/customers', [
              'name' => 'John Doe',
              'email' => 'john@example.com',
              'document' => '12345678900',
          ]);

          $response->assertCreated();
          $response->assertJsonStructure(['data' => ['id', 'name', 'email']]);
          $this->assertDatabaseHas('customers', ['email' => 'john@example.com']);
      }

      /** @test */
      public function it_returns_422_for_duplicate_email(): void
      {
          Customer::factory()->create(['email' => 'john@example.com']);

          $response = $this->postJson('/api/v1/customers', [
              'name' => 'John Doe',
              'email' => 'john@example.com',
              'document' => '12345678900',
          ]);

          $response->assertStatus(422);
          $response->assertJson(['error' => 'customer_already_exists']);
      }

      /** @test */
      public function it_returns_404_for_nonexistent_customer(): void
      {
          $response = $this->getJson('/api/v1/customers/999');

          $response->assertNotFound();
          $response->assertJson(['error' => 'customer_not_found']);
      }
  }
  ```

- [ ] Criar `tests/Unit/Actions/Customer/CreateCustomerActionTest.php`
  ```php
  class CreateCustomerActionTest extends TestCase
  {
      use RefreshDatabase;

      private CreateCustomerAction $action;

      protected function setUp(): void
      {
          parent::setUp();
          $this->action = new CreateCustomerAction();
      }

      /** @test */
      public function it_creates_customer_successfully(): void
      {
          $dto = new CreateCustomerDTO(
              name: 'John Doe',
              email: 'john@example.com',
              document: '12345678900'
          );

          $customer = $this->action->execute($dto);

          $this->assertInstanceOf(Customer::class, $customer);
          $this->assertEquals('John Doe', $customer->name);
          $this->assertDatabaseHas('customers', ['email' => 'john@example.com']);
      }

      /** @test */
      public function it_throws_exception_for_duplicate_email(): void
      {
          Customer::factory()->create(['email' => 'john@example.com']);

          $dto = new CreateCustomerDTO(
              name: 'John Doe',
              email: 'john@example.com',
              document: '12345678900'
          );

          $this->expectException(CustomerAlreadyExistsException::class);

          $this->action->execute($dto);
      }
  }
  ```

- [ ] Criar `tests/Unit/Queries/Customer/GetCustomerByIdQueryTest.php`
- [ ] Criar `tests/Unit/DTOs/Customer/CreateCustomerDTOTest.php`

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

### Performance
- [ ] Eager loading implementado
- [ ] Índices criados
- [ ] Paginação obrigatória

### Testes
- [ ] Feature tests (Controllers)
- [ ] Unit tests (Actions/Queries)
- [ ] Cobertura >80%

---

## Critérios de Aceitação

✅ **Funcionalidade**
- CRUD completo funcionando
- Validações impedindo duplicatas
- Soft delete implementado
- Paginação funcionando

✅ **Arquitetura**
- Actions retornam Customer (não JsonResponse)
- Exceptions controlam status codes (404, 422)
- Controller define status de sucesso (200, 201, 204)
- Queries usam Eloquent direto (sem Repository desnecessário)

✅ **Qualidade**
- Todos os testes passando
- Type hints completos
- Actions reutilizáveis

✅ **API**
- Status codes corretos
- Mensagens de erro claras
- Paginação nos listings

---

## Exemplos de Uso da API

```bash
# Criar cliente
POST /api/v1/customers
{
  "name": "João Silva",
  "email": "joao@example.com",
  "document": "12345678900",
  "phone": "11999999999"
}

# Response 201
{
  "data": {
    "id": 1,
    "name": "João Silva",
    "email": "joao@example.com",
    ...
  }
}

# Duplicata - Response 422
{
  "message": "Customer with email 'joao@example.com' already exists",
  "error": "customer_already_exists",
  "details": {
    "field": "email",
    "issue": "duplicate"
  }
}

# Not Found - Response 404
{
  "message": "Customer #999 not found",
  "error": "customer_not_found"
}
```

---

## Notas Importantes

⚠️ **Actions vs Services**
- Actions retornam Models (Customer)
- Actions NUNCA retornam JsonResponse
- Actions lançam Custom Exceptions
- Actions são reutilizáveis em Jobs/Commands

⚠️ **Queries vs Repositories**
- Use Eloquent DIRETO (Customer::find())
- NÃO criar Repository para CRUD simples
- Queries são classes específicas
- Eager loading explícito

⚠️ **Exceptions**
- Controlam status codes (404, 422, etc)
- Método render() retorna JsonResponse
- Controller não sabe de status de erro
- Registrar no Exception Handler

📚 **Referências**
- Prompt.MD: Action Pattern, Query Pattern, Custom Exceptions
- SOLID Principles
- Object Calisthenics
