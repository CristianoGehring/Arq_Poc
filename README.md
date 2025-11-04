# Nova Arquitetura - Laravel API

API RESTful desenvolvida com Laravel 12 seguindo princípios de Clean Architecture e SOLID.

## Stack Tecnológico

- **PHP**: 8.2+
- **Laravel**: 12.x
- **MySQL**: 8.0+
- **Redis**: Para cache e filas
- **Laravel Sail**: Ambiente Docker
- **Laravel Sanctum**: Autenticação API
- **PHPStan**: Análise estática
- **Laravel Pint**: Code Style (PSR-12)

## Requisitos

- Docker & Docker Compose
- Git

## Instalação

1. Clone o repositório:
```bash
git clone <repository-url>
cd nova_arq
```

2. Inicie os containers do Sail:
```bash
./vendor/bin/sail up -d
```

3. Execute as migrations:
```bash
./vendor/bin/sail artisan migrate
```

4. Acesse a aplicação:
- API: http://localhost/api/v1
- Health Check: http://localhost/up

## Comandos Úteis

```bash
# Subir os containers
./vendor/bin/sail up -d

# Parar os containers
./vendor/bin/sail down

# Executar migrations
./vendor/bin/sail artisan migrate

# Executar testes
./vendor/bin/sail test

# Code Style (PSR-12)
./vendor/bin/sail pint

# Análise estática
./vendor/bin/sail composer phpstan
```

## Estrutura do Projeto

```
app/
├── DTOs/                    # Data Transfer Objects
├── Enums/                   # Enumerações
├── Events/                  # Eventos
├── Exceptions/              # Exceções customizadas
├── Http/
│   ├── Controllers/
│   │   └── Api/V1/         # Controllers da API v1
│   ├── Requests/           # Form Requests
│   └── Resources/          # API Resources
├── Jobs/                   # Jobs assíncronos
├── Listeners/              # Event Listeners
├── Models/                 # Eloquent Models
├── Repositories/
│   ├── Contracts/          # Interfaces dos repositórios
│   └── Eloquent/           # Implementações Eloquent
└── Services/               # Camada de serviços
```

## API

A API está versionada e todas as rotas começam com `/api/v1/`.

### Autenticação

A API utiliza Laravel Sanctum para autenticação via tokens.

### Rate Limiting

Rate limiting está ativo para proteger a API de abuso.

## Configurações

### Timezone e Locale

- **Timezone**: America/Sao_Paulo
- **Locale**: pt_BR

### Cache e Filas

- **Driver de Cache**: Redis
- **Driver de Filas**: Redis

## Contribuindo

1. Siga o PSR-12 rigorosamente
2. Use PHP 8.2+ features (readonly, enums, etc)
3. Execute os testes antes de commitar
4. Execute o Pint para formatar o código

## Licença

MIT License
