<?php

namespace FernandoGuiao\StatelessTenancy\Tests\Feature;

use FernandoGuiao\StatelessTenancy\Exceptions\UnauthorizedException;
use FernandoGuiao\StatelessTenancy\Models\Permission;
use FernandoGuiao\StatelessTenancy\Models\Role;
use FernandoGuiao\StatelessTenancy\Services\AuthService;
use FernandoGuiao\StatelessTenancy\Tests\Fixtures\Account;
use FernandoGuiao\StatelessTenancy\Tests\Fixtures\User;
use FernandoGuiao\StatelessTenancy\Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class AuthServiceTest extends TestCase
{
    public function test_it_can_generate_tokens_for_a_user_and_account()
    {
        $user = User::create(['name' => 'John', 'email' => 'john@test.com', 'password' => Hash::make('password')]);
        $account = Account::create(['name' => 'Johns Company']);

        $role = Role::create(['name' => 'Admin']);
        $permission = Permission::create(['name' => 'create-posts']);
        $role->permissions()->attach($permission->id);

        $user->accounts()->attach($account->id, ['role_id' => $role->id]);

        $authService = new AuthService();
        $tokens = $authService->getTokens($user, $account->id);

        $this->assertArrayHasKey('token', $tokens);
        $this->assertArrayHasKey('refreshToken', $tokens);

        // Parse token and check claims
        $parsedAuthService = new AuthService($tokens['token']);
        $this->assertTrue($parsedAuthService->validate());

        $this->assertEquals($account->id, $parsedAuthService->getAccountId());
        $this->assertEquals($user->id, $parsedAuthService->getUserId());
        $this->assertTrue($parsedAuthService->hasPermission('create-posts'));
        $this->assertFalse($parsedAuthService->hasPermission('delete-posts'));
    }

    public function test_it_can_attempt_login()
    {
        $user = User::create(['name' => 'John', 'email' => 'john@test.com', 'password' => Hash::make('password')]);
        $account = Account::create(['name' => 'Johns Company']);
        $user->accounts()->attach($account->id, ['role_id' => Role::create(['name' => 'Admin'])->id]);

        $authService = new AuthService();
        $tokens = $authService->attempt(['email' => 'john@test.com', 'password' => 'password'], $account->id);

        $this->assertArrayHasKey('token', $tokens);

        $this->expectException(UnauthorizedException::class);
        $authService->attempt(['email' => 'john@test.com', 'password' => 'wrong_password'], $account->id);
    }

    public function test_it_can_issue_and_validate_action_tokens()
    {
        $user = User::create(['name' => 'Action User', 'email' => 'action@test.com', 'password' => Hash::make('password')]);

        $authService = new AuthService();
        $token = $authService->issueActionToken($user, 'password_reset', 15);

        $this->assertIsString($token);

        // Validating the token with the correct action
        $resolvedUser = $authService->validateActionToken($token, 'password_reset');
        $this->assertNotNull($resolvedUser);
        $this->assertEquals($user->id, $resolvedUser->id);

        // Validating the token with the wrong action
        $resolvedUserWrongAction = $authService->validateActionToken($token, 'email_verification');
        $this->assertNull($resolvedUserWrongAction);
    }

    public function test_it_returns_null_for_invalid_action_tokens()
    {
        $authService = new AuthService();
        $invalidToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.invalid.signature';

        $resolvedUser = $authService->validateActionToken($invalidToken, 'password_reset');
        $this->assertNull($resolvedUser);
    }
}
