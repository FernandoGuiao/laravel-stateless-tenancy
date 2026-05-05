<?php

namespace FernandoGuiao\StatelessTenancy\Tests\Feature;

use FernandoGuiao\StatelessTenancy\Models\Permission;
use FernandoGuiao\StatelessTenancy\Models\Role;
use FernandoGuiao\StatelessTenancy\Tests\Fixtures\Account;
use FernandoGuiao\StatelessTenancy\Tests\Fixtures\User;
use FernandoGuiao\StatelessTenancy\Tests\TestCase;

class HasAccountsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_can_attach_and_detach_account_roles_flexibly()
    {
        $account = Account::create(['name' => 'Acme Corp']);
        $user = User::create(['name' => 'John Doe', 'email' => 'john@test.com', 'password' => 'secret']);
        $role1 = Role::create(['name' => 'admin']);
        $role2 = Role::create(['name' => 'editor']);

        // Attach with IDs
        $user->attachAccountWithRoles($account->id, [$role1->id, $role2->id]);
        $this->assertTrue($user->hasAccountRole($account->id, 'admin'));
        $this->assertTrue($user->hasAccountRole($account, 'editor')); // tests model extraction

        // Detach entirely
        $user->detachAccount($account);
        $this->assertFalse($user->hasAccountRole($account, 'admin'));
        $this->assertFalse($user->hasAccountRole($account, 'editor'));

        // Sync with Models
        $user->syncAccountWithRoles($account, [$role1, $role2]);
        $this->assertTrue($user->hasAccountRole($account, 'admin'));
        $this->assertTrue($user->hasAccountRole($account, 'editor'));
    }

    public function test_has_any_and_all_roles()
    {
        $account = Account::create(['name' => 'Acme Corp']);
        $user = User::create(['name' => 'John Doe', 'email' => 'john@test.com', 'password' => 'secret']);
        $role1 = Role::create(['name' => 'admin']);
        $role2 = Role::create(['name' => 'editor']);
        $role3 = Role::create(['name' => 'viewer']);

        $user->attachAccountWithRoles($account, [$role1, $role2]);

        // Has Any
        $this->assertTrue($user->hasAnyAccountRole($account, ['admin', 'viewer']));
        $this->assertTrue($user->hasAnyAccountRole($account, [$role1->id, 'viewer']));
        $this->assertFalse($user->hasAnyAccountRole($account, ['viewer', 'owner']));

        // Has All
        $this->assertTrue($user->hasAllAccountRoles($account, ['admin', 'editor']));
        $this->assertTrue($user->hasAllAccountRoles($account, [$role1->id, $role2->id]));
        $this->assertFalse($user->hasAllAccountRoles($account, ['admin', 'viewer']));
        $this->assertFalse($user->hasAllAccountRoles($account, ['admin', 'editor', 'viewer']));
    }

    public function test_has_any_and_all_permissions_and_get_permissions()
    {
        $account = Account::create(['name' => 'Acme Corp']);
        $user = User::create(['name' => 'John Doe', 'email' => 'john@test.com', 'password' => 'secret']);

        $role = Role::create(['name' => 'editor']);
        $permission1 = Permission::create(['name' => 'edit-posts']);
        $permission2 = Permission::create(['name' => 'delete-posts']);
        $permission3 = Permission::create(['name' => 'view-posts']);

        $role->permissions()->attach([$permission1->id, $permission2->id]);
        $user->attachAccountWithRoles($account, $role);

        // Get Permissions helper test
        $permissionsArray = $user->getAccountPermissions($account);
        $this->assertArrayHasKey('edit-posts', $permissionsArray);
        $this->assertArrayHasKey('delete-posts', $permissionsArray);
        $this->assertArrayNotHasKey('view-posts', $permissionsArray);
        $this->assertTrue($permissionsArray['edit-posts']);

        // Has Any
        $this->assertTrue($user->hasAnyAccountPermission($account, ['edit-posts', 'view-posts']));
        $this->assertTrue($user->hasAnyAccountPermission($account, [$permission1->id, 'view-posts']));
        $this->assertFalse($user->hasAnyAccountPermission($account, ['view-posts', 'manage-billing']));

        // Has All
        $this->assertTrue($user->hasAllAccountPermissions($account, ['edit-posts', 'delete-posts']));
        $this->assertTrue($user->hasAllAccountPermissions($account, [$permission1->id, $permission2->id]));
        $this->assertFalse($user->hasAllAccountPermissions($account, ['edit-posts', 'view-posts']));
        $this->assertFalse($user->hasAllAccountPermissions($account, ['edit-posts', 'delete-posts', 'view-posts']));
    }
}
