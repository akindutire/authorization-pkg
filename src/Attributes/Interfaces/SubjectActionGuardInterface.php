<?php

namespace Akindutire\Authorization\Attributes\Interfaces;

/**
 * Interface for authorization guard attributes
 *
 * Attributes implementing this interface can be used to protect
 * controller methods by checking subject permissions
 */
interface SubjectActionGuardInterface
{
    /**
     * Validate if the subject has the required permissions
     *
     * @return bool
     */
    public function validate(): bool;

    /**
     * Set the subject value (typically an ID or unique identifier)
     * used to lookup the subject model
     *
     * @param mixed $value
     * @return void
     */
    public function setSubjectValue($value);
}
