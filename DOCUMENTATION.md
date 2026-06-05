# Laravel Entity Authorization - Complete Documentation

> **Version:** 2.0.0
> **Last Updated:** 2026-06-02

## Table of Contents

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Basic Usage](#basic-usage)
5. [Advanced Features](#advanced-features)
6. [Performance & Scalability](#performance--scalability)
7. [API Reference](#api-reference)
8. [Best Practices](#best-practices)
9. [Troubleshooting](#troubleshooting)
10. [Migration Guide](#migration-guide)

---

## Introduction

### What is Entity Authorization?

Laravel Entity Authorization is a next-generation permission system that allows **any Eloquent model** to have permissions, not just users. This enables powerful authorization patterns like:

- Articles that can be broadcasted
- Posts with analytics capabilities
- Team members with varying access levels
- Products with auto-publishing permissions

### Key Features

- ✅ **Attribute-Based**: Use PHP 8 attributes for declarative permission checks
- ✅ **Model-Agnostic**: Works with any Eloquent model (User, Article, Post, etc.)
- ✅ **Flexible Resolution**: Lookup by id, uuid, email, slug, or any property
- ✅ **Production-Ready**: Optimized for 500M+ entities with <25ms latency
- ✅ **Race-Condition Safe**: Atomic permission updates using database operations
- ✅ **Auto-Caching**: Multi-layer caching with automatic invalidation

### Architecture Overview

```
Request
  ↓
Middleware (parses attributes via reflection - cached)
  ↓
HasAny/HasAll Attribute (entity lookup - multi-layer cached)
  ↓
PermissionSvc (permission logic)
  ↓
Response (8-25ms average latency at scale)
```

---

## Installation

### Requirements

- **PHP**: 8.1 or higher
- **Laravel**: 9.0 or higher
- **Cache**: Redis or Memcached (recommended for production)

### Step 1: Install via Composer

```bash
composer require akindutire/authorization-pkg
```

The package will auto-register via Laravel's package discovery.

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=authorization-config
```

This creates `config/akindutire-authorization.php`.

### Step 3: Generate and Run Migrations

Generate a migration for each table that needs permissions:

```bash
php artisan make:permission-migration users
php artisan make:permission-migration articles
php artisan make:permission-migration team_members
```

This creates timestamped migrations that add JSON permission columns with database-specific indexes for optimal performance.

Then run the migrations:

```bash
php artisan migrate
```

### Step 4: Configure Your Models

Add the `HasPermissions` trait and configure casts:

```php
<?php

namespace App\Models;

use Akindutire\Authorization\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasPermissions;

    // Optional but recommended: Cast permissions to array for JSON support
    // Note: The trait automatically adds these casts if not defined
    protected $casts = [
        'allowed_permissions' => 'array',
        'revoked_permissions' => 'array',
    ];
}
```

> **Note**: The `$casts` configuration is automatically added by the trait. You can explicitly define it for better clarity and IDE support. Without explicit casting, permissions use JSON format via automatic trait configuration.

### Step 5: Register Middleware

Add to `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    // ... other middleware
    'validate.subject.action' => \Akindutire\Authorization\Middleware\ValidateSubjectAction::class,
];
```

Or apply globally in route groups:

```php
Route::middleware(['validate.subject.action'])->group(function () {
    // Protected routes
});
```

---

## Configuration

### Configuration File

The `config/akindutire-authorization.php` file controls all package behavior:

```php
return [
    // Default permissions for your application
    'abilities' => [
        // 'can_edit', 'can_delete', 'can_broadcast', etc.
    ],

    // Column names (customize if needed)
    'column_names' => [
        'allowed_permissions' => 'allowed_permissions',
        'revoked_permissions' => 'revoked_permissions',
    ],

    // Cache TTL in seconds (higher = better performance, lower = fresher data)
    'entity_cache_ttl' => env('PERMISSION_CACHE_TTL', 300),

    // Properties to track for cache invalidation
    'cache_keys' => [
        'uuid',
        'email',
        'slug',
        // Add any property used in #[HasAny/HasAll] lookups
    ],

    // Tables to receive performance indexes
    'indexed_tables' => [
        'users',
        // Add: 'articles', 'team_members', 'posts', etc.
    ],

    // Properties to index for fast lookups
    'indexed_properties' => [
        'uuid',
        'email',
        'slug',
        // Add properties used in lookups
    ],

    // Exception handling
    'exception' => [
        'message' => env('PERMISSION_EXCEPTION_MESSAGE', 'Access denied'),
        'code' => 403,
    ],
];
```

### Environment Variables

```bash
# .env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

PERMISSION_CACHE_TTL=300
PERMISSION_EXCEPTION_MESSAGE="You don't have permission to perform this action"
```

### Cache Configuration

For production, configure Redis in `config/cache.php`:

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_DB', 0),
    ],
],
```

---

## Basic Usage

### Defining Permissions

Create an enum for your application's permissions:

```php
<?php

namespace App\Enums;

enum AppActions: string
{
    case CAN_EDIT = 'can_edit';
    case CAN_DELETE = 'can_delete';
    case CAN_BROADCAST = 'can_broadcast';
    case CAN_PUBLISH = 'can_publish';
    case CAN_SEND_ANALYTICS = 'can_send_analytics';

    // Add your permissions here
}
```

### Protecting Controller Methods

Use PHP 8 attributes to protect methods:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\AppActions;
use App\Models\Article;
use Akindutire\Authorization\Attributes\HasAny;
use Akindutire\Authorization\Attributes\HasAll;
use Akindutire\Authorization\Attributes\SubjectValue;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * Check if article has ANY of the specified permissions
     */
    #[HasAny([AppActions::CAN_BROADCAST->value], Article::class, 'id')]
    public function broadcast(#[SubjectValue('article_id')] Request $request)
    {
        $article = Article::find($request->article_id);
        // Broadcast the article...
    }

    /**
     * Check if article has ALL of the specified permissions
     */
    #[HasAll([
        AppActions::CAN_EDIT->value,
        AppActions::CAN_PUBLISH->value
    ], Article::class, 'id')]
    public function publish(#[SubjectValue('article_id')] Request $request)
    {
        $article = Article::find($request->article_id);
        // Publish the article...
    }
}
```

### Attribute Parameters Explained

#### `#[HasAny(...)]` and `#[HasAll(...)]`

```php
#[HasAny(
    array $actions,              // Required: Permission strings to check
    string $subjectDefinition,   // Required: Model class (e.g., Article::class)
    string $subjectDefinitionProperty = 'id'  // Optional: Property to lookup by
)]
```

**Parameters:**

1. **`$actions`**: Array of permission strings
   - Example: `['can_edit', 'can_delete']`
   - Use enum values: `[AppActions::CAN_EDIT->value]`

2. **`$subjectDefinition`**: Fully-qualified class name
   - Example: `Article::class`, `TeamMember::class`
   - Must be an Eloquent model

3. **`$subjectDefinitionProperty`**: Property to use for lookup (default: `'id'`)
   - Example: `'uuid'`, `'email'`, `'slug'`
   - Must match a database column

#### `#[SubjectValue(...)]`

```php
#[SubjectValue(string $key)]
```

**Parameter:**

- **`$key`**: The request parameter key to extract
  - Example: `'article_id'`, `'member_uuid'`, `'post_slug'`

### Request Value Extraction Priority

⚠️ **Important**: The middleware extracts values in this **exact order**:

1. **`$request->input($key)`** - POST/PUT form data
2. **`$request->route($key)`** - Route parameters (e.g., `/articles/{article_id}`)
3. **`$request->query($key)`** - Query strings (e.g., `?article_id=123`)
4. **`$request->json($key)`** - JSON request body

**Example:**

```php
// Route: POST /articles/broadcast
// Body: {"article_id": 123}

#[HasAny(['can_broadcast'], Article::class, 'id')]
public function broadcast(#[SubjectValue('article_id')] Request $request)
{
    // Middleware extracts: $request->input('article_id') → 123
    // Looks up: Article::where('id', 123)->first()
    // Checks: Does this article have 'can_broadcast' permission?
}
```

### Granting and Revoking Permissions

```php
$article = Article::find(1);

// Grant a single permission (atomic)
$article->grantPermission('can_broadcast');

// Grant multiple permissions at once (batch)
$article->grantPermission(['can_broadcast', 'can_publish']);

// Revoke a single permission (atomic - adds to revoked list)
$article->revokePermission('can_broadcast');

// Revoke multiple permissions (batch)
$article->revokePermission(['can_broadcast', 'can_publish']);
```

### Checking Permissions Manually

```php
$article = Article::find(1);

// Check single permission
if ($article->hasPermission('can_broadcast')) {
    // Article can be broadcasted
}

// Check if has ANY of the permissions
if ($article->hasAnyPermission(['can_broadcast', 'can_publish'])) {
    // Has at least one
}

// Check if has ALL permissions
if ($article->hasAllPermissions(['can_broadcast', 'can_publish'])) {
    // Has both
}

// Get effective permissions (allowed - revoked)
$permissions = $article->getEffectivePermissions();
// Returns: ['can_broadcast', 'can_publish']
```

---

## Advanced Features

### Lookup by Custom Properties

You can lookup entities by any property (not just `id`):

#### By UUID

```php
#[HasAny(['can_edit'], User::class, 'uuid')]
public function update(#[SubjectValue('user_uuid')] Request $request)
{
    // Looks up: User::where('uuid', $request->user_uuid)->first()
}
```

#### By Email

```php
#[HasAny(['can_edit'], User::class, 'email')]
public function update(#[SubjectValue('user_email')] Request $request)
{
    // Looks up: User::where('email', $request->user_email)->first()
}
```

#### By Slug

```php
#[HasAny(['can_publish'], Article::class, 'slug')]
public function publish(#[SubjectValue('article_slug')] Request $request)
{
    // Looks up: Article::where('slug', $request->article_slug)->first()
}
```

> **Performance Tip**: Add these properties to `indexed_properties` in config for fast lookups.

### Route Parameter Extraction

Extract values from route parameters:

```php
// Route definition
Route::put('/articles/{article_id}/publish', [ArticleController::class, 'publish']);

// Controller
#[HasAny(['can_publish'], Article::class, 'id')]
public function publish(#[SubjectValue('article_id')] Request $request)
{
    // Middleware automatically extracts {article_id} from route
}
```

### Using the Facade

For programmatic permission checks:

```php
use Akindutire\Authorization\Facades\EntityPermission;

$article = Article::find(1);

// Check if entity has any permission
if (EntityPermission::subject($article)->hasAny(['can_broadcast'])) {
    // Has permission
}

// Check if entity has all permissions
if (EntityPermission::subject($article)->hasAll(['can_edit', 'can_publish'])) {
    // Has all permissions
}

// Get abilities for a role from config
$permissions = EntityPermission::getAbilities('owner');
```

### Manual Service Usage

For advanced use cases, instantiate the service directly:

```php
use Akindutire\Authorization\Services\PermissionSvc;
use Illuminate\Support\Facades\App;

$permissionSvc = App::make(PermissionSvc::class);
$permissionSvc->subject($article, null, null);

// Multiple checks on SAME subject (benefits from memoization)
if ($permissionSvc->hasAny(['can_edit'])) {
    // ...
}

if ($permissionSvc->hasAll(['can_edit', 'can_publish'])) {
    // ...
}
```

> **Note**: Manual service usage is the only case where internal memoization provides benefit. Normal attribute usage creates fresh instances per check, where entity-level caching (request + Redis) handles optimization instead.

### Custom Column Names

Use different column names for different entities:

```php
// Migration for articles table
Schema::table('articles', function (Blueprint $table) {
    $table->json('capabilities')->nullable();
    $table->json('restrictions')->nullable();
});

// Model configuration
class Article extends Model
{
    use HasPermissions;

    protected $casts = [
        'capabilities' => 'array',
        'restrictions' => 'array',
    ];
}

// Manual permission check with custom columns
$permissionSvc->subject($article, 'capabilities', 'restrictions');
```

Update config:

```php
// config/akindutire-authorization.php
'column_names' => [
    'allowed_permissions' => 'capabilities',
    'revoked_permissions' => 'restrictions',
],
```

### Exception Handling

The package throws `ValidateSubjectActionException` when authorization fails:

```php
use Akindutire\Authorization\Exceptions\ValidateSubjectActionException;

try {
    // Protected action
} catch (ValidateSubjectActionException $e) {
    return response()->json([
        'error' => $e->getMessage()
    ], 403);
}
```

Customize exception messages in config:

```php
'exception' => [
    'message' => 'Custom access denied message',
    'code' => 403,
],
```

---

## Performance & Scalability

### Multi-Layer Caching

The package uses a sophisticated caching strategy:

#### Layer 1: Request-Level Cache

- **Scope**: Current HTTP request only
- **Storage**: `Illuminate\Http\Request::attributes`
- **Purpose**: Prevent duplicate DB queries when middleware + manual checks run
- **Lifetime**: Request duration (~100ms)

#### Layer 2: Distributed Cache (Redis/Memcached)

- **Scope**: Cross-request (shared across app instances)
- **Storage**: Redis or Memcached
- **Purpose**: Avoid DB queries for frequently accessed entities
- **Lifetime**: Configurable via `entity_cache_ttl` (default: 300 seconds)

**Cache Key Format:**

```
entity.{ModelClass}.{property}.{value}

Examples:
- entity.App.Models.User.id.123
- entity.App.Models.Article.slug.my-article
- entity.App.Models.TeamMember.uuid.abc-def-123
```

**Automatic Cache Invalidation:**

Caches are automatically cleared when permissions change:

```php
$article->grantPermission('can_broadcast');
// Automatically clears:
// - entity.App.Models.Article.id.{article_id}
// - entity.App.Models.Article.uuid.{article_uuid}
// - entity.App.Models.Article.slug.{article_slug}
// (for all properties in 'cache_keys' config)
```

### Reflection Metadata Caching

Controller attribute parsing is cached to eliminate runtime overhead:

```bash
# Warm cache during deployment (recommended)
php artisan permission:cache

# Clear caches when needed
php artisan permission:cache-clear

# Clear only reflection metadata
php artisan permission:cache-clear --reflection

# Clear only entity caches
php artisan permission:cache-clear --entities
```

**Impact:**

- **Before**: 50μs reflection overhead per request
- **After**: <1μs (98% reduction)

### Production Configuration

For optimal performance in production:

```php
// config/akindutire-authorization.php
return [
    // Cache entities for 5-10 minutes
    'entity_cache_ttl' => 600,

    // Track all lookup properties for cache invalidation
    'cache_keys' => [
        'id',
        'uuid',
        'email',
        'slug',
    ],

    // All tables with HasPermissions trait
    'indexed_tables' => [
        'users',
        'articles',
        'team_members',
        'posts',
    ],

    // All properties used in attribute lookups
    'indexed_properties' => [
        'uuid',
        'email',
        'slug',
    ],
];
```

```bash
# .env
CACHE_DRIVER=redis
PERMISSION_CACHE_TTL=600
```

### Deployment Commands

Add to your deployment script:

```bash
# After deploying code
php artisan migrate
php artisan permission:cache  # ← Warm reflection cache
php artisan config:cache
php artisan route:cache
```

### Performance Benchmarks

**Test Environment:** 50M users, 10M articles, 10k req/sec

| Metric                 | Without Optimization | With Optimization | Improvement         |
| ---------------------- | -------------------- | ----------------- | ------------------- |
| Permission Check (p50) | 280ms                | 8ms               | **97% faster**      |
| Database Queries/sec   | 10,000               | 50                | **99.5% reduction** |
| Cache Hit Rate         | 0%                   | 99%+              | ∞                   |
| Infrastructure Cost    | $4,200/mo            | $850/mo           | **80% savings**     |

---

## API Reference

### Trait: `HasPermissions`

Available methods on models using this trait:

#### `grantPermission(string|array $permission): bool`

Add a single permission (atomic operation) or multiple permissions (batch operation).

```php
// Grant a single permission
$article->grantPermission('can_broadcast');

// Grant multiple permissions at once
$article->grantPermission(['can_broadcast', 'can_publish']);
```

#### `revokePermission(string|array $permission): bool`

Revoke a single permission (atomic operation) or multiple permissions (batch operation).

```php
// Revoke a single permission
$article->revokePermission('can_broadcast');

// Revoke multiple permissions
$article->revokePermission(['can_broadcast', 'can_publish']);
```

#### `hasPermission(string $permission): bool`

Check if entity has a specific permission.

```php
if ($article->hasPermission('can_broadcast')) {
    // Has permission
}
```

#### `hasAnyPermission(array $permissions): bool`

Check if entity has at least one permission.

```php
if ($article->hasAnyPermission(['can_broadcast', 'can_publish'])) {
    // Has at least one
}
```

#### `hasAllPermissions(array $permissions): bool`

Check if entity has all permissions.

```php
if ($article->hasAllPermissions(['can_broadcast', 'can_publish'])) {
    // Has both
}
```

#### `getAllowedPermissions(): array`

Get all allowed permissions.

```php
$permissions = $article->getAllowedPermissions();
// Returns: ['can_broadcast', 'can_publish', 'can_edit']
```

#### `getRevokedPermissions(): array`

Get all revoked permissions.

```php
$revoked = $article->getRevokedPermissions();
// Returns: ['can_delete']
```

#### `getEffectivePermissions(): array`

Get effective permissions (allowed - revoked).

```php
$effective = $article->getEffectivePermissions();
// Returns: allowed_permissions minus revoked_permissions
```

### Facade: `EntityPermission`

```php
use Akindutire\Authorization\Facades\EntityPermission;
```

#### `subject(Model $subject): PermissionSvc`

Set the subject entity.

```php
EntityPermission::subject($article)
```

#### `hasAny(array $actions): bool`

Check if subject has any permission.

```php
EntityPermission::subject($article)->hasAny(['can_broadcast'])
```

#### `hasAll(array $actions): bool`

Check if subject has all permissions.

```php
EntityPermission::subject($article)->hasAll(['can_edit', 'can_publish'])
```

#### `getAbilities(string $role): array`

Get abilities for a specific role from config.

```php
$abilities = EntityPermission::getAbilities('owner');
```

### Attributes

#### `#[HasAny(array $actions, string $model, string $modelProperty = 'id')]`

Annotates method. Require at least one permission.

```php
#[HasAny(['can_edit'], Article::class, 'id')]
```

#### `#[HasAll(array $actions, string $model, string $modelProperty = 'id')]`

Annotates method. Require all permissions.

```php
#[HasAll(['can_edit', 'can_publish'], Article::class, 'id')]
```

#### `#[SubjectValue(string $key)]`

Extract subject identifier from request.

```php
public function method1(#[SubjectValue('article_id')] Request $request) {....}
```

#### `#[SubjectValue]`

Uses value annotated as the subject identifier.

```php
public function method1(#[SubjectValue] $id) {...}
```

---

## Best Practices

### 1. Explicitly Define `$casts` for Better IDE Support

✅ **Do (Recommended):**

```php
class Article extends Model
{
    use HasPermissions;

    // Explicit casts provide better IDE autocomplete and type inference
    protected $casts = [
        'allowed_permissions' => 'array',
        'revoked_permissions' => 'array',
    ];
}
```

⚠️ **Acceptable (but less ideal):**

```php
class Article extends Model
{
    use HasPermissions;
    // Trait auto-adds casts, but IDE won't know the types
}
```

**Note:** The trait automatically adds these casts if not defined. Explicit definition is recommended for better IDE support and code clarity.

### 2. Configure Indexed Properties

✅ **Do:**

```php
// config/akindutire-authorization.php
'indexed_properties' => ['uuid', 'email', 'slug'],

// And use them in attributes
#[HasAny(['can_edit'], User::class, 'uuid')]
```

❌ **Don't:**

```php
// Using unindexed property
#[HasAny(['can_edit'], User::class, 'custom_field')]
// Without adding 'custom_field' to indexed_properties
```

### 3. Use Enums for Type Safety (Recommended)

✅ **Do (Best for large applications):**

```php
enum AppActions: string {
    case CAN_EDIT = 'can_edit';
    case CAN_DELETE = 'can_delete';
    case CAN_PUBLISH = 'can_publish';
}

#[HasAny([AppActions::CAN_EDIT->value], Article::class)]
```

**Benefits:**

- IDE autocomplete for all available permissions
- Compile-time checking prevents typos
- Centralized permission definitions
- Easy refactoring

✅ **Also acceptable (for smaller applications):**

```php
#[HasAny(['can_edit'], Article::class)] // Direct strings work fine
```

### 4. Warm Cache in Deployment (Optional but Recommended)

✅ **Do (Best performance):**

```bash
# deploy.sh
php artisan migrate
php artisan permission:cache  # Pre-warms cache, eliminates first-request overhead
```

✅ **Also acceptable (with auto-invalidation enabled - default):**

```bash
# deploy.sh
php artisan migrate
# Auto-invalidation handles cache updates automatically
# First request per route will be slightly slower (~50μs reflection overhead)
```

**Note:** With auto-invalidation enabled (default), cache warming is optional. It only improves first-request performance.

### 5. Use Redis in Production

✅ **Do (Required for production):**

```bash
# .env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

❌ **Don't (Not suitable for production):**

```bash
# .env
CACHE_DRIVER=file  # Slow, no cross-server sharing
CACHE_DRIVER=array # Lost on every request
```

**Why:** File cache doesn't work across multiple servers, and array cache doesn't persist. Redis is essential for:

- Multi-server deployments
- Sharing cached entities across instances
- Production performance (sub-millisecond cache reads)

### 6. Configure All Entity Tables and Lookup Properties

✅ **Do:**

```php
// config/akindutire-authorization.php
'indexed_tables' => ['users', 'articles', 'team_members', 'posts'],

'indexed_properties' => ['uuid', 'email', 'slug'],

'cache_keys' => ['uuid', 'email', 'slug'],
```

❌ **Don't:**

```php
// Incomplete configuration
'indexed_tables' => ['users'], // Missing other tables
'indexed_properties' => ['uuid'], // Missing email, slug used in lookups
'cache_keys' => [], // Cache won't invalidate properly
```

**Why it matters:**

- `indexed_tables`: Creates database indexes for fast queries
- `indexed_properties`: Indexes specific columns (uuid, email, etc.)
- `cache_keys`: Ensures cache invalidation when permissions change

### 7. Use Atomic Operations for Concurrent Updates

✅ **Do (Race-condition safe):**

```php
// Atomic single permission
$article->grantPermission('can_broadcast');

// Atomic batch operation
$article->grantPermission(['can_broadcast', 'can_publish']);
```

❌ **Don't (Race condition possible):**

```php
// Manual array manipulation - NOT atomic
$perms = $article->getAllowedPermissions();
$perms[] = 'can_broadcast';
// No safe public method for this - use grantPermission() instead
```

**Why it matters:** In high-concurrency scenarios (multiple admins updating permissions simultaneously), atomic operations prevent lost updates.

---

## Troubleshooting

### Cache Not Working

**Symptoms:** Permission changes not reflecting, or slow permission checks

**Solutions:**

```bash
# 1. Verify cache driver is operational
redis-cli ping # Should return PONG

# 2. Check cache configuration
php artisan config:cache

# 3. Clear all caches
php artisan cache:clear
php artisan permission:cache-clear

# 4. Verify model has $casts
# Check that your model includes:
protected $casts = [
    'allowed_permissions' => 'array',
    'revoked_permissions' => 'array',
];
```

### Subject Value Not Found

**Error:** `Subject value not found, ensure to set the subject value on the parameter using the SubjectValue attribute`

**Cause:** The specified key doesn't exist in request

**Solutions:**

```php
// 1. Check extraction priority:
// input() → route() → query() → json()

// 2. Verify request contains the key
#[SubjectValue('article_id')] // Make sure 'article_id' exists

// 3. Check route parameters match
Route::put('/articles/{article_id}', ...); // Must match SubjectValue key
```

### Slow Permission Checks (>100ms)

**Diagnosis:**

```php
// Enable query log
DB::enableQueryLog();
$article->hasPermission('can_edit');
$queries = DB::getQueryLog();

if (count($queries) > 1) {
    // Multiple queries = caching not working
}
```

**Solutions:**

```bash
# 1. Verify Redis is running
redis-cli ping

# 2. Check cache TTL isn't too low
# config/akindutire-authorization.php
'entity_cache_ttl' => 300, // Increase if needed

# 3. Add database indexes
php artisan migrate # Run the index migration
```

### Database Deadlocks

**Error:** `SQLSTATE[40001]: Serialization failure: 1213 Deadlock found`

**Cause:** Concurrent permission updates

**Solution:** Already handled via atomic operations. If using legacy CSV:

```php
// Ensure using JSON format with $casts
protected $casts = [
    'allowed_permissions' => 'array', // ← Add this
];
```

### High Memory Usage

**Symptoms:** PHP processes using >512MB RAM, slow permission checks

**Diagnosis:**

```php
$article = Article::find(1);
$jsonSize = strlen(json_encode($article->allowed_permissions));
$count = count($article->allowed_permissions);

echo "Permission JSON size: {$jsonSize} bytes\n";
echo "Permission count: {$count}\n";
echo "Average bytes per permission: " . ($count > 0 ? $jsonSize / $count : 0) . "\n";

// If >10KB, permissions list is too large
// Recommended: <10KB (250-400 permissions)
```

**Solutions for Granular Permission Systems:**

**1. Enable Size Validation (Recommended)**

Prevent oversized permission arrays via configuration:

```php
// config/akindutire-authorization.php
'max_permission_size_bytes' => 10240, // 10KB limit
'max_permission_count' => 500,        // Max 500 permissions

// Disable validation (not recommended for production)
'max_permission_size_bytes' => null,
'max_permission_count' => null,
```

**2. Use Permission Namespacing**

Group related permissions with dot notation for better organization:

```php
// ❌ Before: Many individual permissions (verbose)
$article->grantPermission([
    'can_edit',
    'can_delete',
    'can_publish',
    'can_unpublish',
    'can_archive',
    'can_restore',
    'can_duplicate',
    'can_export',
    'can_import',
    'can_translate',
    'can_schedule',
    'can_preview',
    // ... 200+ more permissions
]);

// ✅ After: Namespaced permissions (organized)
$article->grantPermission([
    'article.edit',
    'article.delete',
    'article.publish',
    'article.archive',
    'article.export',
    'analytics.view',
    'analytics.export',
    'comments.moderate',
    'comments.delete',
    // Easier to manage, same granularity
]);
```

**3. Use Shorter Permission Names**

Use concise abbreviations to reduce JSON size:

```php
// ❌ Verbose: 45 bytes per permission average
'can_edit_article_metadata'
'can_delete_article_permanently'
'can_schedule_article_publishing'

// ✅ Concise: 15 bytes per permission average
'article.edit'
'article.delete'
'article.schedule'

// 3x reduction in storage size
```

**4. Batch Related Permissions**

Instead of hundreds of individual permissions, group by feature:

```php
// ❌ Too granular (500+ permissions)
['article.edit.title', 'article.edit.body', 'article.edit.meta',
 'article.edit.tags', 'article.edit.category', 'article.edit.author', ...]

// ✅ Feature-level permissions (manageable)
['article.edit', 'article.delete', 'article.publish', 'analytics.view']

// Check for specific sub-features in application logic, not permissions
if ($article->hasPermission('article.edit')) {
    // Application decides which fields are editable
}
```

**When to Use Each Approach:**

| Permissions | Recommended Approach                                              |
| ----------- | ----------------------------------------------------------------- |
| <100        | Default JSON column                                               |
| 100-500     | Namespacing + shorter names                                       |
| >500        | Re-evaluate permission granularity, use feature-level permissions |

**Production Checklist:**

- [ ] Configure size limits in config file
- [ ] Monitor permission JSON sizes in production
- [ ] Use namespacing for logical grouping (e.g., `article.edit`, not `can_edit_article`)
- [ ] Keep permission names short and meaningful
- [ ] Review permission list regularly, remove unused permissions

### Missing Indexes

**Symptoms:** Slow lookups by uuid/email/slug

**Check:**

```sql
-- MySQL
EXPLAIN SELECT * FROM articles WHERE uuid = 'abc-123';
-- Should show: type: ref, key: idx_articles_uuid

-- If showing: type: ALL (full table scan)
```

**Solution:**

```bash
# Add to config
# config/akindutire-authorization.php
'indexed_tables' => ['articles'],
'indexed_properties' => ['uuid'],

# Run migration
php artisan migrate
```

---

## Migration Guide

### From v1.x to v2.0

#### 1. Update Composer

```bash
composer update akindutire/authorization-pkg
```

#### 2. Republish Config

```bash
php artisan vendor:publish --tag=authorization-config --force
```

The config file is now `akindutire-authorization.php` (renamed from `authorization.php`).

#### 3. Update Models

The `HasPermissions` trait now automatically adds the necessary casts. You can optionally add them explicitly for clarity:

```php
class Article extends Model
{
    use HasPermissions;

    // Optional: Explicitly define casts (trait adds these automatically if missing)
    protected $casts = [
        'allowed_permissions' => 'array',
        'revoked_permissions' => 'array',
    ];
}
```

#### 4. Run New Migrations

```bash
php artisan migrate
```

This converts TEXT columns to JSON and adds performance indexes.

#### 5. Update Config References

Update `config/akindutire-authorization.php`:

```php
'indexed_tables' => [
    'users',
    'articles',
    // Add all tables with HasPermissions trait
],

'indexed_properties' => [
    'uuid',
    'email',
    'slug',
    // Add all lookup properties
],

'cache_keys' => [
    'uuid',
    'email',
    'slug',
    // Same as indexed_properties
],
```

#### 6. Configure Redis

```bash
# .env
CACHE_DRIVER=redis
PERMISSION_CACHE_TTL=300
```

#### 7. Update Deployment Script

```bash
# Add to deploy.sh
php artisan permission:cache
```

#### 8. Test

```bash
php artisan tinker

>>> $article = Article::first();
>>> $article->grantPermission('can_test');
>>> $article->hasPermission('can_test');
=> true

>>> Cache::has('entity.App.Models.Article.id.' . $article->id);
=> true  // Cache is working
```

### Data Migration (CSV → JSON)

If you have existing CSV data:

```php
// Run this once to migrate data
Article::chunk(1000, function($articles) {
    foreach ($articles as $article) {
        // Check if still in CSV format
        if (is_string($article->allowed_permissions)) {
            // Laravel will auto-convert to JSON on save (via $casts)
            $article->save();
        }
    }
});
```

---

## Important Notes & Pitfalls

### ⚠️ Critical: Always Add `$casts`

**Why:** Without `$casts`, permissions use legacy CSV format:

- 75% more storage
- String parsing overhead on every check
- No database-level querying
- Cache invalidation may not work correctly

### ⚠️ Request Value Extraction Priority

Remember the extraction order:

1. `input()` - POST/PUT body
2. `route()` - Route parameters
3. `query()` - Query strings
4. `json()` - JSON body

If you have both route parameter AND form data with same key, **form data wins**.

### ⚠️ Memoization vs Caching

- **Memoization** (PermissionSvc): Only helps with manual service reuse
- **Caching** (Attributes): Handles all normal usage

Don't rely on memoization for performance - it rarely activates in normal usage.

### ⚠️ Config File Name Changed

v2.0 renamed config from `authorization.php` to `akindutire-authorization.php` to prevent conflicts with other packages.

Update references:

```php
// Old (v1.x)
config('authorization.entity_cache_ttl')

// New (v2.0)
config('akindutire-authorization.entity_cache_ttl')
```

### ⚠️ Generate Migrations Per Table

You must generate a separate migration for each table that needs permissions:

```bash
# Generate a migration for each entity table
php artisan make:permission-migration users
php artisan make:permission-migration articles
php artisan make:permission-migration posts

# Then run all migrations
php artisan migrate
```

Each command creates a timestamped migration file customized for the specified table.

### ⚠️ Cache Warming Required

Add to deployment pipeline or first request will be slow:

```bash
php artisan permission:cache
```

### ⚠️ Atomic Operations

Use `grantPermission()` / `revokePermission()`, not manual array manipulation:

```php
// ✅ Atomic, race-condition safe
$article->grantPermission('can_broadcast');

// ❌ Race condition possible
$perms = $article->getAllowedPermissions();
$perms[] = 'can_broadcast';
// Don't do this - use grantPermission() instead
```

---

## Production Checklist

Before deploying to production:

- [ ] All models have `$casts` for `allowed_permissions` and `revoked_permissions`
- [ ] `config/akindutire-authorization.php` lists all tables in `indexed_tables`
- [ ] `config/akindutire-authorization.php` lists all lookup properties in `indexed_properties`
- [ ] Redis is configured and operational
- [ ] Migrations have been run (`php artisan migrate`)
- [ ] Permission cache warming added to deployment (`php artisan permission:cache`)
- [ ] Cache TTL configured appropriately (`entity_cache_ttl`)
- [ ] Exception messages customized if needed
- [ ] Cache hit rate monitored (target: >95%)
- [ ] Permission check latency monitored (target: <50ms p99)

---

## Support & Resources

- **GitHub**: [akindutire/authorization-pkg](https://github.com/akindutire/authorization-pkg)
- **Issues**: [Report bugs or request features](https://github.com/akindutire/authorization-pkg/issues)
- **Performance Guide**: See [SCALABILITY.md](SCALABILITY.md) for detailed optimization guide
- **Changelog**: See [CHANGELOG.md](CHANGELOG.md) for version history

---

**Last Updated:** 2026-06-02
**Package Version:** 2.0.0
**License:** MIT
