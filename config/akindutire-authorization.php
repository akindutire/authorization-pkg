<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authorization Configuration
    |--------------------------------------------------------------------------
    |
    | This package provides scalable, entity-based authorization for Laravel.
    | Performance-optimized for applications with millions of entities.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Abilities
    |--------------------------------------------------------------------------
    |
    | Define available permissions for your application.
    | These can be assigned to any entity (User, Article, TeamMember, etc.)
    |
    | Example:
    |   'can_update_profile',
    |   'can_delete_account',
    |   'can_receive_notifications',
    |   'can_broadcast',
    |   'can_autosend_for_analytics',
    |
    */
    'abilities' => [
        // Define your application-wide permissions here
        // Example: 'can_edit', 'can_delete', 'can_view', etc.
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Column Names
    |--------------------------------------------------------------------------
    |
    | Customize the column names used for storing permissions in your database.
    | These should match the columns added via migrations.
    |
    | Different entity types can use different column names:
    |   - Users: 'allowed_permissions' / 'revoked_permissions'
    |   - Articles: 'capabilities' / 'restrictions'
    |
    */
    'column_names' => [
        'allowed_permissions' => 'allowed_permissions',
        'revoked_permissions' => 'revoked_permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Control caching behavior for entity lookups and permission checks.
    |
    | entity_cache_ttl: How long to cache entity lookups (in seconds)
    |   - Higher = better performance, lower freshness
    |   - Lower = more DB queries, higher freshness
    |   - Recommended: 300 (5 minutes)
    |
    | cache_keys: Properties to index for cache invalidation
    |   - Add any properties used in #[HasAny/HasAll] lookups
    |   - Example: ['uuid', 'email', 'slug', 'username']
    |
    */
    'entity_cache_ttl' => env('PERMISSION_CACHE_TTL', 300),

    'cache_keys' => [
        'uuid',
        'email',
        'slug',
        // Add other properties used for entity lookups
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Index Configuration
    |--------------------------------------------------------------------------
    |
    | Tables and properties to index for performance optimization.
    |
    | indexed_tables: All tables using HasPermissions trait
    |   - These will receive performance indexes (see migration)
    |   - Critical for applications with >1M rows
    |
    | indexed_properties: Properties commonly used in WHERE clauses
    |   - Should match properties used in attributes:
    |     #[HasAny($actions, User::class, 'uuid')]
    |                                      ^^^^^
    |
    */
    'indexed_tables' => [
        'users',
        // Add other tables: 'articles', 'team_members', 'posts', etc.
    ],

    'indexed_properties' => [
        'uuid',
        'email',
        'slug',
        // Add other lookup properties
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception Handling
    |--------------------------------------------------------------------------
    |
    | Configure how authorization exceptions should be handled.
    |
    | message: Error message shown when authorization fails
    | code: HTTP status code (typically 403 Forbidden)
    |
    */
    'exception' => [
        'message' => env(
            'PERMISSION_EXCEPTION_MESSAGE',
            'Access denied, you do not have enough permission to perform this action, contact your administrator'
        ),
        'code' => 403,
    ],
];
