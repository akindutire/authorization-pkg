# Laravel Model-Agnostic Authorization

A modern, attribute-based authorization package for Laravel 9+ that works with **any** Eloquent model using PHP 8 attributes. Unlike traditional user-centric permission systems, this package lets you attach abilities directly to any model - Articles, Organizations, TeamMembers, or any entity in your application.

**Built for scale** - optimized for applications with millions of entities.

[![Latest Version](https://img.shields.io/packagist/v/akindutire/authorization-pkg.svg?style=flat-square)](https://packagist.org/packages/akindutire/authorization-pkg)
[![Total Downloads](https://img.shields.io/packagist/dt/akindutire/authorization-pkg.svg?style=flat-square)](https://packagist.org/packages/akindutire/authorization-pkg)
[![License](https://img.shields.io/packagist/l/akindutire/authorization-pkg.svg?style=flat-square)](https://packagist.org/packages/akindutire/authorization-pkg)

## Why Model-Agnostic?

Traditional systems: `User` → has Permissions → to perform Actions → on Resources

This package: `Subject` (any model) → has Abilities → validated via Attributes

```php
// ❌ Traditional: User-centric - doesn't model entity-specific abilities
$user->hasPermission('edit-article');

// ✅ This package: Model-agnostic - abilities belong to entities
#[HasAny(['can_edit'], Article::class, 'id')]
public function update(int $articleId) {
    // The ARTICLE itself has 'can_edit' ability
    // The entity being modified controls who can modify it
}
```

## Key Concepts

- **Subject**: Any Eloquent model with the `HasPermissions` trait (User, Article, TeamMember, Organization, etc.)
- **Abilities**: Actions a subject can perform (`can_edit`, `can_publish`, `can_invite`)
- **Attributes**: PHP 8 attributes declaring ability requirements (`#[HasAny]`, `#[HasAll]`)
- **Validation**: Automatic checking via `ValidateSubjectAction` middleware

### How It Works: Architecture Flow

```
Controller Method
    ↓
[Attribute Declaration]
#[HasAny(['can_edit'], Article::class, 'id')]
    ↓
[Middleware: ValidateSubjectAction]
    ↓
[Subject Resolution]
- Lookup model by property: Article::where('id', $value)
- Extract abilities from database columns
    ↓
[Permission Service]
- Check if subject has required abilities
- Respect revoked abilities
    ↓
[Cache Result]
- Multi-layer caching for performance
    ↓
[Allow/Deny Request]
```

### Terminology: Why "Abilities"?

**In code**: The package uses `permissions` (columns/methods) for backward compatibility with Laravel conventions.

**In concept**: We call these **abilities** because:
- **Model-agnostic**: "Article has ability to be edited" (not "Article has permission")
- **Subject-focused**: Abilities belong to the entity being authorized
- **Industry standard**: CanCanCan (Rails) and CASL (JavaScript) use "abilities"
- **Clear semantics**: Describes what subjects *can do*

| Alternative | Why Not? |
|-------------|----------|
| Capabilities | Conflicts with PHP extensions |
| Grants | Sounds temporary |
| Actions | Too generic, conflicts with controllers |
| Permissions | Implies user-centric (kept in code for compatibility) |

## Features

- 🎯 **Model-agnostic** - Works with any Eloquent model, not just Users
- 🏷️ **Attribute-based** - Declarative authorization using PHP 8 attributes
- ⚡ **Auto-invalidating cache** - Detects attribute changes without manual clearing
- 🔍 **Flexible resolution** - Lookup subjects by any property (id, uuid, email, slug)
- 🎭 **Revocation support** - Explicitly deny abilities that override grants
- 🎨 **Facade included** - Easy-to-use facade for programmatic checks
- 🔒 **Middleware validation** - Automatic checks using reflection
- 🚀 **Production-ready** - Multi-layer caching, optimized for 500M+ entities
- ⚛️ **Race condition safe** - Atomic updates using database operations

## Performance Highlights

- **8-25ms** permission check latency (p50) at scale
- **98% reduction** in database queries via intelligent caching
- **99%+ cache hit rate** in production environments
- **Zero runtime reflection overhead** via metadata caching
- **Horizontal scalability** - tested with 500M entities

📊 See [SCALABILITY.md](SCALABILITY.md) for detailed benchmarks.

## Quick Start

### 1. Install

```bash
composer require akindutire/authorization-pkg
```

### 2. Add Abilities to Any Model

```php
use Akindutire\Authorization\Traits\HasPermissions;

// Works with ANY Eloquent model
class Article extends Model
{
    use HasPermissions;

    protected $casts = [
        'allowed_permissions' => 'array',  // What this article can do
        'revoked_permissions' => 'array',  // Explicitly denied
    ];
}

class TeamMember extends Model
{
    use HasPermissions;

    protected $casts = [
        'allowed_permissions' => 'array',  // What this member can do
        'revoked_permissions' => 'array',
    ];
}

class Organization extends Model
{
    use HasPermissions;

    protected $casts = [
        'allowed_permissions' => 'array',  // What this org can do
        'revoked_permissions' => 'array',
    ];
}
```

### 3. Grant Abilities

```php
// Articles have publication abilities
$article = Article::find(1);
$article->grantPermission(['can_be_edited', 'can_be_published']);

// Team members have role-based abilities
$member = TeamMember::find(1);
$member->grantPermission(['can_invite', 'can_manage_billing']);

// Organizations have feature abilities
$org = Organization::find(1);
$org->grantPermission(['can_use_api', 'can_white_label']);
```

### 4. Protect Controller Methods with Attributes

```php
use Akindutire\Authorization\Attributes\{HasAny, HasAll};
use Akindutire\Authorization\Attributes\SubjectValue;

class ArticleController
{
    // Check if Article has 'can_be_edited' ability
    #[HasAny(['can_be_edited'], Article::class, 'id')]
    public function update(
        #[SubjectValue] int $id,
        Request $request
    ) {
        // Only executes if the Article can be edited
        $article = Article::find($id);
        $article->update($request->all());
    }

    // Article must have BOTH abilities
    #[HasAll(['can_be_published', 'can_be_featured'], Article::class, 'id')]
    public function publish(#[SubjectValue] int $id) {
        Article::find($id)->update(['published_at' => now()]);
    }

    // Works with any property, not just 'id'
    #[HasAny(['can_be_viewed'], Article::class, 'slug')]
    public function show(#[SubjectValue] string $slug) {
        return Article::where('slug', $slug)->firstOrFail();
    }
}

class TeamController
{
    // Check TeamMember abilities
    #[HasAny(['can_invite'], TeamMember::class, 'member_id')]
    public function invite(
        #[SubjectValue('member_id')] Request $request
    ) {
        // TeamMember must have 'can_invite' ability
    }
}
```

### 5. Register Middleware

```php
// app/Http/Kernel.php
protected $middlewareAliases = [
    'validate.subject.action' => \Akindutire\Authorization\Middleware\ValidateSubjectAction::class,
];

// routes/web.php
Route::middleware(['validate.subject.action'])->group(function () {
    Route::put('/articles/{id}', [ArticleController::class, 'update']);
    Route::post('/articles/{id}/publish', [ArticleController::class, 'publish']);
});
```

## Use Cases

### 1. Content Management Systems

```php
// Articles control their own editability
$article->grantPermission(['can_be_edited', 'can_be_deleted']);
$article->revokePermission(['can_be_deleted']); // Make read-only

#[HasAny(['can_be_edited'], Article::class, 'id')]
public function update(int $id) { }
```

### 2. Multi-Tenant SaaS

```php
// Organizations have feature abilities
$org->grantPermission(['can_use_api', 'can_export_data', 'can_white_label']);

#[HasAll(['can_use_api'], Organization::class, 'org_id')]
public function apiAccess(#[SubjectValue] int $org_id) { }
```

### 3. Team Collaboration

```php
// Team members have role-based abilities
$member->grantPermission(['can_invite', 'can_view_analytics', 'can_manage_billing']);

#[HasAny(['can_invite', 'can_manage_team'], TeamMember::class, 'id')]
public function addMember(#[SubjectValue] int $id) { }
```

### 4. Resource Sharing

```php
// Documents have sharing abilities
$document->grantPermission(['can_be_shared', 'can_be_commented']);

#[HasAny(['can_be_shared'], Document::class, 'uuid')]
public function share(#[SubjectValue] string $uuid) { }
```

## Core API

### Granting and Revoking Abilities

```php
// Grant abilities (single or multiple)
$subject->grantPermission('can_edit');
$subject->grantPermission(['can_edit', 'can_delete']);

// Revoke abilities (explicitly deny)
$subject->revokePermission('can_delete');
$subject->revokePermission(['can_delete', 'can_admin']);

// Check abilities
$subject->hasPermission('can_edit');              // Check single
$subject->hasAnyPermission(['can_edit', 'can_view']); // Has any
$subject->hasAllPermissions(['can_edit', 'can_publish']); // Has all

// Get abilities
$allowed = $subject->getAllowedPermissions();     // ['can_edit', 'can_view']
$revoked = $subject->getRevokedPermissions();     // ['can_delete']
$effective = $subject->getEffectivePermissions(); // Allowed minus revoked
```

### Facade Usage

```php
use Akindutire\Authorization\Facades\EntityPermission;

// Check if subject has abilities
$canEdit = EntityPermission::subject($article)->hasAny(['can_be_edited']);
$canPublish = EntityPermission::subject($article)->hasAll(['can_be_published', 'can_be_featured']);

// Get role-based ability templates from config
$ownerAbilities = EntityPermission::getAbilities('owner');
```

### Attribute Options

```php
// Basic usage
#[HasAny(['can_edit'], Article::class, 'id')]

// Custom property resolution
#[HasAny(['can_edit'], Article::class, 'uuid')]
#[HasAny(['can_edit'], Article::class, 'slug')]

// Extract subject value from request
#[HasAny(['can_edit'], Article::class, 'id')]
public function update(
    #[SubjectValue('article_id')] Request $request
) { }

// Require all abilities
#[HasAll(['can_publish', 'can_feature'], Article::class, 'id')]
```

## Configuration

### Ability Templates (Optional)

Define reusable ability templates in config:

```php
// config/akindutire-authorization.php
'abilities' => [
    'article_editor' => ['can_be_edited', 'can_be_deleted'],
    'article_publisher' => ['can_be_edited', 'can_be_published', 'can_be_featured'],
    'team_admin' => ['can_invite', 'can_remove', 'can_manage_billing'],
    'team_member' => ['can_invite', 'can_view_analytics'],
],
```

Then use them:

```php
$article->grantPermission(EntityPermission::getAbilities('article_publisher'));
$member->grantPermission(EntityPermission::getAbilities('team_admin'));
```

### Custom Column Names

```php
'column_names' => [
    'allowed_permissions' => 'abilities',        // Rename columns
    'revoked_permissions' => 'denied_abilities',
],
```

### Cache Configuration

```php
'entity_cache_ttl' => 300,  // 5 minutes
'reflection_cache_enabled' => true,
'auto_invalidate_reflection_cache' => true,
```

## Database Setup

### Generate Migrations

```bash
php artisan make:permission-migration articles
php artisan make:permission-migration team_members
php artisan make:permission-migration organizations
```

This creates:

```php
Schema::table('articles', function (Blueprint $table) {
    $table->json('allowed_permissions')->nullable();
    $table->json('revoked_permissions')->nullable();
    // Database-specific indexes for optimal performance
});
```

### Run Migrations

```bash
php artisan migrate
```

## Advanced Examples

### Complex Controller

```php
class ArticleController
{
    #[HasAny(['can_be_edited'], Article::class, 'id')]
    public function update(#[SubjectValue] int $id, Request $request)
    {
        $article = Article::find($id);
        $article->update($request->validated());
        return response()->json($article);
    }

    #[HasAll(['can_be_published', 'can_be_featured'], Article::class, 'id')]
    public function publishAndFeature(#[SubjectValue] int $id)
    {
        $article = Article::find($id);
        $article->update([
            'published_at' => now(),
            'featured' => true,
        ]);
        return response()->json($article);
    }

    #[HasAny(['can_be_viewed'], Article::class, 'slug')]
    public function showBySlug(#[SubjectValue] string $slug)
    {
        return Article::where('slug', $slug)->firstOrFail();
    }
}
```

### Dynamic Ability Management

```php
// Grant abilities based on business logic
if ($user->isAdmin()) {
    $article->grantPermission(['can_be_edited', 'can_be_deleted', 'can_be_published']);
} elseif ($user->isEditor()) {
    $article->grantPermission(['can_be_edited']);
}

// Temporarily revoke abilities
$article->revokePermission(['can_be_deleted']); // Lock article

// Later restore
$article->grantPermission(['can_be_deleted']); // Removes from revoked
```

### Multi-Property Resolution

```php
// By ID
#[HasAny(['can_edit'], User::class, 'id')]
public function updateById(#[SubjectValue] int $id) { }

// By UUID
#[HasAny(['can_edit'], User::class, 'uuid')]
public function updateByUuid(#[SubjectValue] string $uuid) { }

// By email
#[HasAny(['can_login'], User::class, 'email')]
public function authenticate(#[SubjectValue] string $email) { }
```

## Requirements

- PHP 8.1 or higher
- Laravel 9.0 or higher
- Redis or Memcached (recommended for production)

## Documentation

- 📖 [Full Documentation](DOCUMENTATION.md)
- 📈 [Scalability Guide](SCALABILITY.md)
- 🔌 [API Reference](docs/api.html)
- ⚡ [Quick Start](docs/quickstart.html)

## Why This Package?

| Traditional Systems                    | This Package                              |
| -------------------------------------- | ----------------------------------------- |
| User-centric                           | Model-agnostic                            |
| Permissions on users                   | Abilities on any model                    |
| `$user->can('edit-post')`              | `#[HasAny(['can_edit'], Article::class)]` |
| Doesn't scale to entity-specific rules | Entity owns its abilities                 |
| Complex policy classes                 | Declarative attributes                    |

## Comparison

| Feature             | Spatie Permission | Laravel Gates | This Package         |
| ------------------- | ----------------- | ------------- | -------------------- |
| Model-agnostic      | ❌ User-only      | ❌ User-only  | ✅ Any model         |
| Attribute-based     | ❌                | ❌            | ✅                   |
| Auto-caching        | ⚠️ Manual         | ❌            | ✅ Auto-invalidating |
| Flexible resolution | ❌ ID only        | ❌            | ✅ Any property      |
| Revocation support  | ❌                | ⚠️ Limited    | ✅ Native            |
| Scale (entities)    | < 1M              | N/A           | 500M+ tested         |

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security issues, please email security@example.com instead of using the issue tracker.

## Credits

- [Akindutire Ayomide](https://github.com/akindutire)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
