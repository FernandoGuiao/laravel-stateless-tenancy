<?php

namespace FernandoGuiao\StatelessTenancy\Tests;

use FernandoGuiao\StatelessTenancy\StatelessTenancyServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            StatelessTenancyServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:JqX5K05E9x8dJ+bK/4p8D2u5k5Y4b8k8p4k8D4u5k5A=');

        // Use default classes for testing
        $app['config']->set('stateless-tenancy.user_model', \FernandoGuiao\StatelessTenancy\Tests\Fixtures\User::class);
        $app['config']->set('stateless-tenancy.account_model', \FernandoGuiao\StatelessTenancy\Tests\Fixtures\Account::class);
        $app['config']->set('stateless-tenancy.jwt_secret', 'testing-secret-key-that-is-at-least-32-chars-long');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function setUpDatabase()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedBigInteger('default_account_id')->nullable();
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('name');
            $table->timestamps();
        });

        // Copy stubs to actual migrations for testing
        $stubs = [
            'create_roles_table.php.stub',
            'create_permissions_table.php.stub',
            'create_permission_role_table.php.stub',
            'create_account_role_user_table.php.stub',
        ];

        foreach ($stubs as $stub) {
            $content = file_get_contents(__DIR__ . '/../database/migrations/' . $stub);
            // Evaluate the anonymous class
            $migration = eval('?>' . $content);
            $migration->up();
        }
    }
}
