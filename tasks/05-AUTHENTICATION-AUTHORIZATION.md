# 05 - Authentication & Authorization

## Objetivo
Implementar autenticação via Laravel Sanctum e sistema de autorização com policies e gates.

## Prioridade
🟡 MÉDIA-ALTA - Necessário antes de produção

## Dependências
- Task 00 (Setup Inicial)

---

## Ordem de Implementação

### 1. Sanctum Setup
- [ ] Instalar Laravel Sanctum
  ```bash
  composer require laravel/sanctum
  php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
  php artisan migrate
  ```

- [ ] Configurar em `config/sanctum.php`
  ```php
  'expiration' => env('SANCTUM_TOKEN_EXPIRATION', null), // null = sem expiração
  'middleware' => [
      'encrypt_cookies',
      'verify_csrf_token' => \App\Http\Middleware\VerifyCsrfToken::class,
  ],
  ```

### 2. User Model
- [ ] Atualizar `app/Models/User.php`
  ```php
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

      public function scopeActive(Builder $query): void
      {
          $query->where('is_active', true);
      }
  }
  ```

- [ ] Criar migration para adicionar `is_active` à tabela users
  ```php
  Schema::table('users', function (Blueprint $table) {
      $table->boolean('is_active')->default(true)->after('password');
  });
  ```

### 3. DTOs
- [ ] Criar `app/DTOs/Auth/LoginDTO.php`
  ```php
  readonly class LoginDTO
  {
      public function __construct(
          public string $email,
          public string $password,
          public ?string $device = null
      ) {}

      public static function fromRequest(array $data): self
      {
          return new self(
              email: $data['email'],
              password: $data['password'],
              device: $data['device'] ?? 'web'
          );
      }
  }
  ```

- [ ] Criar `app/DTOs/Auth/RegisterDTO.php`
  ```php
  readonly class RegisterDTO
  {
      public function __construct(
          public string $name,
          public string $email,
          public string $password
      ) {}

      public static function fromRequest(array $data): self;
  }
  ```

### 4. Services
- [ ] Criar `app/Services/Auth/AuthService.php`
  ```php
  class AuthService
  {
      public function __construct(
          private readonly UserRepositoryInterface $userRepository
      ) {}

      public function register(RegisterDTO $dto): User
      {
          $user = $this->userRepository->create([
              'name' => $dto->name,
              'email' => $dto->email,
              'password' => Hash::make($dto->password),
              'is_active' => true,
          ]);

          event(new UserRegistered($user));

          return $user;
      }

      public function login(LoginDTO $dto): array
      {
          if (!Auth::attempt(['email' => $dto->email, 'password' => $dto->password])) {
              throw new AuthenticationException('Invalid credentials');
          }

          $user = Auth::user();

          if (!$user->is_active) {
              throw new AuthenticationException('Account is inactive');
          }

          $token = $user->createToken($dto->device)->plainTextToken;

          return [
              'user' => $user,
              'token' => $token,
              'token_type' => 'Bearer',
          ];
      }

      public function logout(User $user): bool
      {
          return $user->currentAccessToken()->delete();
      }

      public function logoutAll(User $user): void
      {
          $user->tokens()->delete();
      }

      public function refreshToken(User $user, string $device = 'web'): string
      {
          $user->currentAccessToken()->delete();
          return $user->createToken($device)->plainTextToken;
      }
  }
  ```

### 5. Form Requests
- [ ] Criar `app/Http/Requests/Auth/RegisterRequest.php`
  ```php
  public function rules(): array
  {
      return [
          'name' => ['required', 'string', 'min:3', 'max:255'],
          'email' => ['required', 'email', 'unique:users,email'],
          'password' => ['required', 'string', 'min:8', 'confirmed'],
      ];
  }
  ```

- [ ] Criar `app/Http/Requests/Auth/LoginRequest.php`
  ```php
  public function rules(): array
  {
      return [
          'email' => ['required', 'email'],
          'password' => ['required', 'string'],
          'device' => ['nullable', 'string', 'max:255'],
      ];
  }
  ```

### 6. Controllers
- [ ] Criar `app/Http/Controllers/Api/V1/AuthController.php`
  ```php
  class AuthController extends Controller
  {
      public function __construct(
          private readonly AuthService $authService
      ) {}

      public function register(RegisterRequest $request): JsonResponse
      {
          $dto = RegisterDTO::fromRequest($request->validated());
          $user = $this->authService->register($dto);

          return response()->json([
              'message' => 'User registered successfully',
              'user' => new UserResource($user),
          ], 201);
      }

      public function login(LoginRequest $request): JsonResponse
      {
          $dto = LoginDTO::fromRequest($request->validated());
          $data = $this->authService->login($dto);

          return response()->json([
              'message' => 'Login successful',
              'user' => new UserResource($data['user']),
              'token' => $data['token'],
              'token_type' => $data['token_type'],
          ]);
      }

      public function logout(Request $request): JsonResponse
      {
          $this->authService->logout($request->user());

          return response()->json([
              'message' => 'Logout successful',
          ]);
      }

      public function logoutAll(Request $request): JsonResponse
      {
          $this->authService->logoutAll($request->user());

          return response()->json([
              'message' => 'All sessions logged out',
          ]);
      }

      public function me(Request $request): JsonResponse
      {
          return response()->json([
              'user' => new UserResource($request->user()),
          ]);
      }

      public function refreshToken(Request $request): JsonResponse
      {
          $device = $request->input('device', 'web');
          $token = $this->authService->refreshToken($request->user(), $device);

          return response()->json([
              'token' => $token,
              'token_type' => 'Bearer',
          ]);
      }
  }
  ```

### 7. Policies
- [ ] Criar `app/Policies/CustomerPolicy.php`
  ```php
  class CustomerPolicy
  {
      public function viewAny(User $user): bool
      {
          return $user->is_active;
      }

      public function view(User $user, Customer $customer): bool
      {
          return $user->is_active;
      }

      public function create(User $user): bool
      {
          return $user->is_active;
      }

      public function update(User $user, Customer $customer): bool
      {
          return $user->is_active;
      }

      public function delete(User $user, Customer $customer): bool
      {
          return $user->is_active;
      }
  }
  ```

- [ ] Criar `app/Policies/ChargePolicy.php`
  ```php
  class ChargePolicy
  {
      public function viewAny(User $user): bool
      {
          return $user->is_active;
      }

      public function view(User $user, Charge $charge): bool
      {
          return $user->is_active;
      }

      public function create(User $user): bool
      {
          return $user->is_active;
      }

      public function update(User $user, Charge $charge): bool
      {
          // Não pode atualizar cobrança paga
          return $user->is_active && !$charge->isPaid();
      }

      public function cancel(User $user, Charge $charge): bool
      {
          return $user->is_active && $charge->canBeCancelled();
      }
  }
  ```

- [ ] Registrar policies em `AuthServiceProvider`
  ```php
  protected $policies = [
      Customer::class => CustomerPolicy::class,
      Charge::class => ChargePolicy::class,
  ];
  ```

### 8. Atualizar Controllers com Authorization
- [ ] `CustomerController.php`
  ```php
  public function index()
  {
      $this->authorize('viewAny', Customer::class);
      // ...
  }

  public function store(StoreCustomerRequest $request)
  {
      $this->authorize('create', Customer::class);
      // ...
  }

  public function show(int $id)
  {
      $customer = $this->queryService->findById($id);
      $this->authorize('view', $customer);
      // ...
  }
  ```

- [ ] `ChargeController.php`
  ```php
  public function update(UpdateChargeRequest $request, int $id)
  {
      $charge = $this->queryService->findById($id);
      $this->authorize('update', $charge);
      // ...
  }

  public function cancel(int $id)
  {
      $charge = $this->queryService->findById($id);
      $this->authorize('cancel', $charge);
      // ...
  }
  ```

### 9. Routes
- [ ] Atualizar `routes/api.php`
  ```php
  Route::prefix('v1')->group(function () {
      // Rotas públicas
      Route::post('auth/register', [AuthController::class, 'register']);
      Route::post('auth/login', [AuthController::class, 'login']);

      // Rotas protegidas
      Route::middleware('auth:sanctum')->group(function () {
          Route::post('auth/logout', [AuthController::class, 'logout']);
          Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);
          Route::get('auth/me', [AuthController::class, 'me']);
          Route::post('auth/refresh', [AuthController::class, 'refreshToken']);

          Route::apiResource('customers', CustomerController::class);
          Route::apiResource('charges', ChargeController::class);
          Route::get('customers/{customer}/charges', [CustomerController::class, 'charges']);
      });
  });

  // Webhooks (sem autenticação)
  Route::prefix('webhooks')->group(function () {
      // ...
  });
  ```

### 10. Rate Limiting
- [ ] Configurar em `app/Providers/RouteServiceProvider.php`
  ```php
  protected function configureRateLimiting(): void
  {
      RateLimiter::for('api', function (Request $request) {
          return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
      });

      RateLimiter::for('auth', function (Request $request) {
          return Limit::perMinute(5)->by($request->ip());
      });
  }
  ```

- [ ] Aplicar rate limiting
  ```php
  Route::middleware(['throttle:auth'])->group(function () {
      Route::post('auth/login', [AuthController::class, 'login']);
      Route::post('auth/register', [AuthController::class, 'register']);
  });

  Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
      // Rotas protegidas
  });
  ```

### 11. Exception Handling
- [ ] Atualizar `app/Exceptions/Handler.php`
  ```php
  public function register(): void
  {
      $this->renderable(function (AuthenticationException $e, Request $request) {
          if ($request->is('api/*')) {
              return response()->json([
                  'message' => $e->getMessage() ?: 'Unauthenticated',
              ], 401);
          }
      });

      $this->renderable(function (AuthorizationException $e, Request $request) {
          if ($request->is('api/*')) {
              return response()->json([
                  'message' => 'Unauthorized',
              ], 403);
          }
      });
  }
  ```

### 12. Resources
- [ ] Criar `app/Http/Resources/UserResource.php`
  ```php
  public function toArray(Request $request): array
  {
      return [
          'id' => $this->id,
          'name' => $this->name,
          'email' => $this->email,
          'is_active' => $this->is_active,
          'email_verified_at' => $this->email_verified_at?->toIso8601String(),
          'created_at' => $this->created_at->toIso8601String(),
      ];
  }
  ```

### 13. Testes
- [ ] Criar `tests/Feature/Api/V1/AuthTest.php`
  - `test_user_can_register()`
  - `test_user_can_login()`
  - `test_user_can_logout()`
  - `test_user_can_get_profile()`
  - `test_user_can_refresh_token()`
  - `test_inactive_user_cannot_login()`
  - `test_invalid_credentials_rejected()`
  - `test_validates_registration_fields()`
  - `test_prevents_duplicate_email()`

- [ ] Criar `tests/Feature/Api/V1/AuthorizationTest.php`
  - `test_unauthorized_user_cannot_access_protected_routes()`
  - `test_cannot_update_paid_charge()`
  - `test_cannot_cancel_paid_charge()`
  - `test_inactive_user_cannot_perform_actions()`

---

## Checklist de Qualidade

### Segurança
- [ ] Passwords hasheados
- [ ] Tokens seguros (Sanctum)
- [ ] Rate limiting em auth endpoints
- [ ] Validação de email único
- [ ] Proteção contra brute force
- [ ] HTTPS obrigatório em produção

### Authorization
- [ ] Policies criadas
- [ ] Gates registrados
- [ ] Authorize em todos os controllers
- [ ] Regras de negócio aplicadas

### Código
- [ ] Type hints completos
- [ ] DTOs para auth
- [ ] Service layer
- [ ] Exception handling apropriado

### Testes
- [ ] Feature tests de auth
- [ ] Authorization tests
- [ ] Rate limiting tests
- [ ] Cobertura > 80%

---

## Critérios de Aceitação

✅ **Funcionalidade**
- Registro funcionando
- Login retornando token
- Logout invalidando token
- Rotas protegidas bloqueando não autenticados
- Policies funcionando

✅ **Segurança**
- Passwords hasheados
- Tokens expiram (se configurado)
- Rate limiting ativo
- CORS configurado

✅ **API**
- Endpoints retornando JSON correto
- Status codes apropriados (401, 403)
- Mensagens de erro claras

---

## Exemplos de Uso

```bash
# Registrar
POST /api/v1/auth/register
{
  "name": "João Silva",
  "email": "joao@example.com",
  "password": "senha123",
  "password_confirmation": "senha123"
}

# Login
POST /api/v1/auth/login
{
  "email": "joao@example.com",
  "password": "senha123",
  "device": "mobile"
}

# Usar token
GET /api/v1/customers
Authorization: Bearer <token>

# Logout
POST /api/v1/auth/logout
Authorization: Bearer <token>

# Perfil
GET /api/v1/auth/me
Authorization: Bearer <token>
```

---

## Notas Importantes

⚠️ **Atenção**
- Sempre usar HTTPS em produção
- Configurar CORS adequadamente
- Rate limiting em endpoints de auth
- Logs de tentativas de login
- Expiração de tokens (opcional mas recomendado)
- Revogar tokens em logout
- Validar força da senha

📚 **Referências**
- Laravel Sanctum Documentation
- Laravel Authorization
- OWASP Authentication Best Practices
