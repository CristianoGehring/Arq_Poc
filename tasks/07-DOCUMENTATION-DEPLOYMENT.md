# 07 - Documentation & Deployment

## Objetivo
Criar documentação completa da API e preparar o projeto para deployment em produção.

## Prioridade
🟢 BAIXA-MÉDIA - Necessário antes do deploy

## Dependências
- Todas as tasks anteriores (01-06)

---

## Ordem de Implementação

### 1. API Documentation (Scribe/OpenAPI)
- [ ] Instalar Scribe
  ```bash
  composer require --dev knuckleswtf/scribe
  php artisan vendor:publish --tag=scribe-config
  ```

- [ ] Configurar `config/scribe.php`
  ```php
  return [
      'type' => 'laravel',
      'title' => 'Billing System API Documentation',
      'description' => 'API para gerenciamento de cobranças multi-gateway',
      'base_url' => env('APP_URL', 'http://localhost'),
      'routes' => [
          [
              'match' => [
                  'prefixes' => ['api/v1/*'],
              ],
          ],
      ],
      'postman' => [
          'enabled' => true,
      ],
      'openapi' => [
          'enabled' => true,
      ],
      'try_it_out' => [
          'enabled' => true,
      ],
  ];
  ```

- [ ] Adicionar annotations nos Controllers
  ```php
  /**
   * @group Customer Management
   *
   * APIs for managing customers
   */
  class CustomerController extends Controller
  {
      /**
       * List customers
       *
       * Get a paginated list of all customers.
       *
       * @queryParam page integer Page number. Example: 1
       * @queryParam per_page integer Items per page. Example: 15
       *
       * @response 200 {
       *   "data": [
       *     {
       *       "id": 1,
       *       "name": "João Silva",
       *       "email": "joao@example.com",
       *       "document": "12345678900",
       *       "phone": "11999999999",
       *       "status": "active",
       *       "created_at": "2024-10-15T10:30:00Z"
       *     }
       *   ],
       *   "links": {},
       *   "meta": {}
       * }
       */
      public function index() { }

      /**
       * Create customer
       *
       * Create a new customer in the system.
       *
       * @bodyParam name string required Customer name. Example: João Silva
       * @bodyParam email string required Customer email. Example: joao@example.com
       * @bodyParam document string required CPF or CNPJ. Example: 12345678900
       * @bodyParam phone string Phone number. Example: 11999999999
       *
       * @response 201 {
       *   "data": {
       *     "id": 1,
       *     "name": "João Silva",
       *     "email": "joao@example.com"
       *   }
       * }
       *
       * @response 422 {
       *   "message": "The given data was invalid.",
       *   "errors": {
       *     "email": ["The email has already been taken."]
       *   }
       * }
       */
      public function store(StoreCustomerRequest $request) { }
  }
  ```

- [ ] Gerar documentação
  ```bash
  php artisan scribe:generate
  ```

### 2. README.md
- [ ] Criar `README.md` completo
  ```markdown
  # Billing System API

  Sistema de gerenciamento de cobranças multi-gateway com arquitetura escalável.

  ## 🚀 Features

  - ✅ CRUD completo de clientes
  - ✅ Gerenciamento de cobranças
  - ✅ Integração com múltiplos gateways (PagSeguro, Asaas, Stone)
  - ✅ Processamento assíncrono de webhooks
  - ✅ Autenticação via Sanctum
  - ✅ API RESTful versionada
  - ✅ Rate limiting
  - ✅ Testes automatizados (>80% coverage)

  ## 🛠️ Stack Tecnológica

  - PHP 8.2+
  - Laravel 11.x
  - MySQL 8.0+ / PostgreSQL 14+
  - Redis (Queue & Cache)
  - Laravel Sanctum (Auth)

  ## 📋 Pré-requisitos

  - PHP >= 8.2
  - Composer
  - MySQL/PostgreSQL
  - Redis
  - Git

  ## 🔧 Instalação

  ```bash
  # Clone o repositório
  git clone [repo-url]
  cd billing-system

  # Instale dependências
  composer install

  # Configure o ambiente
  cp .env.example .env
  php artisan key:generate

  # Configure o banco de dados no .env
  # DB_CONNECTION=mysql
  # DB_DATABASE=billing_db
  # DB_USERNAME=root
  # DB_PASSWORD=

  # Execute migrations
  php artisan migrate

  # Seed inicial (gateways)
  php artisan db:seed --class=PaymentGatewaySeeder

  # Inicie o servidor
  php artisan serve
  ```

  ## 🧪 Testes

  ```bash
  # Todos os testes
  composer test

  # Com coverage
  composer test:coverage

  # Parallel
  php artisan test --parallel
  ```

  ## 📚 Documentação da API

  Acesse: `http://localhost:8000/docs`

  ## 🏗️ Arquitetura

  O projeto segue princípios de DDD simplificado e CQRS leve:

  - **Repository Pattern**: Abstração de acesso a dados
  - **Service Pattern**: Lógica de negócio (Command/Query)
  - **DTO Pattern**: Transferência de dados type-safe
  - **Strategy Pattern**: Gateways de pagamento intercambiáveis
  - **Event-Driven**: Processamento assíncrono

  ## 📁 Estrutura de Diretórios

  ```
  app/
  ├── DTOs/              # Data Transfer Objects
  ├── Enums/             # Enumerações
  ├── Events/            # Domain Events
  ├── Exceptions/        # Custom Exceptions
  ├── Http/
  │   ├── Controllers/   # Controllers (thin)
  │   ├── Requests/      # Form Requests
  │   └── Resources/     # API Resources
  ├── Jobs/              # Async Jobs
  ├── Models/            # Eloquent Models
  ├── Repositories/      # Repository Pattern
  │   ├── Contracts/     # Interfaces
  │   └── Eloquent/      # Implementações
  └── Services/          # Business Logic
      ├── Customer/
      ├── Charge/
      └── PaymentGateway/
  ```

  ## 🔐 Autenticação

  A API usa Laravel Sanctum para autenticação via tokens:

  ```bash
  # Login
  POST /api/v1/auth/login
  {
    "email": "user@example.com",
    "password": "password"
  }

  # Use o token retornado
  Authorization: Bearer {token}
  ```

  ## 🚦 Rate Limiting

  - **API Geral**: 60 requisições/minuto
  - **Auth Endpoints**: 5 requisições/minuto

  ## 📊 Monitoramento

  - Logs: `storage/logs/`
  - Queue Jobs: `php artisan queue:work`
  - Failed Jobs: `php artisan queue:failed`

  ## 🤝 Contribuindo

  1. Fork o projeto
  2. Crie uma branch (`git checkout -b feature/AmazingFeature`)
  3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
  4. Push para a branch (`git push origin feature/AmazingFeature`)
  5. Abra um Pull Request

  ## 📝 Padrões de Código

  - PSR-12 Code Style
  - SOLID Principles
  - Object Calisthenics
  - Type Hints obrigatórios
  - Cobertura de testes > 80%

  ## 📄 Licença

  [MIT License](LICENSE)

  ## 👥 Autores

  - Seu Nome - [@seu-usuario](https://github.com/seu-usuario)
  ```

### 3. CHANGELOG.md
- [ ] Criar `CHANGELOG.md`
  ```markdown
  # Changelog

  All notable changes to this project will be documented in this file.

  The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
  and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

  ## [Unreleased]

  ## [1.0.0] - 2024-10-15

  ### Added
  - Customer CRUD endpoints
  - Charge management
  - PagSeguro integration
  - Asaas integration
  - Stone integration
  - Webhook processing
  - Authentication via Sanctum
  - Rate limiting
  - Comprehensive test suite

  ### Changed
  - N/A

  ### Deprecated
  - N/A

  ### Removed
  - N/A

  ### Fixed
  - N/A

  ### Security
  - Webhook signature validation
  - SQL injection prevention
  - XSS protection
  ```

### 4. CONTRIBUTING.md
- [ ] Criar `CONTRIBUTING.md`
  ```markdown
  # Contributing Guide

  ## Code Style

  - Follow PSR-12
  - Use Laravel Pint: `composer format`
  - Run PHPStan: `composer analyse`

  ## Development Workflow

  1. Create a feature branch
  2. Write tests first (TDD)
  3. Implement feature
  4. Ensure tests pass
  5. Check code style
  6. Submit PR

  ## Commit Messages

  Follow Conventional Commits:

  - `feat:` New feature
  - `fix:` Bug fix
  - `docs:` Documentation
  - `style:` Code style
  - `refactor:` Refactoring
  - `test:` Tests
  - `chore:` Maintenance

  Example: `feat: add customer search endpoint`

  ## Pull Request Process

  1. Update README.md if needed
  2. Update CHANGELOG.md
  3. Ensure all tests pass
  4. Get at least one approval
  5. Squash and merge
  ```

### 5. Deployment - Docker
- [ ] Criar `Dockerfile`
  ```dockerfile
  FROM php:8.2-fpm

  # Install dependencies
  RUN apt-get update && apt-get install -y \
      git \
      curl \
      libpng-dev \
      libonig-dev \
      libxml2-dev \
      zip \
      unzip

  # Install PHP extensions
  RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

  # Install Redis extension
  RUN pecl install redis && docker-php-ext-enable redis

  # Install Composer
  COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

  # Set working directory
  WORKDIR /var/www

  # Copy application
  COPY . .

  # Install dependencies
  RUN composer install --optimize-autoloader --no-dev

  # Set permissions
  RUN chown -R www-data:www-data /var/www

  EXPOSE 9000

  CMD ["php-fpm"]
  ```

- [ ] Criar `docker-compose.yml`
  ```yaml
  version: '3.8'

  services:
    app:
      build: .
      container_name: billing-app
      restart: unless-stopped
      working_dir: /var/www
      volumes:
        - ./:/var/www
      networks:
        - billing-network

    nginx:
      image: nginx:alpine
      container_name: billing-nginx
      restart: unless-stopped
      ports:
        - "8000:80"
      volumes:
        - ./:/var/www
        - ./docker/nginx:/etc/nginx/conf.d
      networks:
        - billing-network

    mysql:
      image: mysql:8.0
      container_name: billing-mysql
      restart: unless-stopped
      environment:
        MYSQL_DATABASE: ${DB_DATABASE}
        MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}
      ports:
        - "3306:3306"
      volumes:
        - mysql-data:/var/lib/mysql
      networks:
        - billing-network

    redis:
      image: redis:7-alpine
      container_name: billing-redis
      restart: unless-stopped
      ports:
        - "6379:6379"
      networks:
        - billing-network

    queue:
      build: .
      container_name: billing-queue
      restart: unless-stopped
      command: php artisan queue:work --tries=3 --timeout=60
      volumes:
        - ./:/var/www
      networks:
        - billing-network

  networks:
    billing-network:
      driver: bridge

  volumes:
    mysql-data:
  ```

- [ ] Criar `docker/nginx/default.conf`
  ```nginx
  server {
      listen 80;
      index index.php index.html;
      error_log  /var/log/nginx/error.log;
      access_log /var/log/nginx/access.log;
      root /var/www/public;

      location ~ \.php$ {
          try_files $uri =404;
          fastcgi_split_path_info ^(.+\.php)(/.+)$;
          fastcgi_pass app:9000;
          fastcgi_index index.php;
          include fastcgi_params;
          fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
          fastcgi_param PATH_INFO $fastcgi_path_info;
      }

      location / {
          try_files $uri $uri/ /index.php?$query_string;
          gzip_static on;
      }
  }
  ```

### 6. Environment Configuration
- [ ] Atualizar `.env.example` completo
  ```env
  APP_NAME="Billing System"
  APP_ENV=production
  APP_KEY=
  APP_DEBUG=false
  APP_URL=https://api.yourdomain.com

  LOG_CHANNEL=stack
  LOG_LEVEL=info

  DB_CONNECTION=mysql
  DB_HOST=mysql
  DB_PORT=3306
  DB_DATABASE=billing_db
  DB_USERNAME=root
  DB_PASSWORD=

  REDIS_HOST=redis
  REDIS_PASSWORD=null
  REDIS_PORT=6379

  QUEUE_CONNECTION=redis
  CACHE_DRIVER=redis
  SESSION_DRIVER=redis

  SANCTUM_TOKEN_EXPIRATION=null

  # Payment Gateways
  PAYMENT_GATEWAY_DEFAULT=pagseguro

  PAGSEGURO_API_URL=https://api.pagseguro.com
  PAGSEGURO_API_KEY=
  PAGSEGURO_API_TOKEN=

  ASAAS_API_URL=https://api.asaas.com/v3
  ASAAS_API_KEY=

  STONE_API_URL=https://api.stone.com.br
  STONE_API_KEY=
  STONE_API_SECRET=
  ```

### 7. Deployment Scripts
- [ ] Criar `deploy.sh`
  ```bash
  #!/bin/bash

  echo "🚀 Starting deployment..."

  # Pull latest code
  git pull origin main

  # Install dependencies
  composer install --optimize-autoloader --no-dev

  # Run migrations
  php artisan migrate --force

  # Clear caches
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache

  # Restart queue workers
  php artisan queue:restart

  # Optimize
  php artisan optimize

  echo "✅ Deployment completed!"
  ```

- [ ] Criar `rollback.sh`
  ```bash
  #!/bin/bash

  echo "⏪ Starting rollback..."

  # Rollback migrations
  php artisan migrate:rollback --step=1

  # Clear caches
  php artisan cache:clear
  php artisan config:clear
  php artisan route:clear
  php artisan view:clear

  echo "✅ Rollback completed!"
  ```

### 8. Monitoring & Logging
- [ ] Configurar `config/logging.php` para produção
  ```php
  'channels' => [
      'stack' => [
          'driver' => 'stack',
          'channels' => ['daily', 'slack'],
      ],

      'daily' => [
          'driver' => 'daily',
          'path' => storage_path('logs/laravel.log'),
          'level' => env('LOG_LEVEL', 'info'),
          'days' => 14,
      ],

      'slack' => [
          'driver' => 'slack',
          'url' => env('LOG_SLACK_WEBHOOK_URL'),
          'level' => 'error',
      ],
  ];
  ```

### 9. Health Check Endpoint
- [ ] Criar `app/Http/Controllers/HealthController.php`
  ```php
  class HealthController extends Controller
  {
      public function check(): JsonResponse
      {
          $health = [
              'status' => 'healthy',
              'timestamp' => now()->toIso8601String(),
              'services' => [
                  'database' => $this->checkDatabase(),
                  'redis' => $this->checkRedis(),
                  'queue' => $this->checkQueue(),
              ],
          ];

          $allHealthy = collect($health['services'])->every(fn($status) => $status === 'ok');

          return response()->json($health, $allHealthy ? 200 : 503);
      }

      private function checkDatabase(): string
      {
          try {
              DB::connection()->getPdo();
              return 'ok';
          } catch (\Exception $e) {
              return 'error';
          }
      }

      private function checkRedis(): string
      {
          try {
              Redis::ping();
              return 'ok';
          } catch (\Exception $e) {
              return 'error';
          }
      }

      private function checkQueue(): string
      {
          // Verificar se há queue workers rodando
          return 'ok';
      }
  }
  ```

- [ ] Adicionar rota
  ```php
  Route::get('health', [HealthController::class, 'check']);
  ```

### 10. Production Checklist
- [ ] Criar `PRODUCTION_CHECKLIST.md`
  ```markdown
  # Production Deployment Checklist

  ## Pre-Deployment
  - [ ] All tests passing
  - [ ] Code coverage > 80%
  - [ ] PHPStan passing
  - [ ] Code style check passing
  - [ ] Environment variables configured
  - [ ] Database backups configured
  - [ ] SSL certificate installed
  - [ ] Domain configured

  ## Deployment
  - [ ] Run migrations
  - [ ] Seed payment gateways
  - [ ] Configure queue workers (Supervisor)
  - [ ] Configure cron jobs
  - [ ] Cache config/routes/views
  - [ ] Enable OPcache
  - [ ] Set APP_DEBUG=false
  - [ ] Set proper file permissions

  ## Post-Deployment
  - [ ] Test health check endpoint
  - [ ] Test authentication
  - [ ] Test critical endpoints
  - [ ] Monitor logs for errors
  - [ ] Monitor queue workers
  - [ ] Set up monitoring alerts

  ## Security
  - [ ] HTTPS only
  - [ ] CORS configured
  - [ ] Rate limiting active
  - [ ] Webhook signatures validated
  - [ ] Database credentials secure
  - [ ] API keys in environment variables
  ```

---

## Critérios de Aceitação

✅ **Documentação**
- README completo e claro
- API documentation gerada
- CHANGELOG atualizado
- CONTRIBUTING criado

✅ **Deployment**
- Docker configurado
- Deploy scripts criados
- Health check funcionando
- Production checklist completo

✅ **Qualidade**
- Todos os testes passando
- Code coverage > 80%
- PHPStan passando
- Code style conformado

---

## Notas Importantes

⚠️ **Atenção**
- NUNCA commitar .env com credenciais reais
- Sempre usar HTTPS em produção
- Configurar backups automáticos
- Monitorar logs de erro
- Ter plano de rollback
- Testar deploy em staging primeiro

📚 **Referências**
- Laravel Deployment Documentation
- Docker Best Practices
- API Documentation Standards
