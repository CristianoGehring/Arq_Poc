# 📋 Task Management - Sistema de Cobrança

Este diretório contém todas as tarefas organizadas para implementação do Sistema de Gerenciamento de Cobranças Multi-Gateway.

## 📚 Índice de Tarefas

### 🔴 Prioridade CRÍTICA
- **[Task 00](00-SETUP-INICIAL.md)** - Setup Inicial do Projeto
  - Instalação Laravel 11.x
  - Configuração de dependências
  - Estrutura de diretórios
  - Configurações base

### 🔴 Prioridade ALTA

- **[Task 01](01-CUSTOMER-DOMAIN.md)** - Customer Domain (Domínio de Clientes)
  - CRUD completo de clientes
  - Repository Pattern
  - DTOs, Enums, Exceptions
  - Services (Command/Query)
  - Testes completos

- **[Task 02](02-CHARGE-DOMAIN.md)** - Charge Domain (Domínio de Cobranças)
  - CRUD completo de cobranças
  - Relacionamento com clientes
  - Status management
  - Event-driven architecture
  - Testes completos

- **[Task 03](03-PAYMENT-GATEWAY-DOMAIN.md)** - Payment Gateway Domain
  - Strategy Pattern para gateways
  - Factory Pattern
  - Implementação PagSeguro, Asaas, Stone
  - Interface comum
  - HTTP Client service

- **[Task 04](04-WEBHOOK-DOMAIN.md)** - Webhook Domain
  - Processamento assíncrono
  - Validação de assinaturas
  - Retry logic
  - Webhook logs
  - Idempotência

### 🟡 Prioridade MÉDIA

- **[Task 05](05-AUTHENTICATION-AUTHORIZATION.md)** - Authentication & Authorization
  - Laravel Sanctum
  - Políticas de acesso
  - Rate limiting
  - Login/Logout/Register
  - Testes de autorização

- **[Task 06](06-TESTING-QUALITY.md)** - Testing & Code Quality
  - PHPUnit/Pest
  - Factories
  - Feature & Unit tests
  - Code coverage (>80%)
  - Laravel Pint (PSR-12)
  - PHPStan (static analysis)
  - CI/CD pipeline

### 🟢 Prioridade BAIXA

- **[Task 07](07-DOCUMENTATION-DEPLOYMENT.md)** - Documentation & Deployment
  - API Documentation (Scribe)
  - README completo
  - CHANGELOG
  - Docker setup
  - Deploy scripts
  - Production checklist

---

## 🚀 Ordem de Execução Recomendada

```
1. Task 00 - Setup Inicial (OBRIGATÓRIO PRIMEIRO)
   ↓
2. Task 01 - Customer Domain
   ↓
3. Task 02 - Charge Domain (depende de Task 01)
   ↓
4. Task 03 - Payment Gateway Domain (depende de Task 02)
   ↓
5. Task 04 - Webhook Domain (depende de Tasks 02 e 03)
   ↓
6. Task 05 - Authentication & Authorization (pode ser feito em paralelo)
   ↓
7. Task 06 - Testing & Quality (deve acompanhar todas as tasks)
   ↓
8. Task 07 - Documentation & Deployment (antes de produção)
```

---

## 📊 Progresso Geral

| Task | Status | Prioridade | Domínio |
|------|--------|------------|---------|
| 00 - Setup Inicial | ⬜ Pendente | 🔴 Crítica | Infraestrutura |
| 01 - Customer Domain | ⬜ Pendente | 🔴 Alta | Customer |
| 02 - Charge Domain | ⬜ Pendente | 🔴 Alta | Charge |
| 03 - Payment Gateway | ⬜ Pendente | 🔴 Alta | Payment Gateway |
| 04 - Webhook Domain | ⬜ Pendente | 🔴 Alta | Webhook |
| 05 - Auth & Authorization | ⬜ Pendente | 🟡 Média | Security |
| 06 - Testing & Quality | ⬜ Pendente | 🟡 Média | Quality |
| 07 - Documentation | ⬜ Pendente | 🟢 Baixa | Documentation |

**Legenda:**
- ⬜ Pendente
- 🟡 Em Progresso
- ✅ Concluído
- ⚠️ Bloqueado

---

## 🎯 Padrões Arquiteturais Aplicados

Todas as tasks seguem rigorosamente:

### SOLID Principles
- ✅ Single Responsibility Principle
- ✅ Open/Closed Principle
- ✅ Liskov Substitution Principle
- ✅ Interface Segregation Principle
- ✅ Dependency Inversion Principle

### Design Patterns
- ✅ Repository Pattern (abstração de dados)
- ✅ Service Pattern (lógica de negócio)
- ✅ DTO Pattern (transferência de dados)
- ✅ Factory Pattern (criação de objetos)
- ✅ Strategy Pattern (algoritmos intercambiáveis)
- ✅ Observer Pattern (events & listeners)

### Object Calisthenics
- ✅ Um nível de indentação por método
- ✅ Não use else
- ✅ Encapsule primitivas (DTOs)
- ✅ Coleções de primeira classe
- ✅ Um ponto por linha
- ✅ Não use abreviações
- ✅ Mantenha classes pequenas (<200 linhas)
- ✅ Máximo 2 variáveis de instância
- ✅ Sem getters/setters em DTOs (use readonly)

### CQRS Leve
- ✅ Services separados para Commands (Write) e Queries (Read)
- ✅ Otimização independente de leitura e escrita

---

## 📝 Checklist por Task

Cada task contém:
- [ ] Objetivos claros
- [ ] Dependências identificadas
- [ ] Ordem de implementação detalhada
- [ ] Exemplos de código
- [ ] Checklist de qualidade
- [ ] Critérios de aceitação
- [ ] Testes obrigatórios
- [ ] Notas importantes

---

## 🛠️ Ferramentas Utilizadas

- **Framework**: Laravel 11.x
- **PHP**: 8.2+
- **Database**: MySQL 8.0+ / PostgreSQL 14+
- **Queue**: Redis
- **Cache**: Redis
- **Auth**: Laravel Sanctum
- **Tests**: PHPUnit / Pest
- **Code Style**: Laravel Pint (PSR-12)
- **Static Analysis**: PHPStan (level 5+)
- **API Docs**: Scribe
- **Containers**: Docker

---

## 📚 Documentação de Referência

Para detalhes completos sobre padrões arquiteturais, consulte:
- [Prompt.MD](../Prompt.MD) - Documento principal com todos os padrões

### Seções importantes do Prompt.MD:
- **architecture_overview** - Visão geral da arquitetura
- **architectural_patterns** - Padrões obrigatórios
- **solid_principles** - Princípios SOLID detalhados
- **object_calisthenics** - Regras de qualidade de código
- **code_standards** - Padrões de código
- **implementation_workflow** - Fluxo de implementação
- **forbidden_practices** - Práticas proibidas
- **communication_protocol** - Protocolo de comunicação

---

## ✅ Critérios de Qualidade Globais

Aplicam-se a TODAS as tasks:

### Arquitetura
- [ ] SOLID principles seguidos
- [ ] Object Calisthenics aplicado
- [ ] Padrões de design apropriados
- [ ] Separação clara de responsabilidades
- [ ] Baixo acoplamento, alta coesão

### Código
- [ ] Type hints em TODOS os métodos
- [ ] Sem uso de else desnecessário
- [ ] Nomes descritivos (sem abreviações)
- [ ] Métodos com máximo 20 linhas
- [ ] Classes com máximo 200 linhas
- [ ] Máximo 2 variáveis de instância

### Validação
- [ ] FormRequest para validação HTTP
- [ ] Validação de domínio em DTOs
- [ ] Regras de negócio em Services

### Performance
- [ ] Eager loading para evitar N+1
- [ ] Queries otimizadas
- [ ] Índices apropriados
- [ ] Paginação obrigatória em listings

### Testes
- [ ] Feature tests para endpoints
- [ ] Unit tests para lógica de negócio
- [ ] Cobertura mínima 80%
- [ ] Testes de casos de sucesso E falha

### Segurança
- [ ] SQL Injection prevenido (Eloquent/Query Builder)
- [ ] XSS prevenido (validação + escape)
- [ ] CSRF protection ativo
- [ ] Validação de input
- [ ] Autenticação/Autorização

---

## 🚨 Práticas PROIBIDAS

Em TODAS as tasks, NUNCA:

❌ Lógica de negócio em Controllers
❌ Queries diretas em Controllers
❌ Arrays ao invés de DTOs
❌ Omitir type hints
❌ Usar else desnecessário
❌ Nomes genéricos/abreviados
❌ Depender de implementação concreta
❌ Services monolíticos
❌ Commits sem testes

---

## 💡 Dicas de Implementação

1. **Sempre comece pelos DTOs e Enums**
   - Define contratos claros
   - Type safety desde o início

2. **Migrations antes de Models**
   - Estrutura de dados primeiro
   - Models refletem o banco

3. **Interfaces antes de Implementations**
   - Permite troca de implementação
   - Facilita testes (mocking)

4. **Testes junto com implementação**
   - TDD quando possível
   - Garante qualidade desde início

5. **Use Factories para testes**
   - Facilita criação de dados
   - Testes mais limpos

6. **Events para desacoplamento**
   - Operações assíncronas
   - Extensibilidade

---

## 📞 Suporte

Ao trabalhar em uma task:

1. Leia a task completa antes de começar
2. Verifique dependências
3. Siga a ordem de implementação
4. Use o checklist de qualidade
5. Valide critérios de aceitação
6. Consulte o Prompt.MD para dúvidas

---

## 🎓 Recursos de Aprendizado

- [Laravel Documentation](https://laravel.com/docs)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)
- [Design Patterns](https://refactoring.guru/design-patterns)
- [Object Calisthenics](https://williamdurand.fr/2013/06/03/object-calisthenics/)
- [PSR-12](https://www.php-fig.org/psr/psr-12/)

---

**Última atualização**: 2024-10-15

**Versão**: 1.0.0
