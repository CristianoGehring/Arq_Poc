# 00 - Setup Inicial do Projeto

## Objetivo
Configurar a base do projeto Laravel com todas as dependências e configurações necessárias para arquitetura baseada em Actions/Queries/Exceptions.

## Prioridade
🔴 CRÍTICA - Deve ser feito primeiro

## Dependências
Nenhuma

---

## Tarefas

### 1. Instalação e Configuração Base
- [x] Verificar versão do PHP (8.2+)
- [x] Verificar versão do Composer
- [x] Instalar Laravel 11.x
- [x] Configurar arquivo `.env`
- [x] Configurar banco de dados (MySQL 8.0+ / PostgreSQL 14+)
- [x] Configurar Redis para Queue e Cache

### 2. Dependências do Projeto
- [x] Instalar Laravel Sanctum para autenticação
- [x] Configurar PSR-12 Code Style
- [x] Instalar PHPStan para análise estática
- [x] Instalar Pest ou PHPUnit para testes
- [x] Configurar Laravel Pint para formatação

### 3. Estrutura de Diretórios (Nova Arquitetura)
- [x] Criar diretório `app/Actions/`
  - [x] `app/Actions/Customer/`
  - [x] `app/Actions/Charge/`
  - [x] `app/Actions/PaymentGateway/`
- [x] Criar diretório `app/Queries/`
  - [x] `app/Queries/Customer/`
  - [x] `app/Queries/Charge/`
  - [x] `app/Queries/PaymentGateway/`
- [x] Criar diretório `app/DTOs/`
  - [x] `app/DTOs/Customer/`
  - [x] `app/DTOs/Charge/`
  - [x] `app/DTOs/Webhook/`
- [x] Criar diretório `app/Enums/`
- [x] Criar diretório `app/Events/`
- [x] Criar diretório `app/Exceptions/`
- [x] Criar diretório `app/Repositories/` (APENAS para abstrações necessárias)
  - [x] `app/Repositories/Contracts/`
  - [x] `app/Repositories/Eloquent/`
- [x] Criar diretório `app/Services/` (APENAS Factories e Orchestrators)
  - [x] `app/Services/PaymentGateway/`
- [x] Criar diretório `app/Http/Requests/`
- [x] Criar diretório `app/Http/Resources/`
- [x] Criar diretório `app/Jobs/`
- [x] Criar diretório `app/Listeners/`

### 4. Configurações Gerais
- [x] Configurar timezone para America/Sao_Paulo
- [x] Configurar locale para pt_BR
- [x] Configurar queue connection para Redis
- [x] Configurar cache driver para Redis
- [x] Configurar session driver

### 5. Versionamento de API
- [x] Criar middleware `ApiVersionMiddleware`
- [x] Configurar rotas `/api/v1`
- [x] Estruturar Controllers em `Api/V1/`

### 6. Exception Handler
- [x] Configurar `app/Exceptions/Handler.php` para registrar Custom Exceptions
  ```php
  public function register(): void
  {
      // Domain Exceptions com render() próprio
      $this->renderable(function (CustomerException $e) {
          return $e->render();
      });

      $this->renderable(function (ChargeException $e) {
          return $e->render();
      });

      // Fallbacks genéricos
      $this->renderable(function (NotFoundHttpException $e) {
          return response()->json([
              'message' => 'Resource not found',
              'error' => 'not_found'
          ], 404);
      });
  }
  ```

### 7. Configurações de Segurança
- [x] Configurar CORS
- [x] Configurar Rate Limiting
- [x] Configurar Sanctum
- [ ] Configurar políticas de senha

### 8. Service Providers
- [ ] Registrar bindings de Repositories (APENAS quando necessário)
  ```php
  // AppServiceProvider.php
  public function register(): void
  {
      // APENAS registrar quando há interface e múltiplas implementações
      $this->app->bind(
          PaymentGatewayRepositoryInterface::class,
          PaymentGatewayRepository::class
      );
  }
  ```

### 9. Documentação
- [ ] Criar README.md do projeto
- [ ] Criar CHANGELOG.md
- [ ] Criar .env.example completo
- [ ] Documentar estrutura de diretórios
- [ ] Documentar diferença entre Actions/Queries

---

## Critérios de Aceitação
- ✅ Projeto Laravel rodando sem erros
- ✅ Banco de dados conectado e funcional
- ✅ Redis configurado para Queue e Cache
- ✅ Estrutura de diretórios criada (Actions/Queries/Exceptions)
- ✅ Versionamento de API funcionando
- ✅ Exception Handler configurado para Custom Exceptions
- ✅ Rate limiting ativo
- ✅ Testes rodando (`php artisan test`)

---

## Comandos Úteis

```bash
# Instalar Laravel
composer create-project laravel/laravel:^11.0 .

# Instalar dependências
composer require laravel/sanctum
composer require --dev laravel/pint
composer require --dev phpstan/phpstan
composer require --dev pestphp/pest
composer require --dev pestphp/pest-plugin-laravel

# Criar estrutura de diretórios
mkdir -p app/Actions/{Customer,Charge,PaymentGateway}
mkdir -p app/Queries/{Customer,Charge,PaymentGateway}
mkdir -p app/DTOs/{Customer,Charge,Webhook}
mkdir -p app/Enums
mkdir -p app/Exceptions
mkdir -p app/Repositories/{Contracts,Eloquent}
mkdir -p app/Services/PaymentGateway

# Migrations
php artisan migrate

# Testes
php artisan test
# ou
./vendor/bin/pest

# Code Style
./vendor/bin/pint

# Static Analysis
./vendor/bin/phpstan analyse
```

---

## Exemplo de README.md

```markdown
# Sistema de Gerenciamento de Cobranças

Sistema multi-gateway para gerenciamento de cobranças com arquitetura baseada em Actions, Queries e Custom Exceptions.

## Arquitetura

### Actions (Write Operations)
Actions encapsulam operações de escrita (Commands). Cada action:
- Retorna domain objects (Models, Collections)
- NUNCA retorna HTTP responses
- Lança Custom Exceptions para erros de negócio
- É reutilizável em Controllers, Jobs, Commands

Exemplo:
\`\`\`php
class CreateCustomerAction
{
    public function execute(CreateCustomerDTO $dto): Customer
    {
        // Lógica de negócio
    }
}
\`\`\`

### Queries (Read Operations)
Queries encapsulam operações de leitura. Cada query:
- Retorna Models, Collections ou Paginators
- Usa Eloquent diretamente (sem abstração desnecessária)
- Eager loading explícito

Exemplo:
\`\`\`php
class GetActiveCustomersQuery
{
    public function execute(int $perPage = 15): LengthAwarePaginator
    {
        return Customer::query()
            ->where('status', CustomerStatus::ACTIVE)
            ->with(['charges'])
            ->paginate($perPage);
    }
}
\`\`\`

### Custom Exceptions
Exceptions controlam status codes sem acoplar Actions ao HTTP:

\`\`\`php
class CustomerNotFoundException extends CustomerException
{
    protected int $statusCode = 404;

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'customer_not_found'
        ], $this->statusCode);
    }
}
\`\`\`

## Stack
- Laravel 11.x
- PHP 8.2+
- MySQL 8.0+ / PostgreSQL 14+
- Redis (Queue + Cache)
- Laravel Sanctum

## Setup
\`\`\`bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
\`\`\`
```

---

## Notas Importantes

⚠️ **Nova Arquitetura**
Este projeto usa arquitetura baseada em:
- **Actions** para write operations (não Services)
- **Queries** para read operations (não QueryServices)
- **Custom Exceptions** para controlar status codes
- **Repository APENAS** quando há múltiplas implementações
- **Eloquent direto** quando não há necessidade de abstração

⚠️ **Diferenças do Padrão Tradicional**
- ❌ NÃO usar Repository/Service para CRUD simples
- ✅ Actions retornam Models (não JsonResponse)
- ✅ Exceptions controlam status codes (via render())
- ✅ Controller define status de sucesso (200, 201, 204)

📚 **Referências**
- Seguir PSR-12 rigorosamente
- Usar PHP 8.2+ features (readonly, enums, match)
- Consultar Prompt.MD para padrões detalhados
