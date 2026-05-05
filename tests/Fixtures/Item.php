<?php

namespace FernandoGuiao\StatelessTenancy\Tests\Fixtures;

use FernandoGuiao\StatelessTenancy\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use BelongsToAccount;

    protected $guarded = [];
}
