<?php

namespace Akindutire\Authorization\Attributes\Interfaces;

/**
 * Interface for marking method parameters that contain the subject value
 *
 * Used by ValidateSubjectAction middleware to identify which parameter
 * contains the identifier for looking up the subject model
 */
interface SubjectValueInterface
{
    /**
     * Get the value/key used to extract the subject identifier
     *
     * @return mixed
     */
    public function getValue();
}
