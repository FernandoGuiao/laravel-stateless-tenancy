# Escopo de Desenvolvimento: Pacote `laravel-stateless-tenancy`

## 🎯 Objetivo Principal
Criar um pacote Laravel (Library) para gerenciamento de **Multi-tenancy Stateless** baseado em JWT. 
Este pacote é uma abstração e evolução da lógica de autenticação e permissionamento implementada originalmente no repositório `FernandoGuiao/SistemaPecuarioApi`. O objetivo é que este pacote possa ser instalado via Composer em qualquer projeto Laravel (como o novo sistema de NFSe ou o próprio Pecuário no futuro), provendo autenticação, controle de inquilinos (Tenants) e RBAC (Roles/Permissions) de forma isolada e reutilizável.

## 🧠 Arquitetura e Conceitos Chaves
1. **Stateless Auth com JWT:** A sessão do usuário, o Tenant ativo (ex: Conta/Empresa) e as permissões ativas desse usuário dentro desse Tenant específico são injetadas nas *claims* do Token JWT. Isso evita consultas ao banco de dados para checar permissões a cada requisição.
2. **Multi-tenancy N:M:** Um Usuário pode pertencer a múltiplos Tenants. O vínculo é feito através de uma tabela pivot (ex: `tenant_role_user`), onde é definida qual a `Role` (Papel) daquele usuário naquele Tenant específico.
3. **Isolamento de Dados (Global Scopes):** O pacote deve fornecer uma `Trait` (ex: `BelongsToTenant`) que, ao ser adicionada a qualquer Model, injeta um `GlobalScope` para filtrar automaticamente todas as queries pelo ID do Tenant ativo no token.
4. **Altamente Configurável:** Como diferentes sistemas usam nomes diferentes (no Pecuário é `Account`, na NFSe é `Company`), o pacote não deve engessar os nomes das tabelas. Tudo deve ser guiado por um arquivo de configuração.

---

## 📦 Estrutura do Pacote

A IA que assumir a construção deste pacote deve criar a seguinte estrutura de diretórios:

```text
laravel-stateless-tenancy/
├── composer.json
├── config/
│   └── jwt-tenancy.php
├── database/
│   └── migrations/
│       ├── create_roles_table.php
│       ├── create_permissions_table.php
│       └── create_tenant_role_user_table.php
├── src/
│   ├── JwtTenancyServiceProvider.php
│   ├── Exceptions/
│   │   ├── TokenExpiredException.php
│   │   ├── RefreshTokenExpiredException.php
│   │   └── UnauthorizedException.php
│   ├── Http/
│   │   └── Middleware/
│   │       └── JwtTenantAuth.php
│   ├── Models/
│   │   ├── Role.php
│   │   └── Permission.php
│   ├── Services/
│   │   └── AuthService.php
│   └── Traits/
│       ├── HasTenants.php          (Para o Model User da aplicação)
│       └── BelongsToTenant.php     (Para os Models que pertencem ao Tenant)
└── README.md
```

---

## 🛠️ Especificações de Implementação (Passo a Passo para a IA)

### 1. Dependências do `composer.json`
O pacote deve exigir o PHP 8.1+, suporte ao Laravel 10.x/11.x, e as bibliotecas JWT da Lcobucci.
**Requisitos essenciais:**
- `"illuminate/support": "^10.0|^11.0"`
- `"lcobucci/jwt": "^5.0"`
- `"lcobucci/clock": "^3.0"`
- Configurar o `Auto-Discovery` no bloco `extra.laravel.providers`.

### 2. O Arquivo de Configuração (`config/jwt-tenancy.php`)
Deve ser flexível. O pacote deve ler essas configurações em vez de chumbar nomes no código.
Exemplo de chaves necessárias:
- `tenant_model` (Ex: `\App\Models\Company::class`)
- `user_model` (Ex: `\App\Models\User::class`)
- `tenant_foreign_key` (Ex: `company_id`)
- `jwt_secret` (Buscar do `.env` original)
- `token_expiration` e `refresh_token_expiration`.

### 3. Migrations
O pacote deve prover as migrations para as entidades auxiliares, mas usando as FKs configuradas.
- `roles` (id, name, etc)
- `permissions` (id, name, etc)
- A tabela Pivot, cujo nome ideal é dinâmico ou algo genérico como `tenant_role_user`. Ela deve conter as FKs de `user_id`, `role_id` e a FK do tenant configurada (`tenant_column`).

### 4. O Motor: `AuthService.php`
Este arquivo é o coração do pacote.
**Responsabilidades:**
- Gerar o token JWT (`getTokens`).
- Injetar as *claims* essenciais:
  - `sub` (User ID)
  - `activeTenant` (Array com ID e dados do Tenant ativo).
  - `permissions` (Array listando as permissões vindas da relação User -> Tenant -> Role -> Permission).
- Validar as assinaturas e a expiração usando `StrictValidAt` e `SignedWith`.
- Fornecer métodos estáticos para acessar o Tenant atual em qualquer lugar da aplicação (ex: `AuthService::tenantId()`).
*(Referência: Basear-se fortemente no `AuthService` do repo `FernandoGuiao/SistemaPecuarioApi`, mas trocando as menções "Account" para variáveis dinâmicas de "Tenant").*

### 5. Traits de Integração
- **`HasTenants`**: Será usada no Model `User` do projeto hospedeiro. Deve prover métodos como:
  - `tenants()` (BelongsToMany apontando para o model configurado via `config('jwt-tenancy.tenant_model')`).
  - `getTenantPermissions(string $tenantId)`: Busca as permissões específicas do usuário naquele tenant.
- **`BelongsToTenant`**: Será usada em qualquer Model da aplicação que pertença ao Tenant (ex: Clientes, Faturas, Notas Fiscais).
  - Deve ter uma relação `tenant()` ou similar.
  - **MUITO IMPORTANTE:** Deve implementar o método estático `bootBelongsToTenant()` para registrar um **Global Scope**. Esse escopo deve filtrar automaticamente todas as queries adicionando `where('tenant_id', AuthService::tenantId())` caso exista um token ativo.

### 6. Middleware `JwtTenantAuth`
Deve validar se a requisição possui um *Bearer Token*.
Se o token for válido e não estiver expirado, ele permite o fluxo.
Se for inválido, lança a exceção correta (`UnauthorizedException`, `TokenExpiredException`), que devem retornar JSON `401` limpo.

### 7. Service Provider
O `JwtTenancyServiceProvider` deve:
- Fazer o `mergeConfigFrom`.
- Fazer o `publishes` do arquivo de configuração para que o desenvolvedor possa editar na aplicação rodando `php artisan vendor:publish`.
- Carregar as migrations `loadMigrationsFrom`.
- Registrar um alias no app container para o AuthService, se necessário.

---

## 🚀 Como a IA (Agente) deve agir ao assumir este escopo:
1. Comece inicializando o repositório (`git init`) e o `composer.json`.
2. Crie a estrutura de diretórios do pacote.
3. Desenvolva os arquivos na ordem lógica: *Config -> Traits/Models -> AuthService -> Middleware -> ServiceProvider*.
4. Mantenha o código limpo, seguindo as PSRs e adicionando tipagem estrita do PHP 8.1.
5. Sempre que precisar ler configurações globais dinâmicas, use a função `config('jwt-tenancy.key')`.
6. Escreva um `README.md` robusto ensinando como instalar o pacote, como configurar o `.env`, como adicionar o Trait no User e como emitir um token.