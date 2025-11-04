# 03 - Payment Gateway Domain (Domínio de Gateways de Pagamento)

## Objetivo
Implementar a infraestrutura de integração com gateways de pagamento usando Strategy Pattern e Factory Pattern.

## Prioridade
🔴 ALTA - Necessário para processar pagamentos

## Dependências
- Task 00 (Setup Inicial)
- Task 01 (Customer Domain)
- Task 02 (Charge Domain)

---

## Ordem de Implementação

### 1. Enum
- [ ] Criar `app/Enums/PaymentGatewayType.php`
  ```php
  enum PaymentGatewayType: string
  {
      case PAGSEGURO = 'pagseguro';
      case ASAAS = 'asaas';
      case STONE = 'stone';
  }
  ```

### 2. Exceptions
- [ ] Criar `app/Exceptions/PaymentGatewayException.php`
- [ ] Criar `app/Exceptions/GatewayConnectionException.php`
- [ ] Criar `app/Exceptions/GatewayAuthenticationException.php`
- [ ] Criar `app/Exceptions/InvalidGatewayResponseException.php`

### 3. Migration & Model
- [ ] Criar migration `create_payment_gateways_table`
  ```php
  Schema::create('payment_gateways', function (Blueprint $table) {
      $table->id();
      $table->enum('type', ['pagseguro', 'asaas', 'stone'])->unique();
      $table->string('name');
      $table->boolean('is_active')->default(true);
      $table->json('credentials')->nullable(); // Criptografado
      $table->json('settings')->nullable();
      $table->timestamps();

      $table->index('type');
      $table->index('is_active');
  });
  ```

- [ ] Criar `app/Models/PaymentGateway.php`
  - Casts: type -> PaymentGatewayType, credentials -> encrypted:array, settings -> array
  - Scope `scopeActive()`
  - Scope `scopeByType(PaymentGatewayType $type)`
  - Relacionamento `hasMany(Charge::class)`

### 4. Interface Padrão (Strategy Pattern)
- [ ] Criar `app/Services/PaymentGateway/Contracts/PaymentGatewayInterface.php`
  ```php
  interface PaymentGatewayInterface
  {
      /**
       * Criar cobrança no gateway
       */
      public function createCharge(CreateChargeDTO $dto): array;

      /**
       * Buscar cobrança no gateway
       */
      public function getCharge(string $gatewayChargeId): array;

      /**
       * Cancelar cobrança no gateway
       */
      public function cancelCharge(string $gatewayChargeId): bool;

      /**
       * Processar payload de webhook
       */
      public function processWebhook(array $payload): array;

      /**
       * Validar assinatura do webhook
       */
      public function validateWebhookSignature(string $signature, array $payload): bool;

      /**
       * Verificar status da conexão com gateway
       */
      public function healthCheck(): bool;
  }
  ```

### 5. Implementações dos Gateways

#### 5.1 PagSeguro
- [ ] Criar `app/Services/PaymentGateway/Implementations/PagSeguroGateway.php`
  ```php
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

      public function createCharge(CreateChargeDTO $dto): array
      {
          // Implementar integração com API PagSeguro
          // Retornar: ['gateway_charge_id' => '...', 'payment_url' => '...', ...]
      }

      public function getCharge(string $gatewayChargeId): array
      {
          // Buscar status da cobrança
      }

      public function cancelCharge(string $gatewayChargeId): bool
      {
          // Cancelar cobrança
      }

      public function processWebhook(array $payload): array
      {
          return [
              'charge_id' => $payload['id'] ?? null,
              'status' => $this->mapStatus($payload['status'] ?? ''),
              'paid_at' => $payload['paid_at'] ?? null,
              'payment_method' => $payload['payment_method'] ?? null,
          ];
      }

      public function validateWebhookSignature(string $signature, array $payload): bool
      {
          // Validar assinatura
      }

      private function mapStatus(string $status): string
      {
          return match($status) {
              'paid', 'approved' => 'paid',
              'pending', 'waiting_payment' => 'pending',
              'cancelled', 'canceled' => 'cancelled',
              'refunded' => 'refunded',
              default => 'pending',
          };
      }
  }
  ```

#### 5.2 Asaas
- [ ] Criar `app/Services/PaymentGateway/Implementations/AsaasGateway.php`
  - Implementar PaymentGatewayInterface
  - Lógica específica da API Asaas
  - Mapeamento de status Asaas -> Sistema

#### 5.3 Stone
- [ ] Criar `app/Services/PaymentGateway/Implementations/StoneGateway.php`
  - Implementar PaymentGatewayInterface
  - Lógica específica da API Stone
  - Mapeamento de status Stone -> Sistema

### 6. Factory Pattern
- [ ] Criar `app/Services/PaymentGateway/PaymentGatewayFactory.php`
  ```php
  class PaymentGatewayFactory
  {
      /**
       * Criar instância do gateway baseado no tipo
       */
      public static function create(PaymentGatewayType $type): PaymentGatewayInterface
      {
          return match($type) {
              PaymentGatewayType::PAGSEGURO => app(PagSeguroGateway::class),
              PaymentGatewayType::ASAAS => app(AsaasGateway::class),
              PaymentGatewayType::STONE => app(StoneGateway::class),
          };
      }

      /**
       * Criar instância do gateway padrão (configurável)
       */
      public static function createDefault(): PaymentGatewayInterface
      {
          $defaultType = PaymentGatewayType::from(
              config('services.payment_gateway.default', 'pagseguro')
          );

          return self::create($defaultType);
      }
  }
  ```

### 7. Service de Integração
- [ ] Criar `app/Services/PaymentGateway/PaymentGatewayService.php`
  ```php
  class PaymentGatewayService
  {
      public function __construct(
          private readonly ChargeRepositoryInterface $chargeRepository
      ) {}

      public function createChargeOnGateway(
          Charge $charge,
          PaymentGatewayType $gatewayType
      ): array {
          $gateway = PaymentGatewayFactory::create($gatewayType);

          $dto = new CreateChargeDTO(
              customerId: $charge->customer_id,
              amount: $charge->amount,
              description: $charge->description,
              paymentMethod: $charge->payment_method,
              dueDate: $charge->due_date->toDateString()
          );

          try {
              $response = $gateway->createCharge($dto);

              // Atualizar charge com gateway_charge_id
              $this->chargeRepository->update($charge->id, [
                  'gateway_charge_id' => $response['gateway_charge_id'],
                  'payment_gateway_id' => $this->getGatewayId($gatewayType),
              ]);

              return $response;

          } catch (\\Throwable $e) {
              Log::error('Failed to create charge on gateway', [
                  'charge_id' => $charge->id,
                  'gateway' => $gatewayType->value,
                  'error' => $e->getMessage()
              ]);

              throw new PaymentGatewayException(
                  "Failed to create charge on {$gatewayType->value}: {$e->getMessage()}"
              );
          }
      }

      public function syncChargeStatus(Charge $charge): Charge
      {
          // Buscar status atualizado no gateway e sincronizar
      }

      private function getGatewayId(PaymentGatewayType $type): int
      {
          return PaymentGateway::where('type', $type)->first()?->id;
      }
  }
  ```

### 8. Configurações
- [ ] Adicionar em `config/services.php`
  ```php
  'payment_gateway' => [
      'default' => env('PAYMENT_GATEWAY_DEFAULT', 'pagseguro'),
  ],

  'pagseguro' => [
      'api_url' => env('PAGSEGURO_API_URL', 'https://api.pagseguro.com'),
      'api_key' => env('PAGSEGURO_API_KEY'),
      'api_token' => env('PAGSEGURO_API_TOKEN'),
  ],

  'asaas' => [
      'api_url' => env('ASAAS_API_URL', 'https://api.asaas.com'),
      'api_key' => env('ASAAS_API_KEY'),
  ],

  'stone' => [
      'api_url' => env('STONE_API_URL', 'https://api.stone.com.br'),
      'api_key' => env('STONE_API_KEY'),
      'api_secret' => env('STONE_API_SECRET'),
  ],
  ```

- [ ] Atualizar `.env.example`
  ```env
  PAYMENT_GATEWAY_DEFAULT=pagseguro

  PAGSEGURO_API_URL=https://sandbox.pagseguro.uol.com.br
  PAGSEGURO_API_KEY=
  PAGSEGURO_API_TOKEN=

  ASAAS_API_URL=https://sandbox.asaas.com/api/v3
  ASAAS_API_KEY=

  STONE_API_URL=https://sandbox.stone.com.br
  STONE_API_KEY=
  STONE_API_SECRET=
  ```

### 9. HTTP Client Service
- [ ] Criar `app/Services/PaymentGateway/HttpClientService.php`
  ```php
  class HttpClientService
  {
      public function post(string $url, array $data, array $headers = []): array
      {
          // Usar Laravel HTTP Client
          // Adicionar retry logic
          // Adicionar logging
          // Tratar erros
      }

      public function get(string $url, array $headers = []): array
      {
          // Similar ao post
      }

      public function put(string $url, array $data, array $headers = []): array
      {
          // Similar ao post
      }

      public function delete(string $url, array $headers = []): bool
      {
          // Similar ao post
      }
  }
  ```

### 10. Repository
- [ ] Criar `app/Repositories/Contracts/PaymentGatewayRepositoryInterface.php`
  ```php
  interface PaymentGatewayRepositoryInterface
  {
      public function find(int $id): ?PaymentGateway;
      public function findByType(PaymentGatewayType $type): ?PaymentGateway;
      public function getActive(): Collection;
      public function create(array $data): PaymentGateway;
      public function update(int $id, array $data): PaymentGateway;
  }
  ```

- [ ] Criar `app/Repositories/Eloquent/PaymentGatewayRepository.php`
- [ ] Registrar binding no `AppServiceProvider`

### 11. Seeders
- [ ] Criar `database/seeders/PaymentGatewaySeeder.php`
  ```php
  public function run(): void
  {
      PaymentGateway::create([
          'type' => PaymentGatewayType::PAGSEGURO,
          'name' => 'PagSeguro',
          'is_active' => true,
          'settings' => [
              'timeout' => 30,
              'retry_attempts' => 3,
          ],
      ]);

      PaymentGateway::create([
          'type' => PaymentGatewayType::ASAAS,
          'name' => 'Asaas',
          'is_active' => true,
          'settings' => [
              'timeout' => 30,
              'retry_attempts' => 3,
          ],
      ]);

      PaymentGateway::create([
          'type' => PaymentGatewayType::STONE,
          'name' => 'Stone',
          'is_active' => false,
          'settings' => [
              'timeout' => 30,
              'retry_attempts' => 3,
          ],
      ]);
  }
  ```

### 12. Testes
- [ ] Criar `tests/Unit/Services/PaymentGateway/PagSeguroGatewayTest.php`
  - Mockar HTTP Client
  - Testar createCharge()
  - Testar getCharge()
  - Testar cancelCharge()
  - Testar processWebhook()
  - Testar mapeamento de status
  - Testar tratamento de erros

- [ ] Criar `tests/Unit/Services/PaymentGateway/AsaasGatewayTest.php`
  - Mesmos testes do PagSeguro

- [ ] Criar `tests/Unit/Services/PaymentGateway/StoneGatewayTest.php`
  - Mesmos testes do PagSeguro

- [ ] Criar `tests/Unit/Services/PaymentGateway/PaymentGatewayFactoryTest.php`
  - Testar criação de cada gateway
  - Testar gateway padrão

- [ ] Criar `tests/Unit/Services/PaymentGateway/PaymentGatewayServiceTest.php`
  - Testar integração completa
  - Mockar gateways

---

## Checklist de Qualidade

### Arquitetura
- [ ] Strategy Pattern implementado corretamente
- [ ] Factory Pattern implementado
- [ ] Interface comum para todos os gateways
- [ ] Facilidade de adicionar novos gateways (OCP)
- [ ] Gateways intercambiáveis (LSP)

### Código
- [ ] Type hints completos
- [ ] Exception handling robusto
- [ ] Logging de todas as operações
- [ ] Retry logic implementado
- [ ] Timeout configurável

### Segurança
- [ ] Credenciais criptografadas no banco
- [ ] Credenciais em variáveis de ambiente
- [ ] Validação de assinatura de webhooks
- [ ] Nunca logar credenciais

### Performance
- [ ] HTTP Client com timeout
- [ ] Retry logic com backoff exponencial
- [ ] Cache de configurações (opcional)

### Testes
- [ ] Unit tests para cada gateway
- [ ] Mocks de HTTP requests
- [ ] Testar casos de sucesso e falha
- [ ] Cobertura > 80%

---

## Critérios de Aceitação

✅ **Arquitetura**
- Strategy Pattern implementado
- Factory Pattern implementado
- Fácil adicionar novos gateways
- Interface comum funcionando

✅ **Funcionalidade**
- Criar cobrança nos gateways
- Buscar status nos gateways
- Cancelar cobrança nos gateways
- Processar webhooks
- Validar assinaturas

✅ **Segurança**
- Credenciais protegidas
- Webhooks validados
- Logs sem dados sensíveis

✅ **Qualidade**
- Todos os testes passando
- Exception handling robusto
- Logging adequado
- Retry logic funcionando

---

## Notas Importantes

⚠️ **Atenção**
- NUNCA commitar credenciais reais
- Sempre validar assinatura de webhooks
- Implementar retry logic em chamadas externas
- Logar todas as interações com gateways
- Usar sandbox em desenvolvimento
- Criptografar credenciais no banco
- Implementar circuit breaker para falhas (opcional)

⚠️ **Adicionar Novo Gateway**
Para adicionar um novo gateway:
1. Adicionar caso no Enum PaymentGatewayType
2. Criar classe que implementa PaymentGatewayInterface
3. Adicionar caso no PaymentGatewayFactory
4. Adicionar configurações em config/services.php
5. Adicionar variáveis no .env.example
6. Criar testes unitários
7. Criar seeder
8. Atualizar documentação

📚 **Referências**
- Prompt.MD: Strategy Pattern, Factory Pattern, Open/Closed Principle
- Laravel HTTP Client
- Laravel Encryption
- Design Patterns: Strategy, Factory
