<?php

namespace Akindutire\Authorization\Middleware;

use Akindutire\Authorization\Attributes\Interfaces\SubjectActionGuardInterface;
use Akindutire\Authorization\Attributes\Interfaces\SubjectValueInterface;
use Akindutire\Authorization\Exceptions\ValidateSubjectActionException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Middleware to validate subject permissions using PHP attributes
 *
 * This middleware uses reflection to read method attributes and validate
 * that the subject (identified by parameter attributes) has the required permissions
 */
class ValidateSubjectAction
{
    /**
     * Handle an incoming request
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     * @throws ValidateSubjectActionException
     * @throws \ReflectionException
     */
    public function handle(Request $request, Closure $next)
    {
        $fullAction = $request->route()?->getActionName();

        // Skip for closures
        if ($fullAction !== 'Closure' && $fullAction !== null) {
            [$controller, $method] = explode('@', $fullAction);

            // Get method reflection
            $methodInstance = new \ReflectionMethod($controller, $method);

            // Find permission guard attributes (HasAny, HasAll, etc.)
            $permissionAttribArr = $methodInstance->getAttributes(
                SubjectActionGuardInterface::class,
                \ReflectionAttribute::IS_INSTANCEOF
            );

            if (count($permissionAttribArr) > 0) {
                $attr = $permissionAttribArr[0];
                $permissionAttrib = $attr->newInstance();

                $paramValue = null;

                // Loop through method parameters to find SubjectValue attribute
                foreach ($methodInstance->getParameters() as $parameter) {
                    $paramAttrib = $parameter->getAttributes(
                        SubjectValueInterface::class,
                        \ReflectionAttribute::IS_INSTANCEOF
                    );

                    if (!empty($paramAttrib)) {
                        // Get the key from SubjectValue attribute
                        $key = $paramAttrib[0]->getArguments()[0];

                        // Extract value from Request
                        // Priority: input() -> route() -> query() -> json()
                        $paramValue = $request->input($key)
                            ?? $request->route($key)
                            ?? $request->query($key)
                            ?? $request->json($key);

                        break;
                    }
                }

                // Validate that subject value was found
                if (is_null($paramValue)) {
                    throw new ValidateSubjectActionException(
                        "Subject value not found, ensure to set the subject value on the parameter using the SubjectValue attribute"
                    );
                }

                // Set the subject value on the permission attribute
                $permissionAttrib->setSubjectValue($paramValue);

                // Validate permissions
                if (!$permissionAttrib->validate()) {
                    throw new ValidateSubjectActionException(
                        "Access denied, you do not have enough permission to perform this action, contact your administrator"
                    );
                }
            }
        }

        return $next($request);
    }
}
