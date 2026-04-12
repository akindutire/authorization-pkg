<?php

namespace Akindutire\Authorization\Attributes;

use Akindutire\Authorization\Attributes\Interfaces\SubjectActionGuardInterface;
use Akindutire\Authorization\Services\PermissionSvc;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

/**
 * Attribute to check if a subject has ALL of the specified permissions
 *
 * Usage:
 * #[HasAll([AppActions::CAN_UPDATE->value, AppActions::CAN_DELETE->value], TeamMember::class, 'id')]
 * public function destroy(#[SubjectValue('member_id')] Request $request) {}
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class HasAll implements SubjectActionGuardInterface
{
    private string|int|bool $subjectValue;

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
     * Validate if the subject has all of the required permissions
     *
     * @return bool
     * @throws \Exception
     */
    public function validate(): bool
    {
        $entity = App::make($this->subjectDefinition);

        if (!($entity instanceof Model)) {
            throw new \Exception(
                sprintf(
                    "Cannot resolve action subject, ensure the FQCN in arg #2 is an instance of '%s'",
                    Model::class
                )
            );
        }

        $subject = $entity->where($this->subjectDefinitionProperty, $this->subjectValue)->first();

        if (!$subject) {
            return false;
        }

        return (App::make(PermissionSvc::class))->subject($subject)->hasAll($this->actions);
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
