<?php

namespace FernandoGuiao\StatelessTenancy\Traits;

use FernandoGuiao\StatelessTenancy\Services\AuthService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToAccount
{
    public static function bootBelongsToAccount()
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

    public function scopeAllAccounts(Builder $query)
    {
        return $query->withoutGlobalScope('account');
    }

    public function account()
    {
        return $this->belongsTo(
            config('stateless-tenancy.account_model'),
            config('stateless-tenancy.account_foreign_key', 'account_id'),
            config('stateless-tenancy.account_primary_key', 'id')
        );
    }
}
