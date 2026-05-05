<?php

namespace FernandoGuiao\StatelessTenancy\Traits;

use FernandoGuiao\StatelessTenancy\Models\Role;
use FernandoGuiao\StatelessTenancy\Services\AuthService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HasAccounts
{
    /**
     * Get the user's accounts.
     *
     * @return BelongsToMany
     */
    public function accounts() : BelongsToMany
    {
        return $this->belongsToMany(
            related: config('stateless-tenancy.account_model'),
            table: 'account_role_user',
            foreignPivotKey: 'user_id',
            relatedPivotKey: config('stateless-tenancy.account_foreign_key', 'account_id'),
        );
    }

    /**
     * Get the user's roles for a specific account.
     *
     * @param int|string|Model|null $accountId The account ID or Model. Defaults to current tenancy context.
     * @return BelongsToMany
     */
    public function accountRoles(int|string|Model|null $accountId = null) : BelongsToMany
    {
        $accountId = $this->extractAccountId($accountId ?? AuthService::accountId());
        return $this->belongsToMany(
            related: Role::class,
            table: 'account_role_user',
            foreignPivotKey: 'user_id',
            relatedPivotKey: 'role_id',
        )->wherePivot(config('stateless-tenancy.account_foreign_key', 'account_id'), $accountId);
    }

    /**
     * Get an array of permissions the user has for a given account.
     *
     * @param int|string|Model $account The account ID or Model.
     * @return array<string, bool> An associative array where keys are permission names and values are true.
     */
    public function getAccountPermissions(int|string|Model $account) : array
    {
        $accountId = $this->extractAccountId($account);
        $permissions = [];
        $this->accountRoles($accountId)
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->each(function ($permission) use (&$permissions) {
                $permissions[$permission->name] = true;
            });

        return $permissions;
    }

    /**
     * Attach an account to the user with the specified roles.
     *
     * @param int|string|Model $account The account ID or Model.
     * @param int|string|Model|array|Collection $roles A single role ID/Model or an array/Collection of them.
     * @return void
     */
    public function attachAccountWithRoles(int|string|Model $account, int|string|Model|array|Collection $roles) : void
    {
        $accountId = $this->extractAccountId($account);
        $roleIds = $this->extractRoleIds($roles);

        foreach ($roleIds as $roleId) {
            $this->accounts()->attach($accountId, ['role_id' => $roleId]);
        }
    }

    /**
     * Remove all roles associated with a given account for this user.
     *
     * @param int|string|Model $account The account ID or Model.
     * @return void
     */
    public function removeAllRolesFromAccount(int|string|Model $account) : void
    {
        $accountId = $this->extractAccountId($account);
        $this->accounts()->detach($accountId);
    }

    /**
     * Detach the account from the user entirely.
     * This acts as an alias for removeAllRolesFromAccount.
     *
     * @param int|string|Model $account The account ID or Model.
     * @return void
     */
    public function detachAccount(int|string|Model $account) : void
    {
        $this->removeAllRolesFromAccount($account);
    }

    /**
     * Sync the user's roles for a given account (replaces existing roles).
     *
     * @param int|string|Model $account The account ID or Model.
     * @param int|string|Model|array|Collection $roles A single role ID/Model or an array/Collection of them.
     * @return void
     */
    public function syncAccountWithRoles(int|string|Model $account, int|string|Model|array|Collection $roles) : void
    {
        DB::transaction(function () use ($account, $roles) {
            $this->removeAllRolesFromAccount($account);
            $this->attachAccountWithRoles($account, $roles);
        });
    }

    /**
     * Check if the user has a specific role in a given account.
     *
     * @param int|string|Model $account The account ID or Model.
     * @param int|string $role The role ID, UUID, or Name.
     * @return bool
     */
    public function hasAccountRole(int|string|Model $account, int|string $role) : bool
    {
        $accountId = $this->extractAccountId($account);
        $column = $this->isIdOrUuid($role) ? 'id' : 'name';

        return $this->accountRoles($accountId)->where($column, $role)->exists();
    }

    /**
     * Check if the user has ANY of the specified roles in a given account.
     *
     * @param int|string|Model $account The account ID or Model.
     * @param array<int, int|string> $roles An array of role IDs, UUIDs, or Names.
     * @return bool
     */
    public function hasAnyAccountRole(int|string|Model $account, array $roles) : bool
    {
        $accountId = $this->extractAccountId($account);

        foreach ($roles as $role) {
            if ($this->hasAccountRole($accountId, $role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user has ALL of the specified roles in a given account.
     *
     * @param int|string|Model $account The account ID or Model.
     * @param array<int, int|string> $roles An array of role IDs, UUIDs, or Names.
     * @return bool
     */
    public function hasAllAccountRoles(int|string|Model $account, array $roles) : bool
    {
        $accountId = $this->extractAccountId($account);

        foreach ($roles as $role) {
            if (!$this->hasAccountRole($accountId, $role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the user has a specific permission in a given account.
     *
     * @param int|string|Model $account The account ID or Model.
     * @param int|string $permission The permission ID, UUID, or Name.
     * @return bool
     */
    public function hasAccountPermission(int|string|Model $account, int|string $permission) : bool
    {
        $accountId = $this->extractAccountId($account);
        $column = $this->isIdOrUuid($permission) ? 'id' : 'name';

        return $this->accountRoles($accountId)->whereHas('permissions', function ($query) use ($column, $permission) {
            $query->where($column, $permission);
        })->exists();
    }

    /**
     * Check if the user has ANY of the specified permissions in a given account.
     *
     * @param int|string|Model $account The account ID or Model.
     * @param array<int, int|string> $permissions An array of permission IDs, UUIDs, or Names.
     * @return bool
     */
    public function hasAnyAccountPermission(int|string|Model $account, array $permissions) : bool
    {
        $accountId = $this->extractAccountId($account);

        foreach ($permissions as $permission) {
            if ($this->hasAccountPermission($accountId, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the user has ALL of the specified permissions in a given account.
     *
     * @param int|string|Model $account The account ID or Model.
     * @param array<int, int|string> $permissions An array of permission IDs, UUIDs, or Names.
     * @return bool
     */
    public function hasAllAccountPermissions(int|string|Model $account, array $permissions) : bool
    {
        $accountId = $this->extractAccountId($account);

        foreach ($permissions as $permission) {
            if (!$this->hasAccountPermission($accountId, $permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract the raw account ID from a Model or scalar value.
     *
     * @param int|string|Model|null $account The account ID or Model.
     * @return int|string|null The raw account ID.
     */
    private function extractAccountId(int|string|Model|null $account) : int|string|null
    {
        if ($account instanceof Model) {
            $accountPrimaryKey = config('stateless-tenancy.account_primary_key', 'id');
            return $account->{$accountPrimaryKey};
        }

        return $account;
    }

    /**
     * Extract an array of raw role IDs from mixed inputs.
     *
     * @param int|string|Model|array|Collection $roles The roles input.
     * @return array<int, int|string> An array of raw role IDs.
     */
    private function extractRoleIds(int|string|Model|array|Collection $roles) : array
    {
        if ($roles instanceof Collection) {
            return $roles->map(function ($role) {
                return $role instanceof Model ? $role->getKey() : $role;
            })->toArray();
        }

        if (is_array($roles)) {
            return array_map(function ($role) {
                return $role instanceof Model ? $role->getKey() : $role;
            }, $roles);
        }

        if ($roles instanceof Model) {
            return [$roles->getKey()];
        }

        return [$roles];
    }

    /**
     * Determine if a value is an integer ID or a UUID.
     *
     * @param int|string $value The value to check.
     * @return bool True if the value is numeric or a UUID, false if it's a name/slug.
     */
    private function isIdOrUuid(int|string $value) : bool
    {
        return is_numeric($value) || Str::isUuid((string) $value);
    }
}
