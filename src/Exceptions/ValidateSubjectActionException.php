<?php

namespace Akindutire\Authorization\Exceptions;

/**
 * Exception thrown when a subject fails authorization validation
 *
 * This exception is thrown by the ValidateSubjectAction middleware
 * when a subject does not have the required permissions
 */
class ValidateSubjectActionException extends \Exception
{
}
