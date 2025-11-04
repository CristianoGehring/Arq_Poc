# Task 06: Testing & Quality Assurance

## Objetivo
Implementar testes automatizados e ferramentas de qualidade de código para garantir a confiabilidade e manutenibilidade do sistema de cobrança.

## Stack de Testes
- **Framework de Testes**: PHPUnit / Pest
- **Code Coverage**: PHPUnit Coverage
- **Análise Estática**: PHPStan (Level 8+)
- **Code Style**: Laravel Pint
- **CI/CD**: GitHub Actions

---

## 1. Configuração do Ambiente de Testes

### 1.1 Configuração do PHPUnit

**Arquivo**: `phpunit.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
        <exclude>
            <directory>app/Exceptions</directory>
            <file>app/Http/Kernel.php</file>
        </exclude>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="DB_CONNECTION" value="mysql"/>
        <env name="DB_DATABASE" value="billing_test"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
    </php>
</phpunit>
```

### 1.2 Configuração do Pest (Alternativa ao PHPUnit)

**Instalação**:
```bash
./vendor/bin/sail composer require pestphp/pest --dev --with-all-dependencies
./vendor/bin/sail composer require pestphp/pest-plugin-laravel --dev
./vendor/bin/sail artisan pest:install
```

**Arquivo**: `tests/Pest.php`

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(
    Tests\TestCase::class,
    RefreshDatabase::class
)->in('Feature', 'Unit');

// Helpers globais para testes
function actingAsUser(): \App\Models\User
{
    $user = \App\Models\User::factory()->create();
    test()->actingAs($user);
    return $user;
}

function actingAsAdmin(): \App\Models\User
{
    $user = \App\Models\User::factory()->admin()->create();
    test()->actingAs($user);
    return $user;
}
```

---

## 2. Estrutura de Testes

### 2.1 Organização de Diretórios

```
tests/
├── Feature/                      # Testes de integração/feature
│   ├── Api/
│   │   ├── V1/
│   │   │   ├── Auth/
│   │   │   │   ├── LoginTest.php
│   │   │   │   └── LogoutTest.php
│   │   │   ├── Customer/
│   │   │   │   ├── CreateCustomerTest.php
│   │   │   │   ├── UpdateCustomerTest.php
│   │   │   │   └── ListCustomersTest.php
│   │   │   ├── Charge/
│   │   │   │   ├── CreateChargeTest.php
│   │   │   │   └── ProcessPaymentTest.php
│   │   │   └── Webhook/
│   │   │       └── ProcessWebhookTest.php
├── Unit/                         # Testes unitários
│   ├── Actions/
│   │   ├── Customer/
│   │   │   ├── CreateCustomerActionTest.php
│   │   │   ├── UpdateCustomerActionTest.php
│   │   │   └── DeleteCustomerActionTest.php
│   │   ├── Charge/
│   │   │   ├── CreateChargeActionTest.php
│   │   │   └── ProcessPaymentActionTest.php
│   │   ├── Auth/
│   │   │   ├── LoginUserActionTest.php
│   │   │   └── LogoutUserActionTest.php
│   │   └── Webhook/
│   │       └── ProcessWebhookActionTest.php
│   ├── Queries/
│   │   ├── Customer/
│   │   │   ├── GetCustomerByIdQueryTest.php
│   │   │   └── ListCustomersQueryTest.php
│   │   └── Charge/
│   │       └── GetChargeByIdQueryTest.php
│   ├── PaymentGateways/
│   │   ├── PagSeguro/
│   │   │   └── PagSeguroGatewayTest.php
│   │   ├── Asaas/
│   │   │   └── AsaasGatewayTest.php
│   │   └── Stone/
│   │       └── StoneGatewayTest.php
│   └── Policies/
│       ├── CustomerPolicyTest.php
│       └── ChargePolicyTest.php
├── TestCase.php
└── Pest.php
```

---

## 3. Factories

### 3.1 Customer Factory

**Arquivo**: `database/factories/CustomerFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'document' => fake()->numerify('###########'), // CPF
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'zip_code' => fake()->postcode(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
```

### 3.2 Charge Factory

**Arquivo**: `database/factories/ChargeFactory.php`

```php
<?php

namespace Database\Factories;

use App\Enums\ChargeStatus;
use App\Enums\PaymentMethod;
use App\Models\Charge;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChargeFactory extends Factory
{
    protected $model = Charge::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'amount' => fake()->randomFloat(2, 10, 1000),
            'due_date' => fake()->dateTimeBetween('now', '+30 days'),
            'description' => fake()->sentence(),
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'status' => ChargeStatus::PENDING,
            'gateway' => 'pagseguro',
            'gateway_charge_id' => null,
            'paid_at' => null,
            'canceled_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChargeStatus::PAID,
            'paid_at' => now(),
            'gateway_charge_id' => 'CHG_' . fake()->uuid(),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChargeStatus::CANCELED,
            'canceled_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChargeStatus::OVERDUE,
            'due_date' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }
}
```

### 3.3 User Factory

**Arquivo**: `database/factories/UserFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }
}
```

---

## 4. Testes Unitários (Actions & Queries)

### 4.1 Teste de Action - CreateCustomerAction

**Arquivo**: `tests/Unit/Actions/Customer/CreateCustomerActionTest.php`

```php
<?php

namespace Tests\Unit\Actions\Customer;

use App\Actions\Customer\CreateCustomerAction;
use App\DTOs\Customer\CreateCustomerDTO;
use App\Exceptions\Customer\DuplicateCustomerException;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateCustomerActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateCustomerAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(CreateCustomerAction::class);
    }

    public function test_can_create_customer_successfully(): void
    {
        $user = User::factory()->create();

        $dto = new CreateCustomerDTO(
            userId: $user->id,
            name: 'João Silva',
            email: 'joao@example.com',
            document: '12345678901',
            phone: '11999999999',
            address: 'Rua A, 123',
            city: 'São Paulo',
            state: 'SP',
            zipCode: '01000-000'
        );

        $customer = $this->action->execute($dto);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('João Silva', $customer->name);
        $this->assertEquals('joao@example.com', $customer->email);
        $this->assertEquals($user->id, $customer->user_id);
        $this->assertDatabaseHas('customers', [
            'email' => 'joao@example.com',
            'document' => '12345678901',
        ]);
    }

    public function test_throws_exception_when_customer_already_exists(): void
    {
        $user = User::factory()->create();

        Customer::factory()->create([
            'user_id' => $user->id,
            'email' => 'joao@example.com',
        ]);

        $dto = new CreateCustomerDTO(
            userId: $user->id,
            name: 'João Silva',
            email: 'joao@example.com',
            document: '12345678901',
            phone: '11999999999',
            address: 'Rua A, 123',
            city: 'São Paulo',
            state: 'SP',
            zipCode: '01000-000'
        );

        $this->expectException(DuplicateCustomerException::class);

        $this->action->execute($dto);
    }

    public function test_normalizes_document_before_saving(): void
    {
        $user = User::factory()->create();

        $dto = new CreateCustomerDTO(
            userId: $user->id,
            name: 'João Silva',
            email: 'joao@example.com',
            document: '123.456.789-01', // Com formatação
            phone: '11999999999',
            address: 'Rua A, 123',
            city: 'São Paulo',
            state: 'SP',
            zipCode: '01000-000'
        );

        $customer = $this->action->execute($dto);

        $this->assertEquals('12345678901', $customer->document); // Sem formatação
    }
}
```

### 4.2 Teste de Action - CreateChargeAction

**Arquivo**: `tests/Unit/Actions/Charge/CreateChargeActionTest.php`

```php
<?php

namespace Tests\Unit\Actions\Charge;

use App\Actions\Charge\CreateChargeAction;
use App\DTOs\Charge\CreateChargeDTO;
use App\Enums\ChargeStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\Customer\CustomerNotFoundException;
use App\Models\Charge;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateChargeActionTest extends TestCase
{
    use RefreshDatabase;

    private CreateChargeAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(CreateChargeAction::class);
    }

    public function test_can_create_charge_successfully(): void
    {
        $customer = Customer::factory()->create();

        $dto = new CreateChargeDTO(
            customerId: $customer->id,
            amount: 100.50,
            dueDate: now()->addDays(7),
            description: 'Cobrança de teste',
            paymentMethod: PaymentMethod::BOLETO,
            gateway: 'pagseguro'
        );

        $charge = $this->action->execute($dto);

        $this->assertInstanceOf(Charge::class, $charge);
        $this->assertEquals(100.50, $charge->amount);
        $this->assertEquals(ChargeStatus::PENDING, $charge->status);
        $this->assertEquals($customer->id, $charge->customer_id);
        $this->assertDatabaseHas('charges', [
            'customer_id' => $customer->id,
            'amount' => 100.50,
            'status' => ChargeStatus::PENDING->value,
        ]);
    }

    public function test_throws_exception_when_customer_not_found(): void
    {
        $dto = new CreateChargeDTO(
            customerId: 999, // ID inexistente
            amount: 100.50,
            dueDate: now()->addDays(7),
            description: 'Cobrança de teste',
            paymentMethod: PaymentMethod::BOLETO,
            gateway: 'pagseguro'
        );

        $this->expectException(CustomerNotFoundException::class);

        $this->action->execute($dto);
    }

    public function test_sets_correct_initial_status(): void
    {
        $customer = Customer::factory()->create();

        $dto = new CreateChargeDTO(
            customerId: $customer->id,
            amount: 100.50,
            dueDate: now()->addDays(7),
            description: 'Cobrança de teste',
            paymentMethod: PaymentMethod::CREDIT_CARD,
            gateway: 'pagseguro'
        );

        $charge = $this->action->execute($dto);

        $this->assertEquals(ChargeStatus::PENDING, $charge->status);
        $this->assertNull($charge->paid_at);
        $this->assertNull($charge->canceled_at);
    }
}
```

### 4.3 Teste de Query - GetCustomerByIdQuery

**Arquivo**: `tests/Unit/Queries/Customer/GetCustomerByIdQueryTest.php`

```php
<?php

namespace Tests\Unit\Queries\Customer;

use App\Exceptions\Customer\CustomerNotFoundException;
use App\Models\Customer;
use App\Models\User;
use App\Queries\Customer\GetCustomerByIdQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetCustomerByIdQueryTest extends TestCase
{
    use RefreshDatabase;

    private GetCustomerByIdQuery $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = app(GetCustomerByIdQuery::class);
    }

    public function test_can_get_customer_by_id_successfully(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->forUser($user)->create([
            'name' => 'João Silva',
            'email' => 'joao@example.com',
        ]);

        $result = $this->query->execute($customer->id, $user->id);

        $this->assertInstanceOf(Customer::class, $result);
        $this->assertEquals($customer->id, $result->id);
        $this->assertEquals('João Silva', $result->name);
        $this->assertEquals('joao@example.com', $result->email);
    }

    public function test_throws_exception_when_customer_not_found(): void
    {
        $user = User::factory()->create();

        $this->expectException(CustomerNotFoundException::class);

        $this->query->execute(999, $user->id);
    }

    public function test_throws_exception_when_customer_belongs_to_different_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $customer = Customer::factory()->forUser($user1)->create();

        $this->expectException(CustomerNotFoundException::class);

        $this->query->execute($customer->id, $user2->id);
    }

    public function test_loads_relationships_when_requested(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->forUser($user)->create();

        // Criar algumas cobranças para o cliente
        $customer->charges()->createMany([
            ['amount' => 100, 'status' => 'pending'],
            ['amount' => 200, 'status' => 'paid'],
        ]);

        $result = $this->query->withRelations(['charges'])->execute($customer->id, $user->id);

        $this->assertTrue($result->relationLoaded('charges'));
        $this->assertCount(2, $result->charges);
    }
}
```

### 4.4 Teste de Action - LoginUserAction

**Arquivo**: `tests/Unit/Actions/Auth/LoginUserActionTest.php`

```php
<?php

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\LoginUserAction;
use App\DTOs\Auth\LoginDTO;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginUserActionTest extends TestCase
{
    use RefreshDatabase;

    private LoginUserAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(LoginUserAction::class);
    }

    public function test_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'joao@example.com',
            'password' => Hash::make('password123'),
        ]);

        $dto = new LoginDTO(
            email: 'joao@example.com',
            password: 'password123',
            deviceName: 'web-browser'
        );

        $result = $this->action->execute($dto);

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals($user->id, $result['user']->id);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'web-browser',
        ]);
    }

    public function test_throws_exception_with_invalid_email(): void
    {
        $dto = new LoginDTO(
            email: 'nonexistent@example.com',
            password: 'password123',
            deviceName: 'web-browser'
        );

        $this->expectException(InvalidCredentialsException::class);

        $this->action->execute($dto);
    }

    public function test_throws_exception_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'joao@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $dto = new LoginDTO(
            email: 'joao@example.com',
            password: 'wrong-password',
            deviceName: 'web-browser'
        );

        $this->expectException(InvalidCredentialsException::class);

        $this->action->execute($dto);
    }

    public function test_token_has_correct_abilities(): void
    {
        $user = User::factory()->create([
            'email' => 'joao@example.com',
            'password' => Hash::make('password123'),
        ]);

        $dto = new LoginDTO(
            email: 'joao@example.com',
            password: 'password123',
            deviceName: 'web-browser'
        );

        $result = $this->action->execute($dto);

        $token = $user->tokens()->first();

        $this->assertTrue($token->can('*'));
    }
}
```

---

## 5. Testes de Integração (Feature Tests)

### 5.1 Feature Test - Customer CRUD

**Arquivo**: `tests/Feature/Api/V1/Customer/CreateCustomerTest.php`

```php
<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_customer_with_valid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/customers', [
                'name' => 'João Silva',
                'email' => 'joao@example.com',
                'document' => '12345678901',
                'phone' => '11999999999',
                'address' => 'Rua A, 123',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => '01000-000',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'document',
                    'phone',
                    'address',
                    'city',
                    'state',
                    'zip_code',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'name' => 'João Silva',
                    'email' => 'joao@example.com',
                ],
            ]);

        $this->assertDatabaseHas('customers', [
            'email' => 'joao@example.com',
            'document' => '12345678901',
            'user_id' => $user->id,
        ]);
    }

    public function test_cannot_create_customer_with_duplicate_email(): void
    {
        $user = User::factory()->create();

        // Criar primeiro cliente
        $this->actingAs($user)->postJson('/api/v1/customers', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'document' => '12345678901',
            'phone' => '11999999999',
            'address' => 'Rua A, 123',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zip_code' => '01000-000',
        ]);

        // Tentar criar segundo cliente com mesmo email
        $response = $this->actingAs($user)->postJson('/api/v1/customers', [
            'name' => 'Maria Silva',
            'email' => 'joao@example.com', // Email duplicado
            'document' => '98765432109',
            'phone' => '11888888888',
            'address' => 'Rua B, 456',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zip_code' => '02000-000',
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Cliente já existe com este email ou documento.',
            ]);
    }

    public function test_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/customers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'document']);
    }

    public function test_validates_email_format(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/customers', [
            'name' => 'João Silva',
            'email' => 'invalid-email',
            'document' => '12345678901',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/customers', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'document' => '12345678901',
        ]);

        $response->assertStatus(401);
    }
}
```

### 5.2 Feature Test - Charge Creation and Payment

**Arquivo**: `tests/Feature/Api/V1/Charge/CreateChargeTest.php`

```php
<?php

namespace Tests\Feature\Api\V1\Charge;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_charge_for_existing_customer(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->forUser($user)->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/charges', [
                'customer_id' => $customer->id,
                'amount' => 100.50,
                'due_date' => now()->addDays(7)->format('Y-m-d'),
                'description' => 'Cobrança de teste',
                'payment_method' => 'boleto',
                'gateway' => 'pagseguro',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'customer_id',
                    'amount',
                    'due_date',
                    'description',
                    'payment_method',
                    'status',
                    'gateway',
                    'created_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'amount' => '100.50',
                    'status' => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('charges', [
            'customer_id' => $customer->id,
            'amount' => 100.50,
            'status' => 'pending',
        ]);
    }

    public function test_cannot_create_charge_for_nonexistent_customer(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/charges', [
                'customer_id' => 999,
                'amount' => 100.50,
                'due_date' => now()->addDays(7)->format('Y-m-d'),
                'description' => 'Cobrança de teste',
                'payment_method' => 'boleto',
                'gateway' => 'pagseguro',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Cliente não encontrado.',
            ]);
    }

    public function test_cannot_create_charge_for_customer_of_another_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $customer = Customer::factory()->forUser($user1)->create();

        $response = $this->actingAs($user2)
            ->postJson('/api/v1/charges', [
                'customer_id' => $customer->id,
                'amount' => 100.50,
                'due_date' => now()->addDays(7)->format('Y-m-d'),
                'description' => 'Cobrança de teste',
                'payment_method' => 'boleto',
                'gateway' => 'pagseguro',
            ]);

        $response->assertStatus(404);
    }

    public function test_validates_minimum_amount(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->forUser($user)->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/charges', [
                'customer_id' => $customer->id,
                'amount' => 0.50, // Abaixo do mínimo
                'due_date' => now()->addDays(7)->format('Y-m-d'),
                'description' => 'Cobrança de teste',
                'payment_method' => 'boleto',
                'gateway' => 'pagseguro',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }
}
```

### 5.3 Feature Test - Webhook Processing

**Arquivo**: `tests/Feature/Api/V1/Webhook/ProcessWebhookTest.php`

```php
<?php

namespace Tests\Feature\Api\V1\Webhook;

use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProcessWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_process_pagseguro_payment_webhook(): void
    {
        Event::fake();

        $customer = Customer::factory()->create();
        $charge = Charge::factory()->create([
            'customer_id' => $customer->id,
            'gateway' => 'pagseguro',
            'gateway_charge_id' => 'CHG_123456',
            'status' => ChargeStatus::PENDING,
        ]);

        $payload = [
            'event' => 'charge.paid',
            'data' => [
                'id' => 'CHG_123456',
                'status' => 'paid',
                'paid_at' => now()->toIso8601String(),
            ],
        ];

        $response = $this->postJson('/api/v1/webhooks/pagseguro', $payload);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Webhook processado com sucesso']);

        $charge->refresh();
        $this->assertEquals(ChargeStatus::PAID, $charge->status);
        $this->assertNotNull($charge->paid_at);
    }

    public function test_webhook_is_idempotent(): void
    {
        $customer = Customer::factory()->create();
        $charge = Charge::factory()->create([
            'customer_id' => $customer->id,
            'gateway' => 'pagseguro',
            'gateway_charge_id' => 'CHG_123456',
            'status' => ChargeStatus::PENDING,
        ]);

        $payload = [
            'event' => 'charge.paid',
            'data' => [
                'id' => 'CHG_123456',
                'status' => 'paid',
                'paid_at' => now()->toIso8601String(),
            ],
        ];

        // Enviar webhook pela primeira vez
        $this->postJson('/api/v1/webhooks/pagseguro', $payload)
            ->assertStatus(200);

        // Enviar o mesmo webhook novamente
        $this->postJson('/api/v1/webhooks/pagseguro', $payload)
            ->assertStatus(200);

        // Verificar que apenas um registro foi criado
        $this->assertDatabaseCount('webhook_logs', 1);
    }

    public function test_validates_webhook_signature(): void
    {
        $payload = [
            'event' => 'charge.paid',
            'data' => ['id' => 'CHG_123456'],
        ];

        $response = $this->postJson('/api/v1/webhooks/pagseguro', $payload, [
            'X-Signature' => 'invalid-signature',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Assinatura inválida']);
    }
}
```

---

## 6. Testes de Payment Gateways (Mocking HTTP)

### 6.1 Teste de Gateway com HTTP Mocking

**Arquivo**: `tests/Unit/PaymentGateways/PagSeguro/PagSeguroGatewayTest.php`

```php
<?php

namespace Tests\Unit\PaymentGateways\PagSeguro;

use App\DTOs\Charge\CreateChargeDTO;
use App\Enums\PaymentMethod;
use App\Gateways\PagSeguro\PagSeguroGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PagSeguroGatewayTest extends TestCase
{
    use RefreshDatabase;

    private PagSeguroGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = app(PagSeguroGateway::class);
    }

    public function test_can_create_charge_successfully(): void
    {
        Http::fake([
            'api.pagseguro.com/charges' => Http::response([
                'id' => 'CHG_123456',
                'status' => 'pending',
                'amount' => 10050,
                'boleto_url' => 'https://pagseguro.com/boleto/123456',
            ], 201),
        ]);

        $dto = new CreateChargeDTO(
            customerId: 1,
            amount: 100.50,
            dueDate: now()->addDays(7),
            description: 'Teste',
            paymentMethod: PaymentMethod::BOLETO,
            gateway: 'pagseguro'
        );

        $result = $this->gateway->createCharge($dto);

        $this->assertEquals('CHG_123456', $result['gateway_charge_id']);
        $this->assertEquals('pending', $result['status']);
        $this->assertArrayHasKey('boleto_url', $result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.pagseguro.com/charges' &&
                   $request['amount'] === 10050;
        });
    }

    public function test_handles_api_errors_gracefully(): void
    {
        Http::fake([
            'api.pagseguro.com/charges' => Http::response([
                'error' => 'Invalid request',
            ], 400),
        ]);

        $dto = new CreateChargeDTO(
            customerId: 1,
            amount: 100.50,
            dueDate: now()->addDays(7),
            description: 'Teste',
            paymentMethod: PaymentMethod::BOLETO,
            gateway: 'pagseguro'
        );

        $this->expectException(\App\Exceptions\PaymentGateway\GatewayException::class);

        $this->gateway->createCharge($dto);
    }

    public function test_retries_on_timeout(): void
    {
        Http::fake([
            'api.pagseguro.com/charges' => Http::sequence()
                ->push(['error' => 'timeout'], 500)
                ->push(['error' => 'timeout'], 500)
                ->push(['id' => 'CHG_123456', 'status' => 'pending'], 201),
        ]);

        $dto = new CreateChargeDTO(
            customerId: 1,
            amount: 100.50,
            dueDate: now()->addDays(7),
            description: 'Teste',
            paymentMethod: PaymentMethod::BOLETO,
            gateway: 'pagseguro'
        );

        $result = $this->gateway->createCharge($dto);

        $this->assertEquals('CHG_123456', $result['gateway_charge_id']);
        Http::assertSentCount(3);
    }
}
```

---

## 7. Testes de Policies

### 7.1 Teste de CustomerPolicy

**Arquivo**: `tests/Unit/Policies/CustomerPolicyTest.php`

```php
<?php

namespace Tests\Unit\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\CustomerPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPolicyTest extends TestCase
{
    use RefreshDatabase;

    private CustomerPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new CustomerPolicy();
    }

    public function test_user_can_view_own_customer(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->forUser($user)->create();

        $this->assertTrue($this->policy->view($user, $customer));
    }

    public function test_user_cannot_view_other_users_customer(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $customer = Customer::factory()->forUser($user1)->create();

        $this->assertFalse($this->policy->view($user2, $customer));
    }

    public function test_user_can_update_own_customer(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->forUser($user)->create();

        $this->assertTrue($this->policy->update($user, $customer));
    }

    public function test_user_cannot_update_other_users_customer(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $customer = Customer::factory()->forUser($user1)->create();

        $this->assertFalse($this->policy->update($user2, $customer));
    }

    public function test_user_can_delete_own_customer(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->forUser($user)->create();

        $this->assertTrue($this->policy->delete($user, $customer));
    }

    public function test_user_cannot_delete_other_users_customer(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $customer = Customer::factory()->forUser($user1)->create();

        $this->assertFalse($this->policy->delete($user2, $customer));
    }
}
```

---

## 8. Laravel Pint (Code Style)

### 8.1 Configuração do Pint

**Arquivo**: `pint.json`

```json
{
    "preset": "laravel",
    "rules": {
        "align_multiline_comment": true,
        "array_indentation": true,
        "array_syntax": {
            "syntax": "short"
        },
        "binary_operator_spaces": {
            "default": "single_space"
        },
        "blank_line_after_namespace": true,
        "blank_line_after_opening_tag": true,
        "blank_line_before_statement": {
            "statements": ["return", "throw", "try"]
        },
        "braces": {
            "allow_single_line_closure": false
        },
        "cast_spaces": {
            "space": "single"
        },
        "class_attributes_separation": {
            "elements": {
                "const": "one",
                "method": "one",
                "property": "one"
            }
        },
        "concat_space": {
            "spacing": "none"
        },
        "declare_equal_normalize": {
            "space": "none"
        },
        "fully_qualified_strict_types": true,
        "function_typehint_space": true,
        "no_unused_imports": true,
        "ordered_imports": {
            "sort_algorithm": "alpha"
        },
        "single_quote": true,
        "trailing_comma_in_multiline": {
            "elements": ["arrays"]
        }
    }
}
```

### 8.2 Comandos do Pint

```bash
# Verificar issues sem corrigir
./vendor/bin/sail pint --test

# Corrigir automaticamente
./vendor/bin/sail pint

# Verificar apenas diretório específico
./vendor/bin/sail pint app/Actions

# Verificar arquivo específico
./vendor/bin/sail pint app/Actions/Customer/CreateCustomerAction.php
```

---

## 9. PHPStan (Static Analysis)

### 9.1 Configuração do PHPStan

**Arquivo**: `phpstan.neon`

```neon
includes:
    - ./vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app
        - config
        - database
        - routes

    level: 8

    ignoreErrors:
        - '#Unsafe usage of new static#'

    excludePaths:
        - ./*/*/FileToBeExcluded.php

    checkMissingIterableValueType: true
    checkGenericClassInNonGenericObjectType: true
    reportUnmatchedIgnoredErrors: false

    parallel:
        jobSize: 10
        maximumNumberOfProcesses: 32
        minimumNumberOfJobsPerProcess: 2
```

### 9.2 Instalação e Uso do PHPStan

```bash
# Instalar
./vendor/bin/sail composer require --dev phpstan/phpstan larastan/larastan

# Executar análise
./vendor/bin/sail composer exec phpstan analyse

# Análise com nível específico
./vendor/bin/sail composer exec phpstan analyse --level=8

# Gerar baseline (ignorar erros existentes)
./vendor/bin/sail composer exec phpstan analyse --generate-baseline
```

---

## 10. Code Coverage

### 10.1 Configuração de Cobertura

```bash
# Gerar relatório de cobertura (HTML)
./vendor/bin/sail test --coverage-html coverage

# Gerar relatório no terminal
./vendor/bin/sail test --coverage

# Gerar com mínimo de cobertura
./vendor/bin/sail test --coverage --min=80
```

### 10.2 Configuração no phpunit.xml

Adicionar no `phpunit.xml`:

```xml
<coverage processUncoveredFiles="true">
    <include>
        <directory suffix=".php">./app</directory>
    </include>
    <exclude>
        <directory suffix=".php">./app/Exceptions</directory>
        <file>./app/Http/Kernel.php</file>
    </exclude>
    <report>
        <html outputDirectory="coverage"/>
        <text outputFile="coverage.txt"/>
        <clover outputFile="coverage.xml"/>
    </report>
</coverage>
```

---

## 11. CI/CD Pipeline (GitHub Actions)

### 11.1 Workflow de Testes

**Arquivo**: `.github/workflows/tests.yml`

```yaml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  tests:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: billing_test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
        options: >-
          --health-cmd="redis-cli ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - name: Checkout code
        uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv
          coverage: xdebug

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction --no-progress

      - name: Copy environment file
        run: cp .env.example .env

      - name: Generate application key
        run: php artisan key:generate

      - name: Run migrations
        run: php artisan migrate --force
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: billing_test
          DB_USERNAME: root
          DB_PASSWORD: password

      - name: Run tests with coverage
        run: php artisan test --coverage --min=80
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: billing_test
          DB_USERNAME: root
          DB_PASSWORD: password

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
          fail_ci_if_error: true
```

### 11.2 Workflow de Code Quality

**Arquivo**: `.github/workflows/code-quality.yml`

```yaml
name: Code Quality

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  pint:
    runs-on: ubuntu-latest
    name: Laravel Pint

    steps:
      - name: Checkout code
        uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction

      - name: Run Pint
        run: ./vendor/bin/pint --test

  phpstan:
    runs-on: ubuntu-latest
    name: PHPStan

    steps:
      - name: Checkout code
        uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2

      - name: Install dependencies
        run: composer install --prefer-dist --no-interaction

      - name: Run PHPStan
        run: ./vendor/bin/phpstan analyse --memory-limit=2G
```

---

## 12. Boas Práticas de Testes

### 12.1 Princípios FIRST

- **Fast**: Testes devem ser rápidos
- **Independent**: Testes não devem depender uns dos outros
- **Repeatable**: Resultados consistentes em qualquer ambiente
- **Self-validating**: Resultado claro (passou ou falhou)
- **Timely**: Escrever testes antes ou junto com o código

### 12.2 Padrão AAA (Arrange, Act, Assert)

```php
public function test_example(): void
{
    // Arrange - Preparar o cenário
    $user = User::factory()->create();
    $dto = new CreateCustomerDTO(...);

    // Act - Executar a ação
    $result = $this->action->execute($dto);

    // Assert - Verificar o resultado
    $this->assertInstanceOf(Customer::class, $result);
    $this->assertEquals('expected', $result->name);
}
```

### 12.3 Nomenclatura de Testes

```php
// ✅ Bom - descreve o que o teste faz
test_can_create_customer_with_valid_data()
test_throws_exception_when_customer_already_exists()
test_validates_email_format()

// ❌ Ruim - nomenclatura vaga
test_customer()
test_create()
test_validation()
```

### 12.4 Testes de Exceções

```php
// ✅ Bom - testar exceção específica
public function test_throws_specific_exception(): void
{
    $this->expectException(CustomerNotFoundException::class);
    $this->expectExceptionMessage('Cliente não encontrado.');

    $this->action->execute($dto);
}

// ❌ Ruim - testar apenas que lançou exceção
public function test_throws_exception(): void
{
    $this->expectException(\Exception::class);
    $this->action->execute($dto);
}
```

### 12.5 Data Providers (Pest)

```php
it('validates email format', function (string $email, bool $valid) {
    $response = $this->postJson('/api/v1/customers', [
        'email' => $email,
        // ... outros campos
    ]);

    if ($valid) {
        $response->assertStatus(201);
    } else {
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
})->with([
    ['joao@example.com', true],
    ['maria@test.com.br', true],
    ['invalid-email', false],
    ['@example.com', false],
    ['test@', false],
]);
```

---

## 13. Métricas de Qualidade

### 13.1 Métricas Recomendadas

- **Code Coverage**: Mínimo 80%
- **PHPStan Level**: 8 (máximo)
- **Complexidade Ciclomática**: Máximo 10 por método
- **Duplicação de Código**: Máximo 3%

### 13.2 Comandos de Verificação

```bash
# Executar todos os testes
./vendor/bin/sail test

# Executar testes com cobertura
./vendor/bin/sail test --coverage --min=80

# Análise estática
./vendor/bin/sail composer exec phpstan analyse

# Verificar code style
./vendor/bin/sail pint --test

# Executar tudo em sequência
./vendor/bin/sail pint --test && \
./vendor/bin/sail composer exec phpstan analyse && \
./vendor/bin/sail test --coverage --min=80
```

---

## 14. Checklist de Qualidade

### Antes de Fazer Commit

- [ ] Todos os testes passando (`sail test`)
- [ ] Code coverage mínimo de 80% (`sail test --coverage --min=80`)
- [ ] PHPStan sem erros (`sail composer exec phpstan analyse`)
- [ ] Laravel Pint sem issues (`sail pint --test`)
- [ ] Testes escritos para novas funcionalidades
- [ ] Testes de exceções para casos de erro
- [ ] Feature tests para endpoints de API
- [ ] Factories criadas para novos models

### Antes de Fazer Merge para Main

- [ ] CI/CD pipeline passando
- [ ] Code review aprovado
- [ ] Documentação atualizada
- [ ] Testes de integração executados
- [ ] Performance verificada (queries N+1, etc.)

---

## Conclusão

Com esta configuração completa de testes e quality assurance, o projeto garante:

✅ **Alta cobertura de testes** (unitários, integração, feature)
✅ **Qualidade de código** (PHPStan level 8, Laravel Pint)
✅ **CI/CD automatizado** (GitHub Actions)
✅ **Testes alinhados com Actions/Queries/Exceptions**
✅ **Mocking de HTTP para gateways externos**
✅ **Políticas de autorização testadas**

**Próximos Passos**: Task 07 - Documentation & Deployment
