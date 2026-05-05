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
    public function accounts() : BelongsToMany
    {
        return $this->belongsToMany(
            related: config('stateless-tenancy.account_model'),
            table: 'account_role_user',
            foreignPivotKey: 'user_id',
            relatedPivotKey: config('stateless-tenancy.account_foreign_key', 'account_id'),
        );
    }

    public function accountRoles($accountId = null) : BelongsToMany
    {
        $accountId = $this->extractAccountId($accountId ?? AuthService::accountId());
        return $this->belongsToMany(
            related: Role::class,
            table: 'account_role_user',
            foreignPivotKey: 'user_id',
            relatedPivotKey: 'role_id',
        )->wherePivot(config('stateless-tenancy.account_foreign_key', 'account_id'), $accountId);
    }

    public function getAccountPermissions($account) : array
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

    public function attachAccountWithRoles($account, $roles) : void
    {
        $accountId = $this->extractAccountId($account);
        $roleIds = $this->extractRoleIds($roles);

        foreach ($roleIds as $roleId) {
            $this->accounts()->attach($accountId, ['role_id' => $roleId]);
        }
    }

    public function removeAllRolesFromAccount($account) : void
    {
        $accountId = $this->extractAccountId($account);
        $this->accounts()->detach($accountId);
    }

    public function detachAccount($account) : void
    {
        $this->removeAllRolesFromAccount($account);
    }

    public function syncAccountWithRoles($account, $roles) : void
    {
        DB::transaction(function () use ($account, $roles) {
            $this->removeAllRolesFromAccount($account);
            $this->attachAccountWithRoles($account, $roles);
        });
    }

    public function hasAccountRole($account, int|string $role) : bool
    {
        $accountId = $this->extractAccountId($account);
        $column = $this->isIdOrUuid($role) ? 'id' : 'name';

        return $this->accountRoles($accountId)->where($column, $role)->exists();
    }

    public function hasAnyAccountRole($account, array $roles) : bool
    {
        $accountId = $this->extractAccountId($account);

        foreach ($roles as $role) {
            if ($this->hasAccountRole($accountId, $role)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllAccountRoles($account, array $roles) : bool
    {
        $accountId = $this->extractAccountId($account);

        foreach ($roles as $role) {
            if (!$this->hasAccountRole($accountId, $role)) {
                return false;
            }
        }

        return true;
    }

    public function hasAccountPermission($account, int|string $permission) : bool
    {
        $accountId = $this->extractAccountId($account);
        $column = $this->isIdOrUuid($permission) ? 'id' : 'name';

        return $this->accountRoles($accountId)->whereHas('permissions', function ($query) use ($column, $permission) {
            $query->where($column, $permission);
        })->exists();
    }

    public function hasAnyAccountPermission($account, array $permissions) : bool
    {
        $accountId = $this->extractAccountId($account);

        foreach ($permissions as $permission) {
            if ($this->hasAccountPermission($accountId, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllAccountPermissions($account, array $permissions) : bool
    {
        $accountId = $this->extractAccountId($account);

        foreach ($permissions as $permission) {
            if (!$this->hasAccountPermission($accountId, $permission)) {
                return false;
            }
        }

        return true;
    }

    private function extractAccountId($account)
    {
        if ($account instanceof Model) {
            $accountPrimaryKey = config('stateless-tenancy.account_primary_key', 'id');
            return $account->{$accountPrimaryKey};
        }

        return $account;
    }

    private function extractRoleIds($roles) : array
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

    private function isIdOrUuid($value) : bool
    {
        return is_numeric($value) || Str::isUuid((string) $value);
    }
}
