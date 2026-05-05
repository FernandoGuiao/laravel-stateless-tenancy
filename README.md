# Laravel Stateless Tenancy

A robust Laravel package for managing **Stateless Multi-tenancy** using JSON Web Tokens (JWT). This library provides complete isolation for authentication, account management, and Role-Based Access Control (RBAC) in a highly scalable and reusable way.

---

## 🎯 The Power of Stateless Multi-tenancy

Traditional multi-tenancy in Laravel relies heavily on sessions or storing the "active tenant" in server memory. This stateful approach falls short when:
- **Scaling Horizontally**: Session-based auth requires sticky sessions or centralized storage (like Redis) for every single request.
- **Building APIs or Microservices**: Modern SPAs (Single Page Applications) and mobile apps require RESTful, stateless interactions.
- **Performance**: Querying the database to fetch the active user and tenant on every request adds overhead.

### Why Stateless is Better?

1. **Self-contained Claims**: The active `Account` ID and the user's active permissions are embedded directly into the JWT claims. The server doesn't need to perform database lookups to authorize the user for that specific tenant.
2. **Infinite Scalability**: Since there is no session state to synchronize, you can add as many servers as you want behind a load balancer. If the JWT is valid and properly signed, the request is processed instantly.
3. **Decoupled Architecture**: Perfect for Vue/React/Angular frontends and mobile applications communicating with your Laravel API.

---

## 🔄 The Complete JWT Token Flow

When using this package, your application should adopt the following flow:

1. **Authentication (Login)**:
   - The user provides their credentials (e.g., email and password) and optionally an `account_id` they wish to access.
   - The API verifies the credentials and checks if the user belongs to the requested account.
   - The API generates and returns two tokens:
     - **Access Token (JWT)**: Short-lived (e.g., 15 minutes). Contains user identity, active account ID, and RBAC permissions in its payload.
     - **Refresh Token**: Long-lived (e.g., 7 days). Stored securely (e.g., HttpOnly cookie or secure storage) to obtain new access tokens without requiring the user to log in again.
2. **Making Authenticated Requests**:
   - The frontend attaches the Access Token in the `Authorization: Bearer <token>` header of every API request.
   - The `JwtAccountAuth` middleware decodes the token, validates the signature, and sets the active `AuthService` state. It **does not** query the database.
3. **Token Expiration & Refresh**:
   - When the Access Token expires, the API responds with a `401 Unauthorized` (TokenExpiredException).
   - The frontend catches this 401, sends the Refresh Token to the `/refresh` endpoint, receives a new Access Token, and retries the original request seamlessly.
4. **Switching Accounts**:
   - To switch the active tenant, the frontend requests a new token using the `/refresh` endpoint and passes a different `account_id` in the request body. The API issues a new JWT containing the permissions for the new account.
5. **Action Tokens (Stateless Notifications)**:
   - For flows like password reset or email verification, the API generates "Action Tokens". These are thin JWTs valid for a single action (e.g., `password_reset`). They lack standard authorization claims, preventing them from being used as a normal bearer token to access the API.

---

## 🔑 The Golden Rule of RBAC

**There are no direct permissions assigned to individual users.**

To keep the architecture simple and highly performant:
- **Permissions** belong exclusively to **Roles**.
- **Users** are assigned **Roles** within the context of a specific **Account**.

If a single user requires a unique permission, create an exclusive `Role` for them and assign it within that account. This eliminates complex resolution logic between user-level and role-level permissions.

---

## 📦 Installation

Install the package via Composer:

```bash
composer require fernandoguiao/laravel-stateless-tenancy
```

### 1. Configuration

Publish the package configuration and migrations:

```bash
php artisan vendor:publish --tag=stateless-tenancy-config
php artisan vendor:publish --tag=stateless-tenancy-migrations
```

This generates `config/stateless-tenancy.php`. Edit it to point to your actual User and Account models:

```php
return [
    'user_model' => \App\Models\User::class,
    'account_model' => \App\Models\Company::class, // E.g., If you call your Account "Company"
    'account_primary_key' => 'id',
    'account_foreign_key' => 'company_id',

    // JWT Settings
    'jwt_secret' => env('JWT_SECRET', 'your-256-bit-secret'),
    'jwt_algo' => 'HS256',
    'access_token_ttl' => 15, // minutes
    'refresh_token_ttl' => 10080, // minutes (7 days)
];
```

### 2. Preparing the Models

On your **User** model (e.g., `app/Models/User.php`), add the `HasAccounts` and `SendsStatelessNotifications` traits:

```php
namespace App\Models;

use FernandoGuiao\StatelessTenancy\Traits\HasAccounts;
use FernandoGuiao\StatelessTenancy\Traits\SendsStatelessNotifications;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasAccounts, SendsStatelessNotifications, Notifiable;
    // ...
}
```

On your **Account/Tenant** model (e.g., `Company` or `Account`), you do not need any special traits. Just ensure it matches the `account_model` defined in the config.

On any **Model that belongs to an Account** (e.g., `Invoice`, `Project`), add the `BelongsToAccount` trait:

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

*Note: The `BelongsToAccount` trait automatically applies a Global Scope. Queries like `Invoice::all()` will only return records belonging to the active Account injected in the JWT.*

### 3. Migrations

The package dynamically builds migrations based on your configuration. Review the generated migrations in `database/migrations` (e.g., `roles`, `permissions`, `permission_role`, `account_role_user`) and run:

```bash
php artisan migrate
```

*(Optional)* If you want the login flow to automatically select a default account when the frontend doesn't provide one, add an `unsignedBigInteger('default_account_id')->nullable()` column to your `users` table via your own migration.

---

## 🚀 Detailed Use Cases & Examples

### 1. Generating Authentication Tokens (Login)

```php
use FernandoGuiao\StatelessTenancy\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request, AuthService $authService)
    {
        $credentials = $request->only('email', 'password');

        // The user can optionally request which account they want to log into.
        // If null, the package attempts to use the "default_account_id" or the first attached account.
        $accountId = $request->input('account_id');

        $tokens = $authService->attempt($credentials, $accountId);

        return response()->json([
            'access_token' => $tokens['token'],
            'refresh_token' => $tokens['refreshToken'],
            'token_type' => 'bearer'
        ]);
    }
}
```

### 2. Refreshing Tokens (And Switching Accounts)

```php
use FernandoGuiao\StatelessTenancy\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function refresh(Request $request, AuthService $authService)
    {
        // To switch accounts without requiring the user's password, pass the new account ID here.
        // It must be an account the user actually belongs to.
        $accountId = $request->input('account_id');

        $tokens = $authService->refreshToken($accountId);

        return response()->json([
            'access_token' => $tokens['token'],
            'refresh_token' => $tokens['refreshToken']
        ]);
    }
}
```

### 3. Protecting Routes (Middleware)

Register the middleware in `app/Http/Kernel.php` (or in your application bootstrapping for Laravel 11+):

```php
protected $routeMiddleware = [
    'tenancy.auth' => \FernandoGuiao\StatelessTenancy\Http\Middleware\JwtAccountAuth::class,
];
```

Protect your API routes:
```php
Route::middleware('tenancy.auth')->group(function () {
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
});
```

### 4. Accessing Active Token Data

Anywhere inside a protected route, you can safely extract the stateless context:

```php
use FernandoGuiao\StatelessTenancy\Services\AuthService;

// Get the active Account ID directly
$activeAccountId = AuthService::accountId();

// Get the decoded JWT User payload (Array with ID, name, etc.)
$userPayload = AuthService::token()->getUser();

// Check if the token contains a specific permission
if (AuthService::token()->hasPermission('create-invoices')) {
    // allow creation...
}
```

### 5. Managing Accounts & Roles (`HasAccounts` Trait)

The `HasAccounts` trait provides powerful helpers for managing users within tenants. You can use these during onboarding or administration tasks.

```php
$user = User::find(1);
$account = Account::find(10);
$adminRole = Role::where('name', 'admin')->first();

// Attach a user to an account with a specific role
$user->attachAccountWithRoles($account, $adminRole);

// Sync roles (replaces existing roles for that account)
$user->syncAccountRoles($account, ['manager', 'editor']);

// Remove specific roles
$user->removeAllRolesFromAccount($account); // Removes all roles, effectively detaching
$user->detachAccount($account); // Alias for removeAllRolesFromAccount

// RBAC Checks (Queries the database, usually done during administration, not in typical requests)
$user->hasAccountRole($account, 'admin'); // bool
$user->hasAnyAccountRole($account, ['editor', 'viewer']); // bool
$user->hasAccountPermission($account, 'delete-users'); // bool
```

### 6. Data Isolation (`BelongsToAccount` Trait)

Any Model utilizing `BelongsToAccount` is automatically scoped.

```php
// If the authenticated token belongs to Account ID 5:

// This will ONLY return invoices where company_id = 5.
$invoices = Invoice::all();

// This will ONLY create the invoice for company_id = 5.
$invoice = Invoice::create(['amount' => 100]);

// To bypass the global scope (e.g., in a background job or super-admin script):
$allInvoicesInSystem = Invoice::withoutAccountScope()->get();
```

### 7. Stateless Notifications (Action Tokens)

The package provides native methods to issue "Action Tokens". These are thin JWTs specifically designed for a single action, completely excluding the standard authentication claims like `permissions` to prevent them from being abused as Bearer tokens.

**Requesting a Password Reset:**
```php
use App\Models\User;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $user = User::where('email', $request->email)->firstOrFail();

        // Send notification. The package will append `?token=YOUR_THIN_JWT` to this URL.
        $user->sendStatelessPasswordResetNotification('https://your-frontend.com/reset-password');

        return response()->json(['message' => 'Reset link sent!']);
    }
}
```

**Validating an Action Token:**
When the user clicks the link, the frontend extracts the `token` from the URL and sends it to your API.

```php
use FernandoGuiao\StatelessTenancy\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{
    public function reset(Request $request, AuthService $authService)
    {
        // Gracefully validates the Action Token.
        // Returns null if invalid, expired, or if the action mismatch.
        $user = $authService->validateActionToken($request->token, 'password_reset');

        if (!$user) {
            return response()->json(['error' => 'Invalid or expired link.'], 400);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['message' => 'Password reset successful!']);
    }
}
```
*(You can use the exact same logic for email verification by calling `$user->sendStatelessEmailVerificationNotification()` and validating the action `'email_verification'`)*.

---

## 🛑 Exception Handling

The `JwtAccountAuth` middleware automatically throws specific exceptions when things go wrong:
- `UnauthorizedException` (401): General failure, missing token, or bad signature.
- `TokenExpiredException` (401): The Access Token has expired.
- `RefreshTokenExpiredException` (401): The Refresh Token has expired.

Your Laravel exception handler will automatically format these into standard JSON error responses.

## License

MIT.
