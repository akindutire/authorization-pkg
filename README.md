# Laravel Authorization Package

A modern, attribute-based authorization package for Laravel 9+ that works with any Eloquent model using PHP 8 attributes.

## Features

- **Attribute-based authorization** - Use PHP 8 attributes for clean, declarative permission checks
- **Model-agnostic** - Works with any Eloquent model (User, TeamMember, etc.)
- **Flexible subject resolution** - Lookup subjects by any property (id, uuid, email, etc.)
- **Permission inheritance** - Supports allowed and revoked permissions
- **Facade support** - Easy-to-use facade for permission checks
- **Middleware included** - Automatic permission validation using reflection

## Requirements

- PHP 8.0 or higher
- Laravel 9.0 or higher

## Installation

Install the package via Composer:

```bash
composer require akindutire/authorization-pkg
```

The package will auto-register via Laravel's package discovery.

## Setup

### 1. Publish Configuration

```bash
php artisan vendor:publish --tag=authorization-config
```

This creates `config/authorization.php` for customizing default permissions.

### 2. Publish and Run Migrations

```bash
php artisan vendor:publish --tag=authorization-migrations
php artisan migrate
```

Or manually add permission fields to your tables:

```php
Schema::table('users', function (Blueprint $table) {
    $table->text('allowed_permissions')->nullable();
    $table->text('revoked_permissions')->nullable();
});
```

### 3. Add Trait to Models

Add the `HasPermissions` trait to any model that needs authorization:

```php
use Akindutire\Authorization\Traits\HasPermissions;

class User extends Authenticatable
{
    use HasPermissions;
}

class TeamMember extends Model
{
    use HasPermissions;
}
```

### 4. Register Middleware

Add the middleware to your `app/Http/Kernel.php`:

```php
protected $middlewareAliases = [
    // ...
    'validate.subject.action' => \Akindutire\Authorization\Middleware\ValidateSubjectAction::class,
];
```

Or apply it to routes:

```php
Route::middleware(['validate.subject.action'])->group(function () {
    // Your routes
});
```

## Usage

### Defining Actions/Permissions

Define your application's actions in the `AppActions` enum:

```php
use Akindutire\Authorization\Enums\AppActions;

enum AppActions: string
{
    case CAN_UPDATE_COMPANY = 'can_update_company';
    case CAN_INVITE_MEMBER = 'can_invite_member';
    case CAN_VIEW_PITCH = 'can_view_pitch';
    // Add your actions here
}
```

### Protecting Controller Methods

Use PHP 8 attributes to protect controller methods:

```php
use Akindutire\Authorization\Attributes\HasAny;
use Akindutire\Authorization\Attributes\HasAll;
use Akindutire\Authorization\Attributes\SubjectValue;
use App\Models\TeamMember;

class CompanyController extends Controller
{
    // Check if team member has ANY of the specified permissions
    #[HasAny([AppActions::CAN_UPDATE_COMPANY->value], TeamMember::class, 'id')]
    public function update(#[SubjectValue('member_id')] Request $request)
    {
        // member_id will be extracted from $request->member_id
        // TeamMember with that id will be looked up
        // Their permissions will be checked
    }

    // Check if team member has ALL of the specified permissions
    #[HasAll([
        AppActions::CAN_UPDATE_COMPANY->value,
        AppActions::CAN_INVITE_MEMBER->value
    ], TeamMember::class, 'id')]
    public function invite(#[SubjectValue('member_id')] Request $request)
    {
        // Requires both permissions
    }
}
```

### Attribute Parameters

**`#[HasAny(array $actions, string $modelClass, string $lookupProperty)]`**
- `$actions` - Array of permission strings to check
- `$modelClass` - The Eloquent model class to lookup (e.g., `TeamMember::class`)
- `$lookupProperty` - The property to use for lookup (default: `'id'`)

**`#[SubjectValue(string $key)]`**
- `$key` - The request parameter key to extract

The middleware extracts the value in this priority order:
1. `$request->input($key)` - Form data (POST/PUT)
2. `$request->route($key)` - Route parameters (e.g., `/users/{member_id}`)
3. `$request->query($key)` - Query strings (e.g., `?member_id=123`)
4. `$request->json($key)` - JSON payload

### Managing Permissions

#### Granting Permissions

```php
$user = User::find(1);

// Set permissions directly
$user->updatePermission(['can_view', 'can_edit', 'can_delete']);

// Grant a single permission
$user->grantPermission('can_update_company');

// Set permissions from a role (uses config)
$user->setPermissionsFromRole('owner');
```

#### Revoking Permissions

```php
// Revoke a permission
$user->revokePermission('can_delete');
```

#### Checking Permissions

```php
// Check single permission
if ($user->hasPermission('can_edit')) {
    // User has permission
}

// Check if has any of the permissions
if ($user->hasAnyPermission(['can_edit', 'can_delete'])) {
    // User has at least one
}

// Check if has all permissions
if ($user->hasAllPermissions(['can_edit', 'can_view'])) {
    // User has all
}

// Get effective permissions (allowed - revoked)
$permissions = $user->getEffectivePermissions();
```

### Using the Facade

```php
use Akindutire\Authorization\Facades\EntityPermission;

$teamMember = TeamMember::find(1);

// Check if member has any permission
if (EntityPermission::subject($teamMember)->hasAny(['can_edit', 'can_view'])) {
    // Has at least one
}

// Check if member has all permissions
if (EntityPermission::subject($teamMember)->hasAll(['can_edit', 'can_view'])) {
    // Has all
}

// Get default actions for a role
$permissions = EntityPermission::getDefaultActions('owner');
```

### Configuring Default Actions per Role

In `config/authorization.php`:

```php
'default_actions' => [
    'owner' => [
        'can_update_company',
        'can_invite_member',
        'can_remove_member',
        'can_view_pitch',
        'can_update_pitch',
    ],
    'admin' => [
        'can_invite_member',
        'can_view_pitch',
        'can_update_pitch',
    ],
    'member' => [
        'can_view_pitch',
    ],
],
```

Then assign permissions from role:

```php
$teamMember->setPermissionsFromRole('owner');
```

## How It Works

1. **Middleware reads attributes** - `ValidateSubjectAction` uses reflection to read method attributes
2. **Extract subject identifier** - Gets the value from Request (input/route/query/json)
3. **Subject lookup** - Finds the subject (model) using the extracted value
4. **Permission check** - Calls `hasAny()` or `hasAll()` on the `PermissionSvc`
5. **Resolution** - Compares `allowed_permissions` - `revoked_permissions`
6. **Authorization** - Returns 403 if unauthorized, continues if authorized

### Request Flow Example

```php
// Route
POST /company/update

// Request data
{
    "member_id": 123,
    "name": "New Company Name"
}

// Controller
#[HasAny([AppActions::CAN_UPDATE->value], TeamMember::class, 'id')]
public function update(#[SubjectValue('member_id')] Request $request)

// Flow:
// 1. Middleware extracts: $request->input('member_id') → 123
// 2. Finds subject: TeamMember::where('id', 123)->first()
// 3. Checks: $teamMember->allowed_permissions contains 'can_update'?
// 4. If yes → continue, if no → throw 403
```

## Advanced Usage

### Custom Subject Lookup

Lookup by different properties:

```php
// Lookup by UUID instead of ID
#[HasAny([AppActions::CAN_EDIT->value], User::class, 'uuid')]
public function update(#[SubjectValue('user_uuid')] Request $request)
{
    // Looks up User::where('uuid', $request->user_uuid)
}
```

### Route Parameter Extraction

```php
// Extract from route parameters
Route::put('/members/{member_id}', [Controller::class, 'update']);

#[HasAny([AppActions::CAN_UPDATE->value], TeamMember::class, 'id')]
public function update(#[SubjectValue('member_id')] Request $request)
{
    // Automatically extracts {member_id} from route
}
```

## Exception Handling

The package throws `ValidateSubjectActionException` when authorization fails:

```php
try {
    // Protected action
} catch (\Akindutire\Authorization\Exceptions\ValidateSubjectActionException $e) {
    return response()->json(['error' => $e->getMessage()], 403);
}
```

Customize the exception message in `config/authorization.php`:

```php
'exception' => [
    'message' => 'Custom access denied message',
    'code' => 403,
],
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
