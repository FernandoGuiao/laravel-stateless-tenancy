<?php

return [

    /*
    |--------------------------------------------------------------------------
    | System Models
    |--------------------------------------------------------------------------
    |
    | Define the User and Account models used by your host application.
    | The package will use these models to resolve relations and foreign keys
    | in the migrations and traits dynamically.
    |
    */

    'user_model' => \App\Models\User::class,

    'account_model' => \App\Models\Account::class,

    /*
    |--------------------------------------------------------------------------
    | Database Keys Configuration
    |--------------------------------------------------------------------------
    |
    | If your accounts table primary key or the foreign key for accounts is
    | different from the standard 'id' and 'account_id', define them here.
    |
    */

    'account_primary_key' => 'id',

    'account_foreign_key' => 'account_id',

    /*
    |--------------------------------------------------------------------------
    | JWT Configuration
    |--------------------------------------------------------------------------
    |
    | The JWT secret should be securely stored in your .env file.
    | The expiration times define how long the tokens remain valid.
    |
    */

    'jwt_secret' => env('JWT_SECRET', env('APP_KEY')),

    'token_expiration' => env('JWT_TOKEN_EXPIRATION', 60), // in minutes

    'refresh_token_expiration' => env('JWT_REFRESH_TOKEN_EXPIRATION', 20160), // 14 days in minutes

];
