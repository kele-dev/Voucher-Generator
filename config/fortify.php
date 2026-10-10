<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication guard
    |--------------------------------------------------------------------------
    */

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    /*
    |--------------------------------------------------------------------------
    | Redirect after login
    |--------------------------------------------------------------------------
    */

    'home' => '/dashboard',

    'prefix' => '',

    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    |
    | The "login" limiter is the framework default of five attempts per minute.
    |
    */

    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    |
    | Disabled: the app renders its own login view via Fortify::loginView.
    |
    */

    'views' => true,

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Registration and password reset are intentionally absent: accounts are
    | created only by a Super Admin from the user management screen. Only the
    | core login/logout routes are registered.
    |
    */

    'features' => [
    ],

];
