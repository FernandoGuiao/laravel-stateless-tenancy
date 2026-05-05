<?php

namespace FernandoGuiao\StatelessTenancy\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'account_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function permissions() : BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function accounts() : BelongsToMany
    {
        return $this->belongsToMany(
            related: config('stateless-tenancy.account_model'),
            table: 'account_role_user',
            foreignPivotKey: 'role_id',
            relatedPivotKey: config('stateless-tenancy.account_foreign_key', 'account_id'),
        );
    }

    public function users() : BelongsToMany
    {
        return $this->belongsToMany(
            related: config('stateless-tenancy.user_model'),
            table: 'account_role_user',
            foreignPivotKey: 'role_id',
            relatedPivotKey: 'user_id',
        );
    }
}
