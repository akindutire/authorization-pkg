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
    // Ability templates for different entity roles/types
    'abilities' => [
        'article_editor' => ['can_be_edited', 'can_be_deleted'],
        'article_publisher' => ['can_be_edited', 'can_be_published', 'can_be_featured'],
        'team_admin' => ['can_invite', 'can_remove_members', 'can_manage_billing'],
        'team_member' => ['can_invite', 'can_view_analytics'],
        'org_premium' => ['can_use_api', 'can_export_data', 'can_white_label'],
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
    // List all tables where entities have the HasPermissions trait
    'indexed_tables' => [
        'articles',
        'team_members',
        'organizations',
        'users',
        // Add any other entity tables
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

### Defining Abilities

Create enums for your entities' abilities. Remember: abilities describe what the entity itself can do, not what users can do to it.

```php
<?php

namespace App\Enums;

// Article abilities - what articles can do
enum ArticleAbilities: string
{
    case CAN_BE_EDITED = 'can_be_edited';
    case CAN_BE_DELETED = 'can_be_deleted';
    case CAN_BE_PUBLISHED = 'can_be_published';
    case CAN_BE_FEATURED = 'can_be_featured';
    case CAN_BE_BROADCASTED = 'can_be_broadcasted';
}

// Team member abilities - what team members can do
enum TeamMemberAbilities: string
{
    case CAN_INVITE = 'can_invite';
    case CAN_REMOVE_MEMBERS = 'can_remove_members';
    case CAN_MANAGE_BILLING = 'can_manage_billing';
    case CAN_VIEW_ANALYTICS = 'can_view_analytics';
}

// Organization abilities - what organizations can do
enum OrganizationAbilities: string
{
    case CAN_USE_API = 'can_use_api';
    case CAN_EXPORT_DATA = 'can_export_data';
    case CAN_WHITE_LABEL = 'can_white_label';
}
```

### Protecting Controller Methods

Use PHP 8 attributes to protect methods. The key insight: you're checking if the **entity itself** has the ability, not checking if a user can act on it.

```php
<?php

namespace App\Http\Controllers;

use App\Enums\ArticleAbilities;
use App\Enums\TeamMemberAbilities;
use App\Models\Article;
use App\Models\TeamMember;
use Akindutire\Authorization\Attributes\HasAny;
use Akindutire\Authorization\Attributes\HasAll;
use Akindutire\Authorization\Attributes\SubjectValue;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * Check if the ARTICLE entity has the ability to be edited
     * This is NOT checking if a user can edit - it's checking if the article itself can be edited
     */
    #[HasAny([ArticleAbilities::CAN_BE_EDITED->value], Article::class, 'id')]
    public function update(
        #[SubjectValue] int $id,
        Request $request
    ) {
        // Only executes if the Article has 'can_be_edited' ability
        $article = Article::find($id);
        $article->update($request->validated());

        return response()->json($article);
    }

    /**
     * Check if the ARTICLE has BOTH abilities
     * The article controls whether it can be published and featured
     */
    #[HasAll([
        ArticleAbilities::CAN_BE_PUBLISHED->value,
        ArticleAbilities::CAN_BE_FEATURED->value
    ], Article::class, 'id')]
    public function publishAndFeature(#[SubjectValue] int $id)
    {
        $article = Article::find($id);
        $article->update([
            'published_at' => now(),
            'featured' => true,
        ]);

        return response()->json($article);
    }
}

class TeamController extends Controller
{
    /**
     * Check if the TEAM MEMBER has the ability to invite others
     * The team member entity controls who can invite, not a separate user permission
     */
    #[HasAny([TeamMemberAbilities::CAN_INVITE->value], TeamMember::class, 'id')]
    public function inviteMember(
        #[SubjectValue('member_id')] Request $request
    ) {
        $member = TeamMember::find($request->member_id);
        // Send invitation...

        return response()->json(['message' => 'Invitation sent']);
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

1. **`$actions`**: Array of ability strings
   - Example: `['can_be_edited', 'can_be_published']` for articles
   - Example: `['can_invite', 'can_manage_billing']` for team members
   - Use enum values: `[ArticleAbilities::CAN_BE_EDITED->value]`

2. **`$subjectDefinition`**: Fully-qualified class name of the entity being checked
   - Example: `Article::class`, `TeamMember::class`, `Organization::class`
   - Must be an Eloquent model with HasPermissions trait

3. **`$subjectDefinitionProperty`**: Property to use for entity lookup (default: `'id'`)
   - Example: `'uuid'`, `'slug'`, `'member_id'`
   - Must match a database column on the subject model

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
// Route: POST /articles/publish
// Body: {"article_id": 123}

#[HasAny(['can_be_published'], Article::class, 'id')]
public function publish(#[SubjectValue('article_id')] Request $request)
{
    // Middleware extracts: $request->input('article_id') → 123
    // Looks up: Article::where('id', 123)->first()
    // Checks: Does this Article entity have 'can_be_published' ability?
    // Note: We're checking the ARTICLE's ability, not a user's permission
}
```

### Granting and Revoking Abilities

Grant abilities to any entity - remember, abilities belong to the entity itself:

```php
// Articles have publication abilities
$article = Article::find(1);
$article->grantPermission('can_be_edited');
$article->grantPermission(['can_be_edited', 'can_be_published', 'can_be_featured']);

// Team members have role-based abilities
$member = TeamMember::find(1);
$member->grantPermission('can_invite');
$member->grantPermission(['can_invite', 'can_manage_billing', 'can_view_analytics']);

// Organizations have feature abilities
$org = Organization::find(1);
$org->grantPermission(['can_use_api', 'can_export_data', 'can_white_label']);

// Revoke abilities (atomic - adds to revoked list)
$article->revokePermission('can_be_deleted'); // Lock the article from deletion
$member->revokePermission(['can_manage_billing']); // Remove billing access
```

### Checking Abilities Manually

Check if an entity has specific abilities:

```php
$article = Article::find(1);

// Check single ability - does this ARTICLE have the ability to be edited?
if ($article->hasPermission('can_be_edited')) {
    // The article can be edited
}

// Check if entity has ANY of the abilities
if ($article->hasAnyPermission(['can_be_edited', 'can_be_published'])) {
    // Article has at least one ability
}

// Check if entity has ALL abilities
if ($article->hasAllPermissions(['can_be_published', 'can_be_featured'])) {
    // Article can be both published and featured
}

// Get effective abilities (allowed - revoked)
$abilities = $article->getEffectivePermissions();
// Returns: ['can_be_edited', 'can_be_published']

// Check team member abilities
$member = TeamMember::find(1);
if ($member->hasPermission('can_invite')) {
    // This team member can invite others
}
```

---

## Advanced Features

### Lookup by Custom Properties

You can lookup entities by any property (not just `id`). This is useful for different models with different identifiers:

#### Articles by Slug

```php
#[HasAny(['can_be_published'], Article::class, 'slug')]
public function publishBySlug(#[SubjectValue('article_slug')] Request $request)
{
    // Looks up: Article::where('slug', $request->article_slug)->first()
    // Checks: Does this Article have 'can_be_published' ability?
    $article = Article::where('slug', $request->article_slug)->firstOrFail();
    $article->update(['published_at' => now()]);
}
```

#### Team Members by UUID

```php
#[HasAny(['can_invite'], TeamMember::class, 'uuid')]
public function inviteByUuid(#[SubjectValue('member_uuid')] Request $request)
{
    // Looks up: TeamMember::where('uuid', $request->member_uuid)->first()
    // Checks: Does this TeamMember have 'can_invite' ability?
    $member = TeamMember::where('uuid', $request->member_uuid)->firstOrFail();
    // Send invitation...
}
```

#### Organizations by Tenant ID

```php
#[HasAny(['can_use_api'], Organization::class, 'tenant_id')]
public function apiAccess(#[SubjectValue('org_tenant_id')] Request $request)
{
    // Looks up: Organization::where('tenant_id', $request->org_tenant_id)->first()
    // Checks: Does this Organization have 'can_use_api' ability?
    $org = Organization::where('tenant_id', $request->org_tenant_id)->firstOrFail();
    return response()->json(['api_key' => $org->api_key]);
}
```

> **Performance Tip**: Add these properties to `indexed_properties` in config for fast lookups.

### Route Parameter Extraction

Extract values from route parameters:

```php
// Route definition
Route::put('/articles/{article_id}/publish', [ArticleController::class, 'publish']);

// Controller
#[HasAny(['can_be_published'], Article::class, 'id')]
public function publish(#[SubjectValue('article_id')] Request $request)
{
    // Middleware automatically extracts {article_id} from route
    // Checks: Does Article with id={article_id} have 'can_be_published' ability?
}

// Works with any model
Route::post('/teams/{member_id}/invite', [TeamController::class, 'invite']);

#[HasAny(['can_invite'], TeamMember::class, 'id')]
public function invite(#[SubjectValue('member_id')] Request $request)
{
    // Checks: Does TeamMember with id={member_id} have 'can_invite' ability?
}
```

### Using the Facade

For programmatic ability checks on any entity:

```php
use Akindutire\Authorization\Facades\EntityPermission;

// Check Article abilities
$article = Article::find(1);
if (EntityPermission::subject($article)->hasAny(['can_be_published'])) {
    // This article can be published
}

if (EntityPermission::subject($article)->hasAll(['can_be_edited', 'can_be_featured'])) {
    // This article has both abilities
}

// Check TeamMember abilities
$member = TeamMember::find(1);
if (EntityPermission::subject($member)->hasAny(['can_invite', 'can_manage_billing'])) {
    // This member has at least one of these abilities
}

// Check Organization abilities
$org = Organization::find(1);
if (EntityPermission::subject($org)->hasAll(['can_use_api', 'can_export_data'])) {
    // This organization has both abilities
}

// Get ability templates for a role from config
$adminAbilities = EntityPermission::getAbilities('admin');
$article->grantPermission($adminAbilities);
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

Caches are automatically cleared when entity abilities change:

```php
// When granting abilities to an Article
$article->grantPermission('can_be_published');
// Automatically clears all cache entries for this Article:
// - entity.App.Models.Article.id.{article_id}
// - entity.App.Models.Article.uuid.{article_uuid}
// - entity.App.Models.Article.slug.{article_slug}
// (for all properties in 'cache_keys' config)

// When granting abilities to a TeamMember
$member->grantPermission('can_invite');
// Automatically clears all cache entries for this TeamMember
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

Add abilities to an entity (atomic operation for single, batch for multiple).

```php
// Grant a single ability to an Article
$article->grantPermission('can_be_published');

// Grant multiple abilities at once
$article->grantPermission(['can_be_edited', 'can_be_published', 'can_be_featured']);

// Grant abilities to a TeamMember
$member->grantPermission(['can_invite', 'can_manage_billing']);
```

#### `revokePermission(string|array $permission): bool`

Revoke abilities from an entity (atomic operation for single, batch for multiple).

```php
// Revoke a single ability from an Article
$article->revokePermission('can_be_deleted');

// Revoke multiple abilities
$article->revokePermission(['can_be_deleted', 'can_be_featured']);

// Revoke abilities from a TeamMember
$member->revokePermission(['can_manage_billing']);
```

#### `hasPermission(string $permission): bool`

Check if entity has a specific ability.

```php
// Check if Article has an ability
if ($article->hasPermission('can_be_published')) {
    // Article has this ability
}

// Check if TeamMember has an ability
if ($member->hasPermission('can_invite')) {
    // Member has this ability
}
```

#### `hasAnyPermission(array $permissions): bool`

Check if entity has at least one ability.

```php
if ($article->hasAnyPermission(['can_be_edited', 'can_be_published'])) {
    // Article has at least one ability
}

if ($member->hasAnyPermission(['can_invite', 'can_manage_billing'])) {
    // Member has at least one ability
}
```

#### `hasAllPermissions(array $permissions): bool`

Check if entity has all abilities.

```php
if ($article->hasAllPermissions(['can_be_published', 'can_be_featured'])) {
    // Article has both abilities
}

if ($member->hasAllPermissions(['can_invite', 'can_view_analytics'])) {
    // Member has both abilities
}
```

#### `getAllowedPermissions(): array`

Get all allowed abilities for an entity.

```php
$abilities = $article->getAllowedPermissions();
// Returns: ['can_be_edited', 'can_be_published', 'can_be_featured']

$memberAbilities = $member->getAllowedPermissions();
// Returns: ['can_invite', 'can_manage_billing']
```

#### `getRevokedPermissions(): array`

Get all revoked abilities for an entity.

```php
$revoked = $article->getRevokedPermissions();
// Returns: ['can_be_deleted']

$memberRevoked = $member->getRevokedPermissions();
// Returns: ['can_remove_members']
```

#### `getEffectivePermissions(): array`

Get effective abilities (allowed - revoked).

```php
$effective = $article->getEffectivePermissions();
// Returns: allowed_permissions minus revoked_permissions

$memberEffective = $member->getEffectivePermissions();
// Returns: member's effective abilities
```

### Facade: `EntityPermission`

```php
use Akindutire\Authorization\Facades\EntityPermission;
```

#### `subject(Model $subject): PermissionSvc`

Set the subject entity to check abilities on.

```php
// Check abilities on an Article
EntityPermission::subject($article)

// Check abilities on a TeamMember
EntityPermission::subject($member)

// Check abilities on an Organization
EntityPermission::subject($org)
```

#### `hasAny(array $actions): bool`

Check if subject has any of the specified abilities.

```php
// Check Article abilities
EntityPermission::subject($article)->hasAny(['can_be_published', 'can_be_featured'])

// Check TeamMember abilities
EntityPermission::subject($member)->hasAny(['can_invite', 'can_manage_billing'])
```

#### `hasAll(array $actions): bool`

Check if subject has all specified abilities.

```php
// Check Article has both abilities
EntityPermission::subject($article)->hasAll(['can_be_edited', 'can_be_published'])

// Check TeamMember has all abilities
EntityPermission::subject($member)->hasAll(['can_invite', 'can_view_analytics'])
```

#### `getAbilities(string $role): array`

Get ability template for a specific role from config.

```php
// Get ability templates from config
$editorAbilities = EntityPermission::getAbilities('article_editor');
$adminAbilities = EntityPermission::getAbilities('team_admin');

// Grant them to entities
$article->grantPermission($editorAbilities);
$member->grantPermission($adminAbilities);
```

### Attributes

#### `#[HasAny(array $actions, string $model, string $modelProperty = 'id')]`

Annotates method. Require entity to have at least one ability.

```php
// Check if Article has ability to be edited
#[HasAny(['can_be_edited'], Article::class, 'id')]

// Check if TeamMember has invite ability
#[HasAny(['can_invite'], TeamMember::class, 'id')]

// Check by custom property
#[HasAny(['can_be_published'], Article::class, 'slug')]
```

#### `#[HasAll(array $actions, string $model, string $modelProperty = 'id')]`

Annotates method. Require entity to have all specified abilities.

```php
// Article must have both abilities
#[HasAll(['can_be_edited', 'can_be_published'], Article::class, 'id')]

// TeamMember must have both abilities
#[HasAll(['can_invite', 'can_manage_billing'], TeamMember::class, 'id')]
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
'indexed_properties' => ['uuid', 'slug', 'tenant_id'],

// And use them in attributes for different entities
#[HasAny(['can_be_published'], Article::class, 'slug')]
#[HasAny(['can_invite'], TeamMember::class, 'uuid')]
#[HasAny(['can_use_api'], Organization::class, 'tenant_id')]
```

❌ **Don't:**

```php
// Using unindexed property
#[HasAny(['can_be_edited'], Article::class, 'custom_field')]
// Without adding 'custom_field' to indexed_properties
```

### 3. Use Enums for Type Safety (Recommended)

✅ **Do (Best for large applications):**

```php
// Define entity-specific ability enums
enum ArticleAbilities: string {
    case CAN_BE_EDITED = 'can_be_edited';
    case CAN_BE_DELETED = 'can_be_deleted';
    case CAN_BE_PUBLISHED = 'can_be_published';
}

enum TeamMemberAbilities: string {
    case CAN_INVITE = 'can_invite';
    case CAN_MANAGE_BILLING = 'can_manage_billing';
}

// Use in attributes
#[HasAny([ArticleAbilities::CAN_BE_EDITED->value], Article::class)]
#[HasAny([TeamMemberAbilities::CAN_INVITE->value], TeamMember::class)]
```

**Benefits:**

- IDE autocomplete for all available abilities
- Compile-time checking prevents typos
- Centralized ability definitions per entity type
- Easy refactoring

✅ **Also acceptable (for smaller applications):**

```php
#[HasAny(['can_be_edited'], Article::class)] // Direct strings work fine
#[HasAny(['can_invite'], TeamMember::class)]
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
// Atomic single ability grant to an Article
$article->grantPermission('can_be_published');

// Atomic batch operation for multiple abilities
$article->grantPermission(['can_be_edited', 'can_be_published', 'can_be_featured']);

// Atomic operations work on any entity
$member->grantPermission(['can_invite', 'can_manage_billing']);
$org->grantPermission(['can_use_api', 'can_export_data']);
```

❌ **Don't (Race condition possible):**

```php
// Manual array manipulation - NOT atomic
$abilities = $article->getAllowedPermissions();
$abilities[] = 'can_be_published';
// No safe public method for this - use grantPermission() instead
```

**Why it matters:** In high-concurrency scenarios (multiple processes updating entity abilities simultaneously), atomic operations prevent lost updates.

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

### Slow Ability Checks (>100ms)

**Diagnosis:**

```php
// Enable query log to check if caching is working
DB::enableQueryLog();
$article->hasPermission('can_be_edited');
$queries = DB::getQueryLog();

if (count($queries) > 1) {
    // Multiple queries = caching not working
}

// Works for any entity
$member->hasPermission('can_invite');
$org->hasPermission('can_use_api');
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

**Cause:** Concurrent entity ability updates

**Solution:** Already handled via atomic operations. If using legacy CSV:

```php
// Ensure using JSON format with $casts
protected $casts = [
    'allowed_permissions' => 'array', // ← Add this
];
```

### High Memory Usage

**Symptoms:** PHP processes using >512MB RAM, slow ability checks

**Diagnosis:**

```php
// Check any entity's ability storage size
$article = Article::find(1);
$jsonSize = strlen(json_encode($article->allowed_permissions));
$count = count($article->allowed_permissions);

echo "Ability JSON size: {$jsonSize} bytes\n";
echo "Ability count: {$count}\n";
echo "Average bytes per ability: " . ($count > 0 ? $jsonSize / $count : 0) . "\n";

// If >10KB, abilities list is too large
// Recommended: <10KB (250-400 abilities)

// Works for any entity
$member = TeamMember::find(1);
$memberSize = strlen(json_encode($member->allowed_permissions));
```

**Solutions for Granular Ability Systems:**

**1. Enable Size Validation (Recommended)**

Prevent oversized ability arrays via configuration:

```php
// config/akindutire-authorization.php
'max_permission_size_bytes' => 10240, // 10KB limit
'max_permission_count' => 500,        // Max 500 abilities

// Disable validation (not recommended for production)
'max_permission_size_bytes' => null,
'max_permission_count' => null,
```

**2. Use Ability Namespacing**

Group related abilities with dot notation for better organization:

```php
// ❌ Before: Many individual abilities (verbose)
$article->grantPermission([
    'can_be_edited',
    'can_be_deleted',
    'can_be_published',
    'can_be_unpublished',
    'can_be_archived',
    'can_be_restored',
    'can_be_duplicated',
    'can_be_exported',
    'can_be_imported',
    'can_be_translated',
    'can_be_scheduled',
    'can_be_previewed',
    // ... 200+ more abilities
]);

// ✅ After: Namespaced abilities (organized, model-agnostic)
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

// Works for any entity type
$member->grantPermission([
    'team.invite',
    'team.remove',
    'billing.view',
    'billing.manage',
]);
```

**3. Use Shorter Ability Names**

Use concise abbreviations to reduce JSON size:

```php
// ❌ Verbose: 45 bytes per ability average
'can_be_edited_with_metadata'
'can_be_deleted_permanently'
'can_be_scheduled_for_publishing'

// ✅ Concise: 15 bytes per ability average (model-agnostic style)
'article.edit'
'article.delete'
'article.schedule'

// 3x reduction in storage size
```

**4. Batch Related Abilities**

Instead of hundreds of individual abilities, group by feature:

```php
// ❌ Too granular (500+ abilities)
['article.edit.title', 'article.edit.body', 'article.edit.meta',
 'article.edit.tags', 'article.edit.category', 'article.edit.author', ...]

// ✅ Feature-level abilities (manageable)
['article.edit', 'article.delete', 'article.publish', 'analytics.view']

// Check for specific sub-features in application logic, not abilities
if ($article->hasPermission('article.edit')) {
    // Application decides which fields are editable
}

// Works for any entity
if ($member->hasPermission('team.manage')) {
    // Application logic determines specific management capabilities
}
```

**When to Use Each Approach:**

| Abilities | Recommended Approach                                            |
| --------- | --------------------------------------------------------------- |
| <100      | Default JSON column                                             |
| 100-500   | Namespacing + shorter names                                     |
| >500      | Re-evaluate ability granularity, use feature-level abilities    |

**Production Checklist:**

- [ ] Configure size limits in config file
- [ ] Monitor ability JSON sizes in production
- [ ] Use namespacing for logical grouping (e.g., `article.edit`, not `can_be_edited_article`)
- [ ] Keep ability names short and meaningful
- [ ] Review ability list regularly, remove unused abilities

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
>>> $article->grantPermission('can_be_edited');
>>> $article->hasPermission('can_be_edited');
=> true

>>> Cache::has('entity.App.Models.Article.id.' . $article->id);
=> true  // Cache is working

// Test with any entity
>>> $member = TeamMember::first();
>>> $member->grantPermission('can_invite');
>>> $member->hasPermission('can_invite');
=> true
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

**Why:** Without `$casts`, entity abilities use legacy CSV format:

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

You must generate a separate migration for each entity table that will have abilities:

```bash
# Generate a migration for each entity table
php artisan make:permission-migration articles
php artisan make:permission-migration team_members
php artisan make:permission-migration organizations
php artisan make:permission-migration users

# Then run all migrations
php artisan migrate
```

Each command creates a timestamped migration file customized for the specified table, adding ability columns.

### ⚠️ Cache Warming Required

Add to deployment pipeline or first request will be slow:

```bash
php artisan permission:cache
```

### ⚠️ Atomic Operations

Use `grantPermission()` / `revokePermission()`, not manual array manipulation:

```php
// ✅ Atomic, race-condition safe
$article->grantPermission('can_be_published');

// ❌ Race condition possible
$abilities = $article->getAllowedPermissions();
$abilities[] = 'can_be_published';
// Don't do this - use grantPermission() instead

// Works for any entity
$member->grantPermission('can_invite');
$org->grantPermission('can_use_api');
```

---

## Production Checklist

Before deploying to production:

- [ ] All entity models have `$casts` for `allowed_permissions` and `revoked_permissions`
- [ ] `config/akindutire-authorization.php` lists all entity tables in `indexed_tables` (articles, team_members, organizations, etc.)
- [ ] `config/akindutire-authorization.php` lists all lookup properties in `indexed_properties`
- [ ] Redis is configured and operational
- [ ] Migrations have been run for all entity tables (`php artisan migrate`)
- [ ] Ability cache warming added to deployment (`php artisan permission:cache`)
- [ ] Cache TTL configured appropriately (`entity_cache_ttl`)
- [ ] Exception messages customized if needed
- [ ] Cache hit rate monitored (target: >95%)
- [ ] Ability check latency monitored (target: <50ms p99)

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
