# Laravel Stateless Tenancy

Um pacote Laravel (Library) para gerenciamento de **Multi-tenancy Stateless** baseado em JWT. Ele provê autenticação, controle de contas (Accounts) e RBAC (Roles/Permissions) de forma isolada e reutilizável.

## 🎯 Conceitos Chaves

1. **Stateless Auth com JWT:** A Conta ativa (`Account`) e as permissões ativas são injetadas nas *claims* do Token JWT.
2. **Multi-tenancy N:M:** Um Usuário pode pertencer a múltiplas Contas.
3. **Isolamento de Dados:** O pacote fornece a Trait `BelongsToAccount` que adiciona um `GlobalScope` isolando queries pelo tenant atual.

---

### ⚠️ A Regra de Ouro do RBAC
**Não existem permissões diretas para usuários individuais.**

Para manter a arquitetura simples e performática:
- As **Permissões** pertencem exclusivamente às **Roles** (Cargos/Papéis).
- Os **Usuários** recebem **Roles** dentro do contexto de uma **Conta (Account)**.

Se um usuário precisa de uma permissão específica, crie uma `Role` exclusiva e atribua a ele naquela conta. Isso elimina conflitos de permissões cargo vs. usuário.

---

## 📦 Instalação

Você pode instalar o pacote via composer:

```bash
composer require fernandoguiao/laravel-stateless-tenancy
```

### 1. Configuração

Publique as configurações e migrations do pacote:

```bash
php artisan vendor:publish --tag=stateless-tenancy-config
php artisan vendor:publish --tag=stateless-tenancy-migrations
```

Isso criará o arquivo `config/stateless-tenancy.php`. Edite-o para apontar para seus Models de `User` e `Account`:

```php
'user_model' => \App\Models\User::class,
'account_model' => \App\Models\Company::class, // Caso você chame sua Account de Company
'account_primary_key' => 'id',
'account_foreign_key' => 'company_id',
```

### 2. Preparando os Models

No seu Model hospedeiro de **Usuário** (ex: `User`), adicione a Trait `HasAccounts`:

```php
namespace App\Models;

use FernandoGuiao\StatelessTenancy\Traits\HasAccounts;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasAccounts;
    // ...
}
```

No Model de **Conta/Tenant** (ex: `Account` ou `Company`), você não precisa adicionar Traits, apenas garanta que é o mesmo Model definido na `config`.

Em qualquer **Model que pertence à Conta** (ex: `Invoice`, `Client`), adicione a Trait `BelongsToAccount`:

```php
namespace App\Models;

use FernandoGuiao\StatelessTenancy\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use BelongsToAccount;
    // ...
}
```

Isso fará com que qualquer consulta a `Invoice::all()` filtre automaticamente pelos registros da Conta ativa no JWT.

### 3. Migrations

As migrations copiadas para seu projeto utilizam tipagem baseada na sua configuração. Revise as migrations geradas na pasta `database/migrations` (ex: `roles`, `permissions`, `permission_role`, `account_role_user`) e rode:

```bash
php artisan migrate
```

*(Opcional)* Se quiser que o login pegue uma conta padrão quando o ID não for passado, crie uma migration adicionando a coluna `$table->unsignedBigInteger('default_account_id')->nullable();` na sua tabela de `users`.

---

## 🚀 Uso e Exemplos

### Gerando Token de Autenticação (Login)

```php
use FernandoGuiao\StatelessTenancy\Services\AuthService;

class AuthController extends Controller
{
    public function login(Request $request, AuthService $authService)
    {
        $credentials = $request->only('email', 'password');

        // Pode passar o ID da conta que o usuário deseja logar
        // Se for null, o pacote busca a "default_account_id" ou a primeira conta.
        $accountId = $request->input('account_id');

        $tokens = $authService->attempt($credentials, $accountId);

        return response()->json($tokens);
        // Retorna ['token' => '...', 'refreshToken' => '...']
    }
}
```

### Refresh Token

```php
use FernandoGuiao\StatelessTenancy\Services\AuthService;

class AuthController extends Controller
{
    public function refresh(Request $request, AuthService $authService)
    {
        $accountId = $request->input('account_id'); // Opcional, para trocar de conta
        $tokens = $authService->refreshToken($accountId);

        return response()->json($tokens);
    }
}
```

### Protegendo Rotas (Middleware)

No `app/Http/Kernel.php` (ou bootstrap do Laravel 11), adicione o middleware:

```php
protected $routeMiddleware = [
    'tenancy.auth' => \FernandoGuiao\StatelessTenancy\Http\Middleware\JwtAccountAuth::class,
];
```

Nas rotas:
```php
Route::middleware('tenancy.auth')->group(function () {
    Route::get('/invoices', [InvoiceController::class, 'index']);
});
```

### Acessando os dados do Token Ativo

Qualquer lugar do código protegido pelo Middleware:

```php
use FernandoGuiao\StatelessTenancy\Services\AuthService;

$accountId = AuthService::accountId(); // ID da Conta Atual
$user = AuthService::token()->getUser(); // Array com ID e nome
$hasPerm = AuthService::token()->hasPermission('create-invoices'); // true / false
```

## Tratamento de Erros

O middleware lança Exceções que formatam automaticamente o retorno JSON com status `401`.

## Licença
MIT.
