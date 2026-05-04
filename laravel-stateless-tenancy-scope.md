# Escopo de Desenvolvimento: Pacote `laravel-stateless-tenancy`

## 🎯 Objetivo Principal
Criar um pacote Laravel (Library) para gerenciamento de **Multi-tenancy Stateless** baseado em JWT.
Este pacote é uma abstração e evolução da lógica de autenticação e permissionamento implementada originalmente no repositório `FernandoGuiao/SistemaPecuarioApi`. O objetivo é que este pacote possa ser instalado via Composer em qualquer projeto Laravel (como um sistema Pecuário, NFSe ou ERP futuro), provendo autenticação, controle de contas (Accounts) e RBAC (Roles/Permissions) de forma isolada e reutilizável.

## 🧠 Arquitetura e Conceitos Chaves
1. **Stateless Auth com JWT:** A sessão do usuário, a Conta ativa (`Account`) e as permissões ativas desse usuário dentro dessa Conta específica são injetadas nas *claims* do Token JWT. Isso evita consultas ao banco de dados para checar permissões a cada requisição.
2. **Multi-tenancy N:M:** Um Usuário pode pertencer a múltiplas Contas (`Accounts`). O vínculo é feito através de uma tabela pivot (ex: `account_role_user`), onde é definida qual a `Role` (Papel) daquele usuário naquela Conta específica.
3. **Isolamento de Dados (Global Scopes):** O pacote deve fornecer uma `Trait` (ex: `BelongsToAccount`) que, ao ser adicionada a qualquer Model, injeta um `GlobalScope` para filtrar automaticamente todas as queries pelo ID da Conta ativa no token.
4. **Altamente Configurável:** Como diferentes sistemas podem usar a tabela principal de forma diferente (em alguns sistemas ela é a própria `Account`, em outros pode ser estendida para `Company`), o pacote não deve engessar o nome da tabela primária nas Models internas. Tudo deve ser guiado por um arquivo de configuração.

### ⚠️ Regra de Ouro do RBAC (Para incluir no README)
**Não existem permissões diretas para usuários individuais.**
Para manter a arquitetura extremamente simples, performática e com fácil manutenção:
- As **Permissões** pertencem exclusivamente às **Roles** (Cargos/Papéis).
- Os **Usuários** recebem **Roles** dentro do contexto de uma **Conta (Account)**.

Se um sistema precisar que um usuário único tenha uma permissão altamente específica que ninguém mais tem, a aplicação deve criar uma `Role` exclusiva para ele (ex: "Gerente Especial") e atribuir essa Role ao usuário. Essa regra elimina a complexidade de resolver conflitos entre permissões de nível de cargo vs permissões de nível de usuário, simplificando imensamente as *queries* e a leitura do Token JWT.

---

## 📦 Estrutura do Pacote

A IA que assumir a construção deste pacote deve criar a seguinte estrutura de diretórios:

```text
laravel-stateless-tenancy/
├── composer.json
├── config/
│   └── stateless-tenancy.php
├── database/
│   └── migrations/
│       ├── create_roles_table.php
│       ├── create_permissions_table.php
│       └── create_account_role_user_table.php
├── src/
│   ├── StatelessTenancyServiceProvider.php
│   ├── Exceptions/
│   │   ├── TokenExpiredException.php
│   │   ├── RefreshTokenExpiredException.php
│   │   └── UnauthorizedException.php
│   ├── Http/
│   │   └── Middleware/
│   │       └── JwtAccountAuth.php
│   ├── Models/
│   │   ├── Role.php
│   │   └── Permission.php
│   ├── Services/
│   │   └── AuthService.php
│   └── Traits/
│       ├── HasAccounts.php          (Para o Model User da aplicação)
│       └── BelongsToAccount.php     (Para os Models que pertencem à Account)
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

### 2. O Arquivo de Configuração (`config/stateless-tenancy.php`)
Deve ser flexível. O pacote deve ler essas configurações em vez de chumbar referências no código.
Exemplo de chaves necessárias:
- `account_model` (Ex: `\App\Models\Account::class`)
- `user_model` (Ex: `\App\Models\User::class`)
- `account_foreign_key` (Ex: `account_id`)
- `jwt_secret` (Buscar do `.env` original)
- `token_expiration` e `refresh_token_expiration`.

### 3. Migrations
O pacote deve prover as migrations para as entidades auxiliares, usando a nomenclatura padrão ou as FKs configuradas.
- `roles` (id, name, account_id nullable, etc)
- `permissions` (id, name, etc)
- A tabela Pivot, cujo nome deve ser `account_role_user`. Ela deve conter as FKs de `user_id`, `role_id` e a FK da conta configurada (`account_column` / `account_id`).

### 4. O Motor: `AuthService.php`
Este arquivo é o coração do pacote.
**Responsabilidades:**
- Gerar o token JWT (`getTokens`).
- Injetar as *claims* essenciais:
    - `sub` (User ID)
    - `activeAccount` (Array com ID e dados da Account ativa).
    - `permissions` (Array listando as permissões vindas estritamente da relação: User -> Account -> Role -> Permission). NUNCA usar permissões diretas.
- Validar as assinaturas e a expiração usando `StrictValidAt` e `SignedWith`.
- Fornecer métodos estáticos para acessar a Account atual em qualquer lugar da aplicação (ex: `AuthService::accountId()`).
  *(Referência: Basear-se fortemente no `AuthService` do repo `FernandoGuiao/SistemaPecuarioApi`).*

### 5. Traits de Integração
- **`HasAccounts`**: Será usada no Model `User` do projeto hospedeiro. Deve prover métodos como:
    - `accounts()` (BelongsToMany apontando para o model configurado via `config('stateless-tenancy.account_model')`).
    - `getAccountPermissions(string $accountId)`: Busca as permissões exclusivas das Roles do usuário naquela Account.
- **`BelongsToAccount`**: Será usada em qualquer Model da aplicação que pertença à Account (ex: Clientes, Faturas, Lotes, Notas).
    - Deve ter uma relação `account()` ou equivalente dinâmico.
    - **MUITO IMPORTANTE:** Deve implementar o método estático `bootBelongsToAccount()` para registrar um **Global Scope**. Esse escopo deve filtrar automaticamente todas as queries adicionando `where('account_id', AuthService::accountId())` caso exista um token ativo.

### 6. Middleware `JwtAccountAuth`
Deve validar se a requisição possui um *Bearer Token*.
Se o token for válido e não estiver expirado, ele permite o fluxo.
Se for inválido, lança a exceção correta (`UnauthorizedException`, `TokenExpiredException`), que devem retornar JSON `401` limpo.

### 7. Service Provider
O `StatelessTenancyServiceProvider` deve:
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
5. Embuta a "Regra de Ouro do RBAC" na documentação final do pacote (`README.md`).
6. Escreva um `README.md` robusto ensinando como instalar o pacote, configurar o `.env`, adicionar a Trait `HasAccounts` no Model User, criar Roles e gerar um Token.
