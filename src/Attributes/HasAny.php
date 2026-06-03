<?php

namespace Akindutire\Authorization\Attributes;

use Akindutire\Authorization\Attributes\Interfaces\{SubjectModel, SubjectActionGuardInterface};
use Akindutire\Authorization\Services\PermissionSvc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

/**
 * Attribute to check if a subject has ANY of the specified permissions
 *
 * Usage:
 * #[HasAny([AppActions::CAN_UPDATE->value], TeamMember::class, 'id')]
 * public function update(#[SubjectValue('member_id')] Request $request) {}
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class HasAny implements SubjectActionGuardInterface
{
    private string|int|bool|array $subjectValue;

    /**
     * @param array $actions List of actions/permissions to check
     * @param string $subjectDefinition FQCN of the model to check (default: User::class)
     * @param string $subjectDefinitionProperty Property to use for lookup (default: 'id')
     */
    public function __construct(
        public readonly array $actions,
        public readonly string $subjectDefinition = \App\Models\User::class,
        public readonly string $subjectDefinitionProperty = 'id',
    ) {
    }

    /**
     * Validate if the subject has any of the required permissions
     *
     * Uses a multi-layer caching strategy:
     * 1. Request-level cache (prevents duplicate lookups in same request)
     * 2. Distributed cache (Redis/Memcached for cross-request optimization)
     *
     * @return bool
     * @throws \Exception
     */
    public function validate(): bool
    {
    
        // Generate cache key for entity lookup
        // Format: entity.{ModelClass}.{property}.{value}
        // Example: entity.App\Models\Article.id.123
        $cacheKey = sprintf(
            'entity.%s.%s.%s',
            str_replace('\\', '.', $this->subjectDefinition), // Normalize namespace separators
            $this->subjectDefinitionProperty,
            $this->subjectValue
        );
        
        $cacheTtl = config('akindutire-authorization.entity_cache_ttl', 300);

        $subjectVals = Cache::remember($cacheKey, $cacheTtl, function () {
            // Create fresh model instance for querying
            $entity = App::make($this->subjectDefinition);

            // Validate that the provided class is actually an Eloquent model
            if (!($entity instanceof Model)) {
                throw new \Exception(
                    sprintf(
                        "Cannot resolve action subject, ensure the FQCN in arg #2 is an instance of '%s'",
                        Model::class
                    )
                );
            }

            // Perform the database query
            // This is the ONLY place we hit the DB (unless cache is cold/expired)
            $a = config('akindutire-authorization.column_names.allowed_permissions');
            $b = config('akindutire-authorization.column_names.revoked_permissions');
            $s = $entity->where($this->subjectDefinitionProperty, $this->subjectValue)
                ->select(
                    $a, $b
                )->first();
            if (!$s) {
                // Entity not found, return null to cache this result
                return null;
            }

            if (!isset($s->{$a}) || !isset($s->{$b})) {
                throw new \Exception(
                    sprintf(
                        "The model '%s' must have the properties '%s' and '%s' for permission checking and must be defined as columns in the database and included in the model's fillable or casts as arrays",
                        $this->subjectDefinition,
                        $a,
                        $b
                    )
                );
            }
          
            
            return ['a' => $s->{$a}, 'r' => $s->{$b}];
        });
            
        $subject = new SubjectModel($subjectVals['a'] ?? [], $subjectVals['r'] ?? []);


        // If entity doesn't exist, deny access immediately
        // No need to check permissions if the subject isn't found
        if (!$subject) {
            return false;
        }

        // Delegate permission checking to PermissionSvc
        // The service handles the business logic of allowed vs revoked permissions
        return (App::make(PermissionSvc::class))
            ->subject($subject, null, null)
            ->hasAny($this->actions);
    }

    /**
     * Set the subject value (called by middleware)
     *
     * @param mixed $value
     * @return void
     */
    public function setSubjectValue($value)
    {
        $this->subjectValue = $value;
    }
}
