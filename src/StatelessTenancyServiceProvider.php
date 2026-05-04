<?php

namespace FernandoGuiao\StatelessTenancy;

use FernandoGuiao\StatelessTenancy\Services\AuthService;
use Illuminate\Support\ServiceProvider;

class StatelessTenancyServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/stateless-tenancy.php', 'stateless-tenancy'
        );

        $this->app->singleton(AuthService::class, function ($app) {
            return new AuthService(request()->bearerToken());
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/stateless-tenancy.php' => config_path('stateless-tenancy.php'),
            ], 'stateless-tenancy-config');

            $this->publishes([
                __DIR__.'/../database/migrations/create_roles_table.php.stub' => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_roles_table.php'),
                __DIR__.'/../database/migrations/create_permissions_table.php.stub' => database_path('migrations/' . date('Y_m_d_His', time() + 1) . '_create_permissions_table.php'),
                __DIR__.'/../database/migrations/create_permission_role_table.php.stub' => database_path('migrations/' . date('Y_m_d_His', time() + 2) . '_create_permission_role_table.php'),
                __DIR__.'/../database/migrations/create_account_role_user_table.php.stub' => database_path('migrations/' . date('Y_m_d_His', time() + 3) . '_create_account_role_user_table.php'),
            ], 'stateless-tenancy-migrations');
        }
    }
}
