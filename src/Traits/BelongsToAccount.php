<?php

namespace FernandoGuiao\StatelessTenancy\Traits;

use FernandoGuiao\StatelessTenancy\Services\AuthService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToAccount
{
    /**
     * Boot the BelongsToAccount trait for a model.
     * Sets up global scopes and creating events for tenancy.
     *
     * @return void
     */
    public static function bootBelongsToAccount() : void
    {
        static::addGlobalScope('account', function (Builder $builder) {
            if (app()->bound(AuthService::class)) {
                $accountId = AuthService::accountId();
                if ($accountId) {
                    $accountForeignKey = config('stateless-tenancy.account_foreign_key', 'account_id');
                    $builder->where((new static)->getTable() . '.' . $accountForeignKey, $accountId);
                }
            }
        });

        static::creating(function (Model $model) {
            $accountForeignKey = config('stateless-tenancy.account_foreign_key', 'account_id');
            if (!$model->getAttribute($accountForeignKey) && app()->bound(AuthService::class)) {
                $accountId = AuthService::accountId();
                if ($accountId) {
                    $model->setAttribute($accountForeignKey, $accountId);
                }
            }
        });
    }

    /**
     * Scope a query to include all accounts, bypassing the global tenancy scope.
     * Useful for super-admins or background jobs.
     *
     * @param Builder $query The Eloquent query builder.
     * @return Builder
     */
    public function scopeAllAccounts(Builder $query) : Builder
    {
        return $query->withoutGlobalScope('account');
    }

    /**
     * Get the account that owns the model.
     *
     * @return BelongsTo
     */
    public function account() : BelongsTo
    {
        return $this->belongsTo(
            config('stateless-tenancy.account_model'),
            config('stateless-tenancy.account_foreign_key', 'account_id'),
            config('stateless-tenancy.account_primary_key', 'id')
        );
    }
}
