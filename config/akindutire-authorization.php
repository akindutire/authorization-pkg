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
    | Abilities Configuration
    |--------------------------------------------------------------------------
    |
    | Define available permissions for your application.
    | These can be assigned to any entity (User, Article, TeamMember, etc.)
    |
    | Option 1: Simple permission list (recommended for most applications)
    |   'abilities' => [
    |       'can_edit',
    |       'can_delete',
    |       'can_broadcast',
    |       'can_autosend_for_analytics',
    |   ],
    |
    | Option 2: Role-based permission groups (for role-based authorization)
    |   'abilities' => [
    |       'owner' => [
    |           'can_update_company',
    |           'can_invite_member',
    |           'can_remove_member',
    |       ],
    |       'admin' => [
    |           'can_invite_member',
    |           'can_view_pitch',
    |       ],
    |       'member' => [
    |           'can_view_pitch',
    |       ],
    |   ],
    |
    | With role-based setup, use: EntityPermission::getDefaultActions('owner')
    |
    */
    'abilities' => [
        // Define your application-wide permissions or role-based permission groups here
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
    | auto_invalidate_reflection_cache: Automatically detect attribute changes
    |   - When true: Cache keys include a hash of attribute parameters
    |   - When false: Cache persists until manual clearing (old behavior)
    |   - Recommended: true (eliminates need for permission:cache-clear after attribute changes)
    |   - Performance: Adds ~5-10μs overhead on first request to generate hash
    |
    */
    'entity_cache_ttl' => env('PERMISSION_CACHE_TTL', 300),

    'auto_invalidate_reflection_cache' => env('PERMISSION_AUTO_INVALIDATE', true),

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
    | Permission Size Limits
    |--------------------------------------------------------------------------
    |
    | Enforce limits on permission list sizes to prevent memory issues.
    |
    | max_permission_size_bytes: Maximum size of permission JSON in bytes
    |   - Prevents oversized permission arrays (>10KB can cause memory issues)
    |   - Default: 10240 bytes (10KB) ≈ 250-400 permissions
    |   - Set to null to disable size validation
    |
    | max_permission_count: Maximum number of individual permissions
    |   - Recommended: 100-500 permissions per entity
    |   - Set to null to disable count validation
    |
    | IMPORTANT: If you need more than 500 permissions per entity, consider:
    |   1. Permission categories/namespacing (e.g., 'article.edit', 'article.delete')
    |   2. Bitwise permission encoding for ultra-compact storage
    |   3. External permission storage (dedicated permissions table)
    |
    */
    'max_permission_size_bytes' => env('PERMISSION_MAX_SIZE_BYTES', 10240), // 10KB
    'max_permission_count' => env('PERMISSION_MAX_COUNT', 500),

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
