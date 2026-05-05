<?php

namespace FernandoGuiao\StatelessTenancy\Services;

use FernandoGuiao\StatelessTenancy\Exceptions\RefreshTokenExpiredException;
use FernandoGuiao\StatelessTenancy\Exceptions\TokenExpiredException;
use FernandoGuiao\StatelessTenancy\Exceptions\UnauthorizedException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use DateTimeImmutable;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Token\Builder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\JWT\Validation\Validator;

class AuthService
{
    protected ?string $token;
    protected string $key;
    private Parser $parser;
    private ?Token $parsedToken = null;

    public function __construct($token = null)
    {
        $this->key = config('stateless-tenancy.jwt_secret');
        if (empty($this->key)) {
            $this->key = 'default-secret-key-for-testing-only-change-in-production';
        }
        $this->token = $token;
        $this->parser = new Parser(new JoseEncoder());
        if (!empty($token)) {
            try {
                $this->parsedToken = $this->parser->parse($token);
            } catch (\Throwable $e) {
                $this->parsedToken = null;
            }
        }
    }

    public static function accountId(): mixed
    {
        try { return self::token()->getAccountId(); } catch (\Exception $e) { return null; }
    }

    public static function token(): self
    {
        if (app()->bound(AuthService::class)) { return app(AuthService::class); }
        $token = request()->bearerToken();
        return app()->make(AuthService::class, ['token' => $token]);
    }

    /**
     * Authenticate a user by credentials and return tokens.
     */
    public function attempt(array $credentials, $accountUniqueId = null): array
    {
        $userModelClass = config('stateless-tenancy.user_model');
        $user = $userModelClass::where('email', $credentials['email'] ?? null)->first();

        if (!$user || !Hash::check($credentials['password'] ?? '', $user->password)) {
            throw new UnauthorizedException();
        }

        return $this->getTokens($user, $accountUniqueId);
    }

    public function getTokens(Authenticatable $user, $accountUniqueId = null): array
    {
        return [
            'token' => $this->issueToken(user: $user, isRefresh: false, accountUniqueId: $accountUniqueId),
            'refreshToken' => $this->issueToken(user: $user, isRefresh: true, accountUniqueId: $accountUniqueId),
        ];
    }

    public function refreshToken($accountUniqueId = null): array
    {
        if (!$this->parsedToken) {
            throw new UnauthorizedException();
        }

        $userModelClass = config('stateless-tenancy.user_model');
        $user = $userModelClass::findOrFail($this->parsedToken->claims()->get('sub'));

        if ($accountUniqueId === null) {
            $accountUniqueId = $this->parsedToken->claims()->get('activeAccount')['id'];
        } else {
            $accountPrimaryKey = config('stateless-tenancy.account_primary_key', 'id');
            $account = $user->accounts()->where(app(config('stateless-tenancy.account_model'))->getTable().'.'.$accountPrimaryKey, $accountUniqueId)->first();
            if ($account === null) {
                throw new UnauthorizedException();
            }
        }

        return $this->getTokens($user, $accountUniqueId);
    }

    /**
     * @throws UnauthorizedException
     */
    public function issueToken(Authenticatable $user, $isRefresh = false, $accountUniqueId = null): string
    {
        $tokenBuilder = (new Builder(new JoseEncoder(), ChainedFormatter::default()));
        $algorithm    = new Sha256();
        $signingKey   = InMemory::plainText($this->key);
        $expirationMinutes = $isRefresh ? config('stateless-tenancy.refresh_token_expiration') : config('stateless-tenancy.token_expiration');

        $now   = new DateTimeImmutable();

        $accountPrimaryKey = config('stateless-tenancy.account_primary_key', 'id');

        if ($accountUniqueId !== null) {
            $account = $user->accounts()->where(app(config('stateless-tenancy.account_model'))->getTable().'.'.$accountPrimaryKey, $accountUniqueId)->first();
        } else {
            // Check if default_account_id exists and is not null
            if (isset($user->default_account_id) && $user->default_account_id !== null) {
                $account = $user->accounts()->where(app(config('stateless-tenancy.account_model'))->getTable().'.'.$accountPrimaryKey, $user->default_account_id)->first();
            } else {
                $account = $user->accounts()->first();
            }
        }

        if ($account === null) {
            throw new UnauthorizedException();
        }

        // Must implement HasAccounts trait
        if (method_exists($user, 'getAccountPermissions')) {
            $permissions = $user->getAccountPermissions($account->{$accountPrimaryKey});
        } else {
            $permissions = [];
        }

        $token = $tokenBuilder
            ->issuedBy(config('app.name', 'Laravel'))
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify('+'. $expirationMinutes .' minutes'))
            ->relatedTo((string) $user->id)
            ->withClaim('isRefresh', $isRefresh)
            ->withClaim('activeAccount',
                [
                    'id' => $account->{$accountPrimaryKey},
                    // We only store the ID to be generic, the app can fetch more if needed
                ]
            )
            ->withClaim('user',
                [
                    'id' => $user->id,
                ]
            )
            ->withClaim('permissions', $permissions)
            ->getToken($algorithm, $signingKey);

        return $token->toString();
    }

    public function validate() : bool
    {
        if (!$this->parsedToken) {
            return false;
        }

        $signer = new Sha256();
        $publicKey = InMemory::plainText($this->key);

        $validator = new Validator();
        $constraints = [
            new SignedWith($signer, $publicKey),
            new StrictValidAt(SystemClock::fromUTC())
        ];

        try {
            $validator->assert($this->parsedToken, ...$constraints);
        } catch (\Lcobucci\JWT\Validation\RequiredConstraintsViolated $e) {
            if (isset($e->violations()[0]) && $e->violations()[0]->getMessage() === 'The token is expired') {
                $isRefresh = $this->parsedToken->claims()->get('isRefresh');
                if ($isRefresh === true) {
                    throw new RefreshTokenExpiredException();
                }
                throw new TokenExpiredException();
            }
            return false;
        }
        return true;
    }

    public function validateRefresh() : bool
    {
        if (!$this->parsedToken || $this->parsedToken->claims()->get('isRefresh') !== true) {
            return false;
        }
        return $this->validate();
    }

    public function getClaim(?string $claim) : array|string|int|null
    {
        if (!$this->parsedToken) return null;
        if ($claim) {
            return $this->parsedToken->claims()->get($claim);
        }
        return $this->parsedToken->claims()->all();
    }

    public function hasPermission(string $permission) : bool
    {
        if (!$this->parsedToken) return false;
        $permissions = $this->parsedToken->claims()->get('permissions');
        return isset($permissions[$permission]);
    }

    public function getAccount() : ?array
    {
        if (!$this->parsedToken) return null;
        return $this->parsedToken->claims()->get('activeAccount');
    }

    public function getAccountId() : mixed
    {
        $account = $this->getAccount();
        return $account ? $account['id'] : null;
    }

    public function getUser() : ?array
    {
        if (!$this->parsedToken) return null;
        return $this->parsedToken->claims()->get('user');
    }

    public function getUserId() : mixed
    {
        $user = $this->getUser();
        return $user ? $user['id'] : null;
    }
}
