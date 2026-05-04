<?php

namespace FernandoGuiao\StatelessTenancy\Traits;

use FernandoGuiao\StatelessTenancy\Models\Role;
use FernandoGuiao\StatelessTenancy\Services\AuthService;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

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
        $accountId = $accountId ?? AuthService::accountId();
        return $this->belongsToMany(
            related: Role::class,
            table: 'account_role_user',
            foreignPivotKey: 'user_id',
            relatedPivotKey: 'role_id',
        )->wherePivot(config('stateless-tenancy.account_foreign_key', 'account_id'), $accountId);
    }

    public function getAccountPermissions($accountId) : array
    {
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

    public function attachAccountWithRoles($account, Collection $roles) : void
    {
        $accountPrimaryKey = config('stateless-tenancy.account_primary_key', 'id');
        foreach ($roles as $role) {
            $this->accounts()->attach($account->{$accountPrimaryKey}, ['role_id' => $role->id]);
        }
    }
}
