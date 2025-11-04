# 05 - Authentication & Authorization

## Objetivo
Implementar autenticação via **Laravel Sanctum** e sistema de autorização com **Policies**, seguindo a arquitetura baseada em Actions/Queries/Exceptions.

## Prioridade
🟡 MÉDIA-ALTA - Necessário antes de produção

## Dependências
- Task 00 (Setup Inicial)
- Task 01 (Customer Domain)
- Task 02 (Charge Domain)

---

## Ordem de Implementação

### 1. Sanctum Setup

```bash
# Instalar Sanctum
./vendor/bin/sail composer require laravel/sanctum

# Publicar configurações
./vendor/bin/sail artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Rodar migrations
./vendor/bin/sail artisan migrate
```

**Configurar `config/sanctum.php`:**
```php
return [
    'expiration' => env('SANCTUM_TOKEN_EXPIRATION', null), // null = sem expiração

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
```

**Tarefas:**
- [ ] Instalar Laravel Sanctum
- [ ] Publicar configurações
- [ ] Rodar migrations
- [ ] Configurar `SANCTUM_TOKEN_EXPIRATION` no `.env` (ex: 60 para 60 minutos)

---

### 2. User Model & Migration

#### 2.1 Migration
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn('is_active');
        });
    }
};
```

#### 2.2 Model
```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    /**
     * Scope: Apenas usuários ativos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Verifica se usuário está ativo
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }
}
```

**Tarefas:**
- [ ] Criar migration `add_is_active_to_users_table`
- [ ] Atualizar `app/Models/User.php` com HasApiTokens
- [ ] Rodar migration: `./vendor/bin/sail artisan migrate`

---

### 3. Custom Exceptions

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Credenciais inválidas
 */
class InvalidCredentialsException extends Exception
{
    protected int $statusCode = 401;

    public function __construct()
    {
        parent::__construct('Invalid email or password');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'invalid_credentials',
        ], $this->statusCode);
    }
}

/**
 * Conta inativa
 */
class InactiveAccountException extends Exception
{
    protected int $statusCode = 403;

    public function __construct()
    {
        parent::__construct('Your account is inactive. Please contact support.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'account_inactive',
        ], $this->statusCode);
    }
}

/**
 * Email já cadastrado
 */
class EmailAlreadyExistsException extends Exception
{
    protected int $statusCode = 422;

    public function __construct(string $email)
    {
        parent::__construct("Email '{$email}' is already registered");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'email_already_exists',
        ], $this->statusCode);
    }
}
```

**Tarefas:**
- [ ] Criar `app/Exceptions/InvalidCredentialsException.php`
- [ ] Criar `app/Exceptions/InactiveAccountException.php`
- [ ] Criar `app/Exceptions/EmailAlreadyExistsException.php`
- [ ] Registrar no `app/Exceptions/Handler.php`:
```php
$this->renderable(function (InvalidCredentialsException $e) {
    return $e->render();
});
$this->renderable(function (InactiveAccountException $e) {
    return $e->render();
});
$this->renderable(function (EmailAlreadyExistsException $e) {
    return $e->render();
});
```

---

### 4. DTOs

```php
<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

/**
 * DTO para registro de usuário
 */
readonly class RegisterDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password, // Será hasheado na Action
            'is_active' => true,
        ];
    }
}

/**
 * DTO para login
 */
readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public string $device = 'web',
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            email: $data['email'],
            password: $data['password'],
            device: $data['device'] ?? 'web',
        );
    }
}
```

**Tarefas:**
- [ ] Criar `app/DTOs/Auth/RegisterDTO.php`
- [ ] Criar `app/DTOs/Auth/LoginDTO.php`

---

### 5. Actions (Write Operations)

#### 5.1 RegisterUserAction
```php
<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\RegisterDTO;
use App\Events\UserRegistered;
use App\Exceptions\EmailAlreadyExistsException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Action: Registrar novo usuário
 *
 * Retorna: User model
 * Lança: EmailAlreadyExistsException
 */
class RegisterUserAction
{
    /**
     * Registrar usuário
     *
     * @throws EmailAlreadyExistsException
     */
    public function execute(RegisterDTO $dto): User
    {
        // Verificar se email já existe
        if (User::where('email', $dto->email)->exists()) {
            throw new EmailAlreadyExistsException($dto->email);
        }

        return DB::transaction(function () use ($dto) {
            $user = User::create([
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => Hash::make($dto->password),
                'is_active' => true,
            ]);

            event(new UserRegistered($user));

            return $user;
        });
    }
}
```

#### 5.2 LoginUserAction
```php
<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\LoginDTO;
use App\Exceptions\InactiveAccountException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Action: Autenticar usuário
 *
 * Retorna: array com user e token
 * Lança: InvalidCredentialsException, InactiveAccountException
 */
class LoginUserAction
{
    /**
     * Autenticar e gerar token
     *
     * @throws InvalidCredentialsException
     * @throws InactiveAccountException
     */
    public function execute(LoginDTO $dto): array
    {
        // Tentar autenticar
        if (!Auth::attempt(['email' => $dto->email, 'password' => $dto->password])) {
            Log::warning('Failed login attempt', ['email' => $dto->email]);
            throw new InvalidCredentialsException();
        }

        /** @var User $user */
        $user = Auth::user();

        // Verificar se conta está ativa
        if (!$user->isActive()) {
            Auth::logout();
            Log::warning('Inactive user tried to login', ['user_id' => $user->id]);
            throw new InactiveAccountException();
        }

        // Gerar token
        $token = $user->createToken($dto->device)->plainTextToken;

        Log::info('User logged in successfully', [
            'user_id' => $user->id,
            'device' => $dto->device,
        ]);

        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }
}
```

#### 5.3 LogoutUserAction
```php
<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;

/**
 * Action: Fazer logout do usuário (revogar token atual)
 *
 * Retorna: bool (sucesso)
 */
class LogoutUserAction
{
    /**
     * Revogar token atual
     */
    public function execute(User $user): bool
    {
        return $user->currentAccessToken()->delete();
    }
}
```

#### 5.4 LogoutAllDevicesAction
```php
<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Action: Fazer logout de todos os dispositivos
 *
 * Retorna: void
 */
class LogoutAllDevicesAction
{
    /**
     * Revogar todos os tokens do usuário
     */
    public function execute(User $user): void
    {
        $count = $user->tokens()->count();
        $user->tokens()->delete();

        Log::info('User logged out from all devices', [
            'user_id' => $user->id,
            'tokens_revoked' => $count,
        ]);
    }
}
```

#### 5.5 RefreshTokenAction
```php
<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;

/**
 * Action: Renovar token de autenticação
 *
 * Retorna: string (novo token)
 */
class RefreshTokenAction
{
    /**
     * Revogar token atual e criar novo
     */
    public function execute(User $user, string $device = 'web'): string
    {
        // Revogar token atual
        $user->currentAccessToken()->delete();

        // Criar novo token
        return $user->createToken($device)->plainTextToken;
    }
}
```

**Tarefas:**
- [ ] Criar `app/Actions/Auth/RegisterUserAction.php`
- [ ] Criar `app/Actions/Auth/LoginUserAction.php`
- [ ] Criar `app/Actions/Auth/LogoutUserAction.php`
- [ ] Criar `app/Actions/Auth/LogoutAllDevicesAction.php`
- [ ] Criar `app/Actions/Auth/RefreshTokenAction.php`

---

### 6. Queries (Read Operations)

```php
<?php

declare(strict_types=1);

namespace App\Queries\Auth;

use App\Models\User;

/**
 * Query: Buscar usuário por email
 */
class GetUserByEmailQuery
{
    /**
     * Buscar usuário por email
     */
    public function execute(string $email): ?User
    {
        return User::where('email', $email)->first();
    }
}

/**
 * Query: Buscar usuário por ID
 */
class GetUserByIdQuery
{
    /**
     * Buscar usuário por ID
     */
    public function execute(int $id): ?User
    {
        return User::find($id);
    }
}
```

**Tarefas:**
- [ ] Criar `app/Queries/Auth/GetUserByEmailQuery.php`
- [ ] Criar `app/Queries/Auth/GetUserByIdQuery.php`

---

### 7. Events

```php
<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event: Usuário registrado
 */
class UserRegistered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly User $user
    ) {}
}
```

**Tarefas:**
- [ ] Criar `app/Events/UserRegistered.php`

---

### 8. Form Requests

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
            'name.min' => 'Name must be at least 3 characters',
            'email.required' => 'Email is required',
            'email.email' => 'Email must be valid',
            'email.unique' => 'Email is already registered',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed' => 'Password confirmation does not match',
        ];
    }
}

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email is required',
            'email.email' => 'Email must be valid',
            'password.required' => 'Password is required',
        ];
    }
}
```

**Tarefas:**
- [ ] Criar `app/Http/Requests/Auth/RegisterRequest.php`
- [ ] Criar `app/Http/Requests/Auth/LoginRequest.php`

---

### 9. API Resources

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
```

**Tarefas:**
- [ ] Criar `app/Http/Resources/UserResource.php`

---

### 10. Controller (HTTP Layer)

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\LoginUserAction;
use App\Actions\Auth\LogoutAllDevicesAction;
use App\Actions\Auth\LogoutUserAction;
use App\Actions\Auth\RefreshTokenAction;
use App\Actions\Auth\RegisterUserAction;
use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Registrar novo usuário
     */
    public function register(
        RegisterRequest $request,
        RegisterUserAction $action
    ): JsonResponse {
        $dto = RegisterDTO::fromRequest($request->validated());
        $user = $action->execute($dto);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Login (gerar token)
     */
    public function login(
        LoginRequest $request,
        LoginUserAction $action
    ): JsonResponse {
        $dto = LoginDTO::fromRequest($request->validated());
        $data = $action->execute($dto);

        return response()->json([
            'message' => 'Login successful',
            'user' => new UserResource($data['user']),
            'token' => $data['token'],
            'token_type' => $data['token_type'],
        ]);
    }

    /**
     * Logout (revogar token atual)
     */
    public function logout(
        Request $request,
        LogoutUserAction $action
    ): JsonResponse {
        $action->execute($request->user());

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Logout de todos os dispositivos
     */
    public function logoutAll(
        Request $request,
        LogoutAllDevicesAction $action
    ): JsonResponse {
        $action->execute($request->user());

        return response()->json([
            'message' => 'Logged out from all devices successfully',
        ]);
    }

    /**
     * Obter perfil do usuário autenticado
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * Renovar token
     */
    public function refreshToken(
        Request $request,
        RefreshTokenAction $action
    ): JsonResponse {
        $device = $request->input('device', 'web');
        $token = $action->execute($request->user(), $device);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
```

**Tarefas:**
- [ ] Criar `app/Http/Controllers/Api/V1/AuthController.php`

---

### 11. Policies (Authorization)

#### 11.1 CustomerPolicy
```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine if user can view any customers
     */
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    /**
     * Determine if user can view the customer
     */
    public function view(User $user, Customer $customer): bool
    {
        return $user->isActive();
    }

    /**
     * Determine if user can create customers
     */
    public function create(User $user): bool
    {
        return $user->isActive();
    }

    /**
     * Determine if user can update the customer
     */
    public function update(User $user, Customer $customer): bool
    {
        return $user->isActive();
    }

    /**
     * Determine if user can delete the customer
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->isActive();
    }
}
```

#### 11.2 ChargePolicy
```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Charge;
use App\Models\User;

class ChargePolicy
{
    /**
     * Determine if user can view any charges
     */
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    /**
     * Determine if user can view the charge
     */
    public function view(User $user, Charge $charge): bool
    {
        return $user->isActive();
    }

    /**
     * Determine if user can create charges
     */
    public function create(User $user): bool
    {
        return $user->isActive();
    }

    /**
     * Determine if user can update the charge
     */
    public function update(User $user, Charge $charge): bool
    {
        // Não pode atualizar cobrança paga
        return $user->isActive() && $charge->canBeUpdated();
    }

    /**
     * Determine if user can cancel the charge
     */
    public function cancel(User $user, Charge $charge): bool
    {
        return $user->isActive() && $charge->canBeCancelled();
    }

    /**
     * Determine if user can delete the charge
     */
    public function delete(User $user, Charge $charge): bool
    {
        return $user->isActive() && $charge->canBeCancelled();
    }
}
```

**Tarefas:**
- [ ] Criar `app/Policies/CustomerPolicy.php`
- [ ] Criar `app/Policies/ChargePolicy.php`
- [ ] Registrar policies em `app/Providers/AuthServiceProvider.php`:
```php
protected $policies = [
    Customer::class => CustomerPolicy::class,
    Charge::class => ChargePolicy::class,
];
```

---

### 12. Atualizar Controllers com Authorization

**CustomerController.php:**
```php
public function index(ListCustomersRequest $request, GetCustomersWithFiltersQuery $query)
{
    $this->authorize('viewAny', Customer::class);

    // ... resto do código
}

public function show(int $id, GetCustomerByIdQuery $query)
{
    $customer = $query->execute($id);

    if (!$customer) {
        throw new CustomerNotFoundException($id);
    }

    $this->authorize('view', $customer);

    return new CustomerResource($customer);
}

public function store(StoreCustomerRequest $request, CreateCustomerAction $action)
{
    $this->authorize('create', Customer::class);

    // ... resto do código
}

public function update(int $id, UpdateCustomerRequest $request, UpdateCustomerAction $action)
{
    $customer = Customer::findOrFail($id);
    $this->authorize('update', $customer);

    // ... resto do código
}

public function destroy(int $id, DeleteCustomerAction $action)
{
    $customer = Customer::findOrFail($id);
    $this->authorize('delete', $customer);

    // ... resto do código
}
```

**ChargeController.php:**
```php
public function index(ListChargesRequest $request, GetChargesWithFiltersQuery $query)
{
    $this->authorize('viewAny', Charge::class);

    // ... resto do código
}

public function update(int $id, UpdateChargeRequest $request, UpdateChargeAction $action)
{
    $charge = Charge::findOrFail($id);
    $this->authorize('update', $charge);

    // ... resto do código
}

public function cancel(int $id, CancelChargeRequest $request, CancelChargeAction $action)
{
    $charge = Charge::findOrFail($id);
    $this->authorize('cancel', $charge);

    // ... resto do código
}
```

**Tarefas:**
- [ ] Adicionar `$this->authorize()` em todos os métodos do `CustomerController`
- [ ] Adicionar `$this->authorize()` em todos os métodos do `ChargeController`

---

### 13. Routes (API)

```php
<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChargeController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

// Public routes (sem autenticação)
Route::prefix('v1')->group(function () {
    // Auth routes com rate limiting mais agressivo
    Route::middleware('throttle:auth')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
    });
});

// Protected routes (com autenticação)
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Auth routes
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/refresh', [AuthController::class, 'refreshToken']);

    // Customers CRUD
    Route::apiResource('customers', CustomerController::class);

    // Charges CRUD
    Route::apiResource('charges', ChargeController::class);

    // Charges - Ações adicionais
    Route::post('charges/{id}/cancel', [ChargeController::class, 'cancel']);
    Route::post('charges/{id}/sync', [ChargeController::class, 'syncWithGateway']);

    // Cobranças de um cliente
    Route::get('customers/{id}/charges', [CustomerController::class, 'charges']);
});

// Webhooks (sem autenticação, mas com validação de assinatura)
Route::prefix('webhooks')->group(function () {
    Route::post('pagseguro', [WebhookController::class, 'pagseguro'])
        ->middleware('validate.webhook.signature:pagseguro');

    Route::post('asaas', [WebhookController::class, 'asaas'])
        ->middleware('validate.webhook.signature:asaas');

    Route::post('stone', [WebhookController::class, 'stone'])
        ->middleware('validate.webhook.signature:stone');
});
```

**Tarefas:**
- [ ] Atualizar `routes/api.php` com rotas de autenticação
- [ ] Aplicar middleware `auth:sanctum` nas rotas protegidas
- [ ] Manter webhooks sem autenticação (usam validação de assinatura)

---

### 14. Rate Limiting

**Configurar em `bootstrap/app.php` (Laravel 11) ou `RouteServiceProvider` (Laravel 10):**
```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

// Laravel 11 - bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiter::for('auth', function (Request $request) {
        return Limit::perMinute(5)->by($request->ip())
            ->response(function () {
                return response()->json([
                    'message' => 'Too many login attempts. Please try again later.',
                ], 429);
            });
    });
})
```

**Tarefas:**
- [ ] Configurar rate limiter `api` (60 req/min)
- [ ] Configurar rate limiter `auth` (5 req/min para login/register)
- [ ] Aplicar nas rotas

---

### 15. Exception Handler

```php
// app/Exceptions/Handler.php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

public function register(): void
{
    // Unauthenticated (401)
    $this->renderable(function (AuthenticationException $e, Request $request) {
        if ($request->is('api/*')) {
            return response()->json([
                'message' => 'Unauthenticated. Please login.',
                'error' => 'unauthenticated',
            ], 401);
        }
    });

    // Unauthorized (403)
    $this->renderable(function (AuthorizationException $e, Request $request) {
        if ($request->is('api/*')) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
                'error' => 'unauthorized',
            ], 403);
        }
    });

    // Rate Limit (429)
    $this->renderable(function (TooManyRequestsHttpException $e, Request $request) {
        if ($request->is('api/*')) {
            return response()->json([
                'message' => 'Too many requests. Please slow down.',
                'error' => 'rate_limit_exceeded',
            ], 429);
        }
    });

    // Auth exceptions customizadas
    $this->renderable(function (InvalidCredentialsException $e) {
        return $e->render();
    });

    $this->renderable(function (InactiveAccountException $e) {
        return $e->render();
    });

    $this->renderable(function (EmailAlreadyExistsException $e) {
        return $e->render();
    });
}
```

**Tarefas:**
- [ ] Adicionar renderables no `app/Exceptions/Handler.php`

---

### 16. Testes

#### 16.1 Feature Test - Authentication
```php
<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['message', 'user' => ['id', 'name', 'email']]);
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_user_cannot_register_with_existing_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'email_already_exists']);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['message', 'user', 'token', 'token_type']);
    }

    public function test_user_cannot_login_with_wrong_credentials(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'invalid_credentials']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'account_inactive']);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_user_can_get_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJson(['user' => ['id' => $user->id]]);
    }

    public function test_user_can_refresh_token(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->postJson('/api/v1/auth/refresh');

        $response->assertOk();
        $response->assertJsonStructure(['token', 'token_type']);
        $this->assertNotEquals($oldToken, $response->json('token'));
    }
}
```

#### 16.2 Feature Test - Authorization
```php
<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ChargeStatus;
use App\Models\Charge;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/v1/customers');

        $response->assertStatus(401);
        $response->assertJson(['error' => 'unauthenticated']);
    }

    public function test_inactive_user_cannot_list_customers(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/customers');

        $response->assertStatus(403);
    }

    public function test_cannot_update_paid_charge(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $customer = Customer::factory()->create();
        $charge = Charge::factory()->create([
            'customer_id' => $customer->id,
            'status' => ChargeStatus::PAID,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/charges/{$charge->id}", [
                'amount' => 200.00,
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_cancel_paid_charge(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $customer = Customer::factory()->create();
        $charge = Charge::factory()->create([
            'customer_id' => $customer->id,
            'status' => ChargeStatus::PAID,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/charges/{$charge->id}/cancel");

        $response->assertStatus(403);
    }
}
```

**Tarefas:**
- [ ] Criar `tests/Feature/Api/V1/AuthTest.php`
- [ ] Criar `tests/Feature/Api/V1/AuthorizationTest.php`
- [ ] Criar `tests/Unit/Actions/Auth/RegisterUserActionTest.php`
- [ ] Criar `tests/Unit/Actions/Auth/LoginUserActionTest.php`
- [ ] Rodar testes: `./vendor/bin/sail artisan test`

---

## Checklist de Qualidade

### Arquitetura
- [ ] **Actions** retornam Models ou arrays (não JsonResponse)
- [ ] **Queries** usam Eloquent diretamente
- [ ] **Custom Exceptions** com render()
- [ ] Policies para autorização (não lógica inline)

### Segurança
- [ ] Passwords hasheados (Hash::make)
- [ ] Tokens seguros (Sanctum)
- [ ] Rate limiting em auth endpoints (5 req/min)
- [ ] Rate limiting em API endpoints (60 req/min)
- [ ] HTTPS obrigatório em produção
- [ ] Logs de tentativas de login
- [ ] Validação de email único
- [ ] Senha mínima 8 caracteres

### Authorization
- [ ] Policies criadas para Customer e Charge
- [ ] `$this->authorize()` em todos os controllers
- [ ] Regras de negócio aplicadas (não pode atualizar/cancelar charge paga)
- [ ] Usuário inativo não pode fazer nada

### Código
- [ ] Type hints completos (PHP 8.2+)
- [ ] Readonly DTOs
- [ ] Exception handling robusto
- [ ] Logging adequado

### Testes
- [ ] Feature tests de auth (register, login, logout, etc.)
- [ ] Feature tests de authorization (policies)
- [ ] Unit tests de Actions
- [ ] Cobertura > 80%

---

## Critérios de Aceitação

✅ **Funcionalidade**
- Registro funcionando (cria usuário, retorna 201)
- Login retornando token
- Logout revogando token
- Refresh token funcionando
- Rotas protegidas bloqueando não autenticados (401)
- Policies funcionando (usuários inativos não podem acessar, 403)

✅ **Segurança**
- Passwords hasheados no banco
- Tokens expiram (se configurado)
- Rate limiting ativo (5 req/min login, 60 req/min API)
- Email já cadastrado retorna 422
- Credenciais inválidas retornam 401
- Conta inativa retorna 403

✅ **Autorização**
- Não pode atualizar cobrança paga
- Não pode cancelar cobrança paga
- Usuário inativo não consegue fazer login
- Usuário inativo não pode acessar recursos (403)

✅ **API**
- Endpoints retornando JSON correto
- Status codes apropriados (201, 401, 403, 429)
- Mensagens de erro claras

---

## Exemplos de Uso da API

```bash
# Registrar
POST /api/v1/auth/register
{
  "name": "João Silva",
  "email": "joao@example.com",
  "password": "senha12345",
  "password_confirmation": "senha12345"
}

# Response 201
{
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "name": "João Silva",
    "email": "joao@example.com",
    "is_active": true,
    ...
  }
}

# Login
POST /api/v1/auth/login
{
  "email": "joao@example.com",
  "password": "senha12345",
  "device": "mobile"
}

# Response 200
{
  "message": "Login successful",
  "user": { ... },
  "token": "1|abc123...",
  "token_type": "Bearer"
}

# Usar token nas requisições
GET /api/v1/customers
Authorization: Bearer 1|abc123...

# Obter perfil
GET /api/v1/auth/me
Authorization: Bearer 1|abc123...

# Renovar token
POST /api/v1/auth/refresh
Authorization: Bearer 1|abc123...
{
  "device": "web"
}

# Logout
POST /api/v1/auth/logout
Authorization: Bearer 1|abc123...

# Logout de todos os dispositivos
POST /api/v1/auth/logout-all
Authorization: Bearer 1|abc123...

# Erros comuns

# Credenciais inválidas - 401
{
  "message": "Invalid email or password",
  "error": "invalid_credentials"
}

# Conta inativa - 403
{
  "message": "Your account is inactive. Please contact support.",
  "error": "account_inactive"
}

# Não autenticado - 401
{
  "message": "Unauthenticated. Please login.",
  "error": "unauthenticated"
}

# Sem permissão - 403
{
  "message": "You do not have permission to perform this action.",
  "error": "unauthorized"
}

# Rate limit excedido - 429
{
  "message": "Too many requests. Please slow down.",
  "error": "rate_limit_exceeded"
}
```

---

## Notas Importantes

### ⚠️ Actions vs Services

**Neste projeto:**
- ✅ **Actions**: Lógica de negócio (RegisterUserAction, LoginUserAction)
  - Retornam Models ou arrays simples
  - Lançam Custom Exceptions
  - Reutilizáveis

- ❌ **Services**: REMOVIDOS
  - Antigamente: `AuthService->login()`
  - Agora: `LoginUserAction->execute()`

### ⚠️ Segurança CRÍTICA

**Sempre:**
- Usar HTTPS em produção
- Rate limiting em endpoints de auth
- Logar tentativas de login falhas
- Hash passwords com `Hash::make()`
- Validar força da senha (min 8 caracteres)
- Revogar tokens no logout
- Verificar se conta está ativa

**Nunca:**
- Retornar detalhes de erro que ajudem atacantes
- Logar passwords (nem hasheados)
- Permitir tentativas ilimitadas de login

### ⚠️ Rate Limiting

**Configurado:**
- **Auth endpoints** (login/register): 5 requisições/minuto por IP
- **API endpoints** (protegidos): 60 requisições/minuto por usuário/IP

**Por quê?**
- Previne brute force em login
- Previne abuso da API
- Retorna 429 (Too Many Requests)

### ⚠️ Policies vs Gates

**Neste projeto: apenas Policies**
- Policies são classes organizadas por Model
- Gates são closures no AuthServiceProvider
- Policies escalam melhor e são testáveis

### ⚠️ Token Expiration

**Opcional mas recomendado:**
```env
# .env
SANCTUM_TOKEN_EXPIRATION=60  # 60 minutos
```

**Se configurado:**
- Tokens expiram automaticamente
- Frontend deve renovar token periodicamente
- Usar `RefreshTokenAction` para renovar

---

## Comandos Úteis

```bash
# Instalar Sanctum
./vendor/bin/sail composer require laravel/sanctum
./vendor/bin/sail artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Migrations
./vendor/bin/sail artisan migrate

# Criar policy
./vendor/bin/sail artisan make:policy CustomerPolicy --model=Customer

# Listar tokens de um usuário (tinker)
./vendor/bin/sail artisan tinker
>>> $user = User::find(1);
>>> $user->tokens;

# Revogar todos os tokens de um usuário
>>> $user->tokens()->delete();

# Rodar testes
./vendor/bin/sail artisan test --filter=Auth
```

---

## Referências

- [Laravel Sanctum](https://laravel.com/docs/11.x/sanctum): Documentação oficial
- [Laravel Authorization](https://laravel.com/docs/11.x/authorization): Policies e Gates
- [OWASP Authentication](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html): Best practices
- [Prompt.MD](../Prompt.MD): Arquitetura completa do projeto
