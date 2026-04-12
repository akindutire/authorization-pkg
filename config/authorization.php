<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authorization Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your authorization settings.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Actions per Role
    |--------------------------------------------------------------------------
    |
    | Define available permissions for application wide.
    | When a subject is assigned some permissions, such subject .
    |
    | Example:
    | ' // Define your permissions
    |  'can_update_profile', 'can_delete_account', 'can_receive_notifications',
    |
    */
    'abilities' => [
        // Define your permissions here

    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Column Names
    |--------------------------------------------------------------------------
    |
    | Customize the column names used for storing permissions in your database.
    | These should match the columns added via migrations.
    |
    */
    'column_names' => [
        'allowed_permissions' => 'allowed_permissions',
        'revoked_permissions' => 'revoked_permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception Handling
    |--------------------------------------------------------------------------
    |
    | Configure how authorization exceptions should be handled.
    |
    */
    'exception' => [
        'message' => 'Access denied, you do not have enough permission to perform this action, contact your administrator',
        'code' => 403,
    ],
];
