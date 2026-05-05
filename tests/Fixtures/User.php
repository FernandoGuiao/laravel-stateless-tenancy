<?php

namespace FernandoGuiao\StatelessTenancy\Tests\Fixtures;

use FernandoGuiao\StatelessTenancy\Traits\HasAccounts;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasAccounts;

    protected $guarded = [];
}
