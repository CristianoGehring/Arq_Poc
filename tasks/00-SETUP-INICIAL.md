# 00 - Setup Inicial do Projeto

## Objetivo
Configurar a base do projeto Laravel com todas as dependências e configurações necessárias.

## Prioridade
🔴 CRÍTICA - Deve ser feito primeiro

## Dependências
Nenhuma

---

## Tarefas

### 1. Instalação e Configuração Base
- [ ] Verificar versão do PHP (8.2+)
- [ ] Verificar versão do Composer
- [ ] Instalar Laravel 11.x
- [ ] Configurar arquivo `.env`
- [ ] Configurar banco de dados (MySQL 8.0+ / PostgreSQL 14+)
- [ ] Configurar Redis para Queue e Cache

### 2. Dependências do Projeto
- [ ] Instalar Laravel Sanctum para autenticação
- [ ] Configurar PSR-12 Code Style
- [ ] Instalar PHPStan para análise estática
- [ ] Instalar Pest ou PHPUnit para testes
- [ ] Configurar Laravel Pint para formatação

### 3. Estrutura de Diretórios
- [ ] Criar diretório `app/DTOs/`
- [ ] Criar diretório `app/Enums/`
- [ ] Criar diretório `app/Events/`
- [ ] Criar diretório `app/Exceptions/`
- [ ] Criar diretório `app/Repositories/Contracts/`
- [ ] Criar diretório `app/Repositories/Eloquent/`
- [ ] Criar diretório `app/Services/`
- [ ] Criar diretório `app/Http/Requests/`
- [ ] Criar diretório `app/Http/Resources/`
- [ ] Criar diretório `app/Jobs/`
- [ ] Criar diretório `app/Listeners/`

### 4. Configurações Gerais
- [ ] Configurar timezone para America/Sao_Paulo
- [ ] Configurar locale para pt_BR
- [ ] Configurar queue connection para Redis
- [ ] Configurar cache driver para Redis
- [ ] Configurar session driver

### 5. Versionamento de API
- [ ] Criar middleware `ApiVersionMiddleware`
- [ ] Configurar rotas `/api/v1`
- [ ] Estruturar Controllers em `Api/V1/`

### 6. Configurações de Segurança
- [ ] Configurar CORS
- [ ] Configurar Rate Limiting
- [ ] Configurar Sanctum
- [ ] Configurar políticas de senha

### 7. Documentação
- [ ] Criar README.md do projeto
- [ ] Criar CHANGELOG.md
- [ ] Criar .env.example completo
- [ ] Documentar estrutura de diretórios

---

## Critérios de Aceitação
- ✅ Projeto Laravel rodando sem erros
- ✅ Banco de dados conectado e funcional
- ✅ Redis configurado para Queue e Cache
- ✅ Estrutura de diretórios criada
- ✅ Versionamento de API funcionando
- ✅ Rate limiting ativo
- ✅ Testes rodando (`php artisan test`)

---

## Comandos Úteis

```bash
# Instalar Laravel
composer create-project laravel/laravel .

# Instalar dependências
composer require laravel/sanctum
composer require --dev laravel/pint
composer require --dev phpstan/phpstan

# Migrations
php artisan migrate

# Testes
php artisan test

# Code Style
./vendor/bin/pint
```

---

## Notas
- Seguir PSR-12 rigorosamente
- Usar PHP 8.2+ features (readonly, enums, etc)
- Configurar CI/CD desde o início (opcional mas recomendado)
