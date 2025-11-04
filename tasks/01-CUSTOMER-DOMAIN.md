# 01 - Customer Domain (Domínio de Clientes)

## Objetivo
Implementar o domínio completo de gerenciamento de clientes com CRUD, seguindo todos os padrões arquiteturais.

## Prioridade
🔴 ALTA - Domínio fundamental do sistema

## Dependências
- Setup Inicial (Task 00)

---

## Ordem de Implementação

### 1. Enums
- [ ] Criar `app/Enums/CustomerStatus.php`
  - ACTIVE
  - INACTIVE
  - BLOCKED

### 2. Exceptions
- [ ] Criar `app/Exceptions/CustomerException.php`
- [ ] Criar `app/Exceptions/CustomerNotFoundException.php`
- [ ] Criar `app/Exceptions/CustomerAlreadyExistsException.php`

### 3. DTOs (Data Transfer Objects)
- [ ] Criar `app/DTOs/Customer/CreateCustomerDTO.php`
  - Propriedades: name, email, document, phone?, address?
  - Método `fromRequest(array $data): self`
  - Método `toArray(): array`
  - Usar `readonly class`

- [ ] Criar `app/DTOs/Customer/UpdateCustomerDTO.php`
  - Propriedades: name?, email?, phone?, address?, status?
  - Método `fromRequest(array $data): self`
  - Método `toArray(): array`
  - Usar `readonly class`

### 4. Migration & Model
- [ ] Criar migration `create_customers_table`
  - id (primary key)
  - name (string)
  - email (string, unique, index)
  - document (string, unique, index - CPF/CNPJ)
  - phone (string, nullable)
  - address (json, nullable)
  - status (enum: active, inactive, blocked)
  - timestamps
  - softDeletes
  - Índices: email, document, created_at

- [ ] Criar `app/Models/Customer.php`
  - Casts apropriados (address -> array, status -> enum)
  - Relacionamento hasMany com Charge
  - Scope `scopeActive()`
  - Scope `scopeByDocument(string $document)`

### 5. Repository Pattern
- [ ] Criar `app/Repositories/Contracts/CustomerRepositoryInterface.php`
  ```php
  interface CustomerRepositoryInterface
  {
      public function find(int $id): ?Customer;
      public function findByEmail(string $email): ?Customer;
      public function findByDocument(string $document): ?Customer;
      public function create(array $data): Customer;
      public function update(int $id, array $data): Customer;
      public function delete(int $id): bool;
      public function existsByEmail(string $email): bool;
      public function existsByDocument(string $document): bool;
      public function paginate(int $perPage = 15): LengthAwarePaginator;
  }
  ```

- [ ] Criar `app/Repositories/Eloquent/CustomerRepository.php`
  - Implementar todos os métodos da interface
  - Usar Query Builder otimizado
  - Eager loading quando necessário

- [ ] Registrar binding no `AppServiceProvider`
  ```php
  $this->app->bind(
      CustomerRepositoryInterface::class,
      CustomerRepository::class
  );
  ```

### 6. Services (CQRS Leve)
- [ ] Criar `app/Services/Customer/CustomerService.php` (Commands - Write)
  ```php
  public function create(CreateCustomerDTO $dto): Customer;
  public function update(int $id, UpdateCustomerDTO $dto): Customer;
  public function delete(int $id): bool;
  public function activate(int $id): Customer;
  public function deactivate(int $id): Customer;
  public function block(int $id): Customer;
  ```

- [ ] Criar `app/Services/Customer/CustomerQueryService.php` (Queries - Read)
  ```php
  public function findById(int $id): ?Customer;
  public function findByEmail(string $email): ?Customer;
  public function findByDocument(string $document): ?Customer;
  public function getActive(int $perPage = 15): LengthAwarePaginator;
  public function getAll(int $perPage = 15): LengthAwarePaginator;
  public function search(string $term, int $perPage = 15): LengthAwarePaginator;
  ```

### 7. Events
- [ ] Criar `app/Events/CustomerCreated.php`
- [ ] Criar `app/Events/CustomerUpdated.php`
- [ ] Criar `app/Events/CustomerDeleted.php`

### 8. Form Requests
- [ ] Criar `app/Http/Requests/Customer/StoreCustomerRequest.php`
  ```php
  rules: [
      'name' => ['required', 'string', 'min:3', 'max:255'],
      'email' => ['required', 'email', 'unique:customers,email'],
      'document' => ['required', 'string', 'cpf_cnpj', 'unique:customers,document'],
      'phone' => ['nullable', 'string', 'telefone_com_ddd'],
      'address' => ['nullable', 'array'],
      'address.street' => ['required_with:address', 'string'],
      'address.number' => ['required_with:address', 'string'],
      'address.city' => ['required_with:address', 'string'],
      'address.state' => ['required_with:address', 'string', 'size:2'],
      'address.zip_code' => ['required_with:address', 'string', 'formato_cep'],
  ]
  ```

- [ ] Criar `app/Http/Requests/Customer/UpdateCustomerRequest.php`
  - Validações similares, mas todos campos opcionais
  - Unique rules com `ignore` para o ID atual

### 9. API Resources
- [ ] Criar `app/Http/Resources/CustomerResource.php`
  ```php
  return [
      'id' => $this->id,
      'name' => $this->name,
      'email' => $this->email,
      'document' => $this->document,
      'phone' => $this->phone,
      'address' => $this->address,
      'status' => $this->status->value,
      'created_at' => $this->created_at->toIso8601String(),
      'updated_at' => $this->updated_at->toIso8601String(),
  ];
  ```

- [ ] Criar `app/Http/Resources/CustomerCollection.php`

### 10. Controller
- [ ] Criar `app/Http/Controllers/Api/V1/CustomerController.php`
  - `index()`: Listar clientes paginados
  - `store(StoreCustomerRequest)`: Criar cliente
  - `show(int $id)`: Mostrar cliente específico
  - `update(UpdateCustomerRequest, int $id)`: Atualizar cliente
  - `destroy(int $id)`: Deletar cliente (soft delete)
  - Controllers devem ser THIN (apenas delegação)

### 11. Routes
- [ ] Adicionar rotas em `routes/api.php`
  ```php
  Route::prefix('v1')->group(function () {
      Route::apiResource('customers', CustomerController::class);
  });
  ```

### 12. Testes
- [ ] Criar `tests/Feature/Api/V1/CustomerTest.php`
  - `test_can_list_customers()`
  - `test_can_create_customer()`
  - `test_can_show_customer()`
  - `test_can_update_customer()`
  - `test_can_delete_customer()`
  - `test_validates_required_fields()`
  - `test_prevents_duplicate_email()`
  - `test_prevents_duplicate_document()`
  - `test_returns_404_for_nonexistent_customer()`

- [ ] Criar `tests/Unit/Services/Customer/CustomerServiceTest.php`
  - Testar lógica de negócio
  - Mockar repository
  - Testar exceptions

- [ ] Criar `tests/Unit/DTOs/Customer/CreateCustomerDTOTest.php`
  - Testar criação do DTO
  - Testar método fromRequest()
  - Testar validações de domínio

---

## Checklist de Qualidade

### Arquitetura
- [ ] SOLID principles seguidos
- [ ] Object Calisthenics aplicado
- [ ] Repository Pattern implementado
- [ ] DTOs readonly criados
- [ ] Services separados (Command/Query)

### Código
- [ ] Type hints em todos os métodos
- [ ] Sem uso de else desnecessário
- [ ] Nomes descritivos (sem abreviações)
- [ ] Métodos com máximo 20 linhas
- [ ] Classes com máximo 200 linhas
- [ ] Máximo 2 variáveis de instância por classe

### Validação
- [ ] FormRequest para validação HTTP
- [ ] Validação de domínio no DTO
- [ ] Regras de negócio no Service

### Performance
- [ ] Eager loading implementado onde necessário
- [ ] Queries otimizadas
- [ ] Índices criados nas migrations
- [ ] Paginação em todas as listagens

### Testes
- [ ] Feature tests criados
- [ ] Unit tests para lógica complexa
- [ ] Cobertura mínima 80%
- [ ] Transactions usadas onde necessário

---

## Critérios de Aceitação

✅ **Funcionalidade**
- CRUD completo de clientes funcionando
- Validações impedindo duplicatas
- Soft delete implementado
- Paginação funcionando

✅ **Arquitetura**
- Repository Pattern implementado
- DTOs readonly criados
- Services separados (Command/Query)
- Events disparados corretamente

✅ **Qualidade**
- Todos os testes passando
- Cobertura > 80%
- PSR-12 seguido
- Type hints completos

✅ **API**
- Endpoints retornando JSON correto
- Status codes apropriados (200, 201, 404, 422)
- Validações retornando erros claros
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
  "phone": "11999999999",
  "address": {
    "street": "Rua Exemplo",
    "number": "123",
    "city": "São Paulo",
    "state": "SP",
    "zip_code": "01234-567"
  }
}

# Listar clientes (paginado)
GET /api/v1/customers?page=1&per_page=15

# Buscar cliente
GET /api/v1/customers/1

# Atualizar cliente
PUT /api/v1/customers/1
{
  "name": "João Silva Santos",
  "phone": "11988888888"
}

# Deletar cliente
DELETE /api/v1/customers/1
```

---

## Notas Importantes

⚠️ **Atenção**
- Sempre usar DTOs, nunca arrays
- Controller deve ser THIN
- Validar CPF/CNPJ adequadamente
- Usar transactions em operações críticas
- Disparar events após operações bem-sucedidas
- Logar operações importantes

📚 **Referências**
- Prompt.MD seções: architectural_patterns, solid_principles, object_calisthenics
- PSR-12 Code Style
- Laravel Best Practices
