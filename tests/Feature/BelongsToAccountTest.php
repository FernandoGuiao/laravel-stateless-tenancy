<?php

namespace FernandoGuiao\StatelessTenancy\Tests\Feature;

use FernandoGuiao\StatelessTenancy\Services\AuthService;
use FernandoGuiao\StatelessTenancy\Tests\Fixtures\Account;
use FernandoGuiao\StatelessTenancy\Tests\Fixtures\Item;
use FernandoGuiao\StatelessTenancy\Tests\Fixtures\User;
use FernandoGuiao\StatelessTenancy\Tests\TestCase;

class BelongsToAccountTest extends TestCase
{
    public function test_global_scope_filters_by_active_account()
    {
        $account1 = Account::create(['name' => 'Account 1']);
        $account2 = Account::create(['name' => 'Account 2']);

        Item::create(['name' => 'Item 1', 'account_id' => $account1->id]);
        Item::create(['name' => 'Item 2', 'account_id' => $account2->id]);

        $user = User::create(['name' => 'John', 'email' => 'test@test.com', 'password' => 'password']);
        $user->accounts()->attach($account1->id, ['role_id' => 1]); // dummy role

        // Mock auth token
        $authService = new AuthService();
        // Since we need it properly signed to be validated inside the application container,
        // we first generate it.
        $token = $authService->issueToken($user, false, $account1->id);

        // Setup singleton
        $validAuthService = new AuthService($token);
        app()->instance(AuthService::class, $validAuthService);

        $items = Item::all();

        $this->assertCount(1, $items);
        $this->assertEquals('Item 1', $items->first()->name);

        // Check creating sets account automatically
        $newItem = Item::create(['name' => 'New Item']);
        $this->assertEquals($account1->id, $newItem->account_id);
    }
}
