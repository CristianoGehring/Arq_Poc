# 06 - Testing & Code Quality

## Objetivo
Configurar ambiente de testes completo e ferramentas de qualidade de código.

## Prioridade
🟡 MÉDIA - Deve ser configurado desde o início

## Dependências
- Task 00 (Setup Inicial)

---

## Ordem de Implementação

### 1. PHPUnit / Pest Configuration
- [ ] Escolher framework de testes (PHPUnit ou Pest)
  - PHPUnit (padrão Laravel)
  - Pest (mais moderno e legível)

- [ ] Configurar `phpunit.xml` / `pest.php`
  ```xml
  <!-- phpunit.xml -->
  <coverage>
      <include>
          <directory suffix=".php">./app</directory>
      </include>
      <exclude>
          <directory>./app/Http/Middleware</directory>
          <file>./app/Providers/RouteServiceProvider.php</file>
      </exclude>
      <report>
          <html outputDirectory="coverage"/>
          <clover outputFile="coverage.xml"/>
      </report>
  </coverage>
  ```

### 2. Database Configuration para Testes
- [ ] Configurar banco de testes em `.env.testing`
  ```env
  APP_ENV=testing
  DB_CONNECTION=mysql
  DB_DATABASE=testing_db
  QUEUE_CONNECTION=sync
  CACHE_DRIVER=array
  SESSION_DRIVER=array
  ```

- [ ] Criar `tests/TestCase.php` base
  ```php
  abstract class TestCase extends BaseTestCase
  {
      use CreatesApplication, RefreshDatabase;

      protected function setUp(): void
      {
          parent::setUp();
          $this->artisan('db:seed', ['--class' => 'TestSeeder']);
      }
  }
  ```

### 3. Factories
- [ ] Criar `database/factories/CustomerFactory.php`
  ```php
  public function definition(): array
  {
      return [
          'name' => fake()->name(),
          'email' => fake()->unique()->safeEmail(),
          'document' => fake()->numerify('###########'),
          'phone' => fake()->phoneNumber(),
          'address' => [
              'street' => fake()->streetName(),
              'number' => fake()->buildingNumber(),
              'city' => fake()->city(),
              'state' => fake()->stateAbbr(),
              'zip_code' => fake()->postcode(),
          ],
          'status' => CustomerStatus::ACTIVE,
      ];
  }

  public function inactive(): static
  {
      return $this->state(fn (array $attributes) => [
          'status' => CustomerStatus::INACTIVE,
      ]);
  }
  ```

- [ ] Criar `database/factories/ChargeFactory.php`
  ```php
  public function definition(): array
  {
      return [
          'customer_id' => Customer::factory(),
          'amount' => fake()->randomFloat(2, 10, 1000),
          'description' => fake()->sentence(),
          'payment_method' => fake()->randomElement(PaymentMethod::cases()),
          'status' => ChargeStatus::PENDING,
          'due_date' => fake()->dateTimeBetween('now', '+30 days'),
          'metadata' => null,
      ];
  }

  public function paid(): static
  {
      return $this->state(fn (array $attributes) => [
          'status' => ChargeStatus::PAID,
          'paid_at' => now(),
      ]);
  }

  public function overdue(): static
  {
      return $this->state(fn (array $attributes) => [
          'status' => ChargeStatus::PENDING,
          'due_date' => now()->subDays(5),
      ]);
  }
  ```

- [ ] Criar `database/factories/UserFactory.php`
- [ ] Criar `database/factories/PaymentGatewayFactory.php`
- [ ] Criar `database/factories/WebhookLogFactory.php`

### 4. Feature Tests (Exemplos Completos)
- [ ] Criar estrutura de Feature Tests
  ```
  tests/Feature/
  ├── Api/
  │   └── V1/
  │       ├── AuthTest.php
  │       ├── CustomerTest.php
  │       ├── ChargeTest.php
  │       ├── CustomerChargesTest.php
  │       └── WebhookTest.php
  ```

- [ ] Implementar `CustomerTest.php` completo
  ```php
  class CustomerTest extends TestCase
  {
      use RefreshDatabase;

      /** @test */
      public function it_can_list_customers(): void
      {
          $user = User::factory()->create();
          Customer::factory()->count(20)->create();

          $response = $this->actingAs($user)
              ->getJson('/api/v1/customers');

          $response->assertOk()
              ->assertJsonStructure([
                  'data' => [
                      '*' => ['id', 'name', 'email', 'document']
                  ],
                  'links',
                  'meta'
              ]);
      }

      /** @test */
      public function it_can_create_customer(): void
      {
          $user = User::factory()->create();

          $data = [
              'name' => 'João Silva',
              'email' => 'joao@example.com',
              'document' => '12345678900',
              'phone' => '11999999999',
          ];

          $response = $this->actingAs($user)
              ->postJson('/api/v1/customers', $data);

          $response->assertCreated()
              ->assertJsonFragment(['email' => 'joao@example.com']);

          $this->assertDatabaseHas('customers', [
              'email' => 'joao@example.com'
          ]);
      }

      /** @test */
      public function it_validates_required_fields(): void
      {
          $user = User::factory()->create();

          $response = $this->actingAs($user)
              ->postJson('/api/v1/customers', []);

          $response->assertStatus(422)
              ->assertJsonValidationErrors(['name', 'email', 'document']);
      }

      /** @test */
      public function it_prevents_duplicate_email(): void
      {
          $user = User::factory()->create();
          Customer::factory()->create(['email' => 'joao@example.com']);

          $response = $this->actingAs($user)
              ->postJson('/api/v1/customers', [
                  'name' => 'João',
                  'email' => 'joao@example.com',
                  'document' => '12345678900'
              ]);

          $response->assertStatus(422)
              ->assertJsonValidationErrors(['email']);
      }
  }
  ```

### 5. Unit Tests (Exemplos)
- [ ] Criar estrutura de Unit Tests
  ```
  tests/Unit/
  ├── Services/
  │   ├── Customer/
  │   │   ├── CustomerServiceTest.php
  │   │   └── CustomerQueryServiceTest.php
  │   ├── Charge/
  │   │   ├── ChargeServiceTest.php
  │   │   └── ChargeQueryServiceTest.php
  │   └── PaymentGateway/
  │       └── PaymentGatewayFactoryTest.php
  ├── Repositories/
  │   ├── CustomerRepositoryTest.php
  │   └── ChargeRepositoryTest.php
  ├── DTOs/
  │   └── CreateCustomerDTOTest.php
  └── Models/
      ├── CustomerTest.php
      └── ChargeTest.php
  ```

- [ ] Implementar `CustomerServiceTest.php`
  ```php
  class CustomerServiceTest extends TestCase
  {
      use RefreshDatabase;

      private CustomerService $service;
      private CustomerRepositoryInterface $repository;

      protected function setUp(): void
      {
          parent::setUp();

          $this->repository = app(CustomerRepositoryInterface::class);
          $this->service = new CustomerService($this->repository);
      }

      /** @test */
      public function it_creates_customer_with_valid_data(): void
      {
          $dto = new CreateCustomerDTO(
              name: 'João Silva',
              email: 'joao@example.com',
              document: '12345678900'
          );

          $customer = $this->service->create($dto);

          $this->assertInstanceOf(Customer::class, $customer);
          $this->assertEquals('João Silva', $customer->name);
          $this->assertDatabaseHas('customers', [
              'email' => 'joao@example.com'
          ]);
      }

      /** @test */
      public function it_dispatches_event_when_customer_created(): void
      {
          Event::fake([CustomerCreated::class]);

          $dto = new CreateCustomerDTO(
              name: 'João Silva',
              email: 'joao@example.com',
              document: '12345678900'
          );

          $customer = $this->service->create($dto);

          Event::assertDispatched(CustomerCreated::class, function ($event) use ($customer) {
              return $event->customer->id === $customer->id;
          });
      }
  }
  ```

- [ ] Implementar `ChargeTest.php` (Model)
  ```php
  class ChargeTest extends TestCase
  {
      use RefreshDatabase;

      /** @test */
      public function it_can_check_if_charge_is_paid(): void
      {
          $charge = Charge::factory()->paid()->create();

          $this->assertTrue($charge->isPaid());
      }

      /** @test */
      public function it_can_check_if_charge_is_overdue(): void
      {
          $charge = Charge::factory()->overdue()->create();

          $this->assertTrue($charge->isOverdue());
      }

      /** @test */
      public function paid_charge_cannot_be_cancelled(): void
      {
          $charge = Charge::factory()->paid()->create();

          $this->assertFalse($charge->canBeCancelled());
      }

      /** @test */
      public function pending_charge_can_be_cancelled(): void
      {
          $charge = Charge::factory()->create([
              'status' => ChargeStatus::PENDING
          ]);

          $this->assertTrue($charge->canBeCancelled());
      }
  }
  ```

### 6. Mocking Examples
- [ ] Criar exemplos de mocking para gateways
  ```php
  /** @test */
  public function it_creates_charge_on_gateway(): void
  {
      Http::fake([
          'api.pagseguro.com/*' => Http::response([
              'id' => 'CHG-123',
              'status' => 'pending',
              'payment_url' => 'https://pagseguro.com/pay/CHG-123'
          ], 200)
      ]);

      $gateway = app(PagSeguroGateway::class);
      $dto = new CreateChargeDTO(/* ... */);

      $response = $gateway->createCharge($dto);

      $this->assertEquals('CHG-123', $response['gateway_charge_id']);
  }
  ```

### 7. Laravel Pint (Code Style)
- [ ] Instalar Laravel Pint
  ```bash
  composer require laravel/pint --dev
  ```

- [ ] Configurar `pint.json`
  ```json
  {
      "preset": "laravel",
      "rules": {
          "simplified_null_return": true,
          "braces": false,
          "new_with_braces": true,
          "method_chaining_indentation": true
      }
  }
  ```

- [ ] Adicionar script no `composer.json`
  ```json
  "scripts": {
      "format": "pint",
      "format:test": "pint --test"
  }
  ```

### 8. PHPStan (Static Analysis)
- [ ] Instalar PHPStan
  ```bash
  composer require --dev phpstan/phpstan
  composer require --dev larastan/larastan
  ```

- [ ] Criar `phpstan.neon`
  ```neon
  includes:
      - vendor/larastan/larastan/extension.neon

  parameters:
      paths:
          - app
      level: 5
      ignoreErrors:
          - '#Unsafe usage of new static#'
      excludePaths:
          - app/Http/Middleware/Authenticate.php
  ```

- [ ] Adicionar script no `composer.json`
  ```json
  "scripts": {
      "analyse": "phpstan analyse"
  }
  ```

### 9. CI/CD Pipeline (GitHub Actions)
- [ ] Criar `.github/workflows/tests.yml`
  ```yaml
  name: Tests

  on: [push, pull_request]

  jobs:
    tests:
      runs-on: ubuntu-latest

      services:
        mysql:
          image: mysql:8.0
          env:
            MYSQL_ROOT_PASSWORD: password
            MYSQL_DATABASE: testing_db
          ports:
            - 3306:3306
          options: --health-cmd="mysqladmin ping" --health-interval=10s

        redis:
          image: redis:7
          ports:
            - 6379:6379

      steps:
        - uses: actions/checkout@v3

        - name: Setup PHP
          uses: shivammathur/setup-php@v2
          with:
            php-version: 8.2
            extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, mysql, redis
            coverage: xdebug

        - name: Install Dependencies
          run: composer install --prefer-dist --no-interaction

        - name: Copy .env
          run: php -r "file_exists('.env') || copy('.env.example', '.env');"

        - name: Generate key
          run: php artisan key:generate

        - name: Run Migrations
          run: php artisan migrate --force
          env:
            DB_DATABASE: testing_db
            DB_PASSWORD: password

        - name: Execute tests
          run: php artisan test --coverage --min=80

        - name: Run PHPStan
          run: composer analyse

        - name: Check Code Style
          run: composer format:test
  ```

### 10. Code Coverage
- [ ] Configurar Xdebug para coverage
- [ ] Adicionar comando de coverage
  ```bash
  php artisan test --coverage --min=80
  ```

- [ ] Configurar relatórios HTML
  ```bash
  php artisan test --coverage-html coverage/
  ```

### 11. Test Helpers
- [ ] Criar `tests/Helpers/AssertionHelper.php`
  ```php
  trait AssertionHelper
  {
      protected function assertDatabaseHasCustomer(array $attributes): void
      {
          $this->assertDatabaseHas('customers', $attributes);
      }

      protected function assertEventDispatched(string $event): void
      {
          Event::assertDispatched($event);
      }

      protected function assertJobPushed(string $job): void
      {
          Queue::assertPushed($job);
      }
  }
  ```

### 12. Comandos de Testes
- [ ] Adicionar scripts no `composer.json`
  ```json
  "scripts": {
      "test": "php artisan test",
      "test:unit": "php artisan test --testsuite=Unit",
      "test:feature": "php artisan test --testsuite=Feature",
      "test:coverage": "php artisan test --coverage --min=80",
      "test:parallel": "php artisan test --parallel",
      "format": "pint",
      "format:test": "pint --test",
      "analyse": "phpstan analyse",
      "quality": [
          "@format:test",
          "@analyse",
          "@test:coverage"
      ]
  }
  ```

---

## Checklist de Qualidade

### Configuração
- [ ] PHPUnit/Pest configurado
- [ ] Banco de testes configurado
- [ ] Factories criadas
- [ ] TestCase base criado

### Testes
- [ ] Feature tests para todos os endpoints
- [ ] Unit tests para Services
- [ ] Unit tests para Repositories
- [ ] Unit tests para Models
- [ ] Mocking de HTTP requests
- [ ] Cobertura > 80%

### Code Quality
- [ ] Laravel Pint configurado
- [ ] PHPStan configurado (level 5+)
- [ ] CI/CD pipeline funcionando
- [ ] Scripts composer criados

### Documentação
- [ ] README com instruções de teste
- [ ] Exemplos de testes documentados

---

## Critérios de Aceitação

✅ **Testes**
- Todos os testes passando
- Cobertura mínima 80%
- Feature tests para todos os endpoints
- Unit tests para lógica de negócio

✅ **Qualidade**
- Pint passando (PSR-12)
- PHPStan level 5+ passando
- CI/CD rodando automaticamente

✅ **Performance**
- Testes rodando em < 30s
- Parallel testing funcionando

---

## Comandos Úteis

```bash
# Rodar todos os testes
php artisan test

# Rodar testes específicos
php artisan test --filter CustomerTest

# Rodar com coverage
php artisan test --coverage --min=80

# Rodar em paralelo
php artisan test --parallel

# Code style
composer format

# Static analysis
composer analyse

# Tudo junto
composer quality
```

---

## Notas Importantes

⚠️ **Atenção**
- Sempre rodar testes antes de commit
- Manter cobertura > 80%
- Usar factories ao invés de criar dados manualmente
- Mockar HTTP requests externos
- Limpar banco após cada teste (RefreshDatabase)
- Testar casos de sucesso E falha
- Usar transações em testes quando possível

📚 **Referências**
- Laravel Testing Documentation
- Pest Documentation
- PHPStan Documentation
- Laravel Pint
