<?php

namespace Akindutire\Authorization\Support;

use Akindutire\Authorization\Attributes\Interfaces\SubjectActionGuardInterface;
use Akindutire\Authorization\Attributes\Interfaces\SubjectValueInterface;

/**
 * Generates consistent cache keys for reflection metadata
 *
 * This class ensures both the middleware and cache warming command
 * use identical cache key generation logic, including auto-invalidation hashes.
 */
class ReflectionCacheKeyGenerator
{
    /**
     * Generate cache key for a controller method's reflection metadata
     *
     * When auto_invalidate_reflection_cache is enabled, includes a hash of attribute configuration.
     * This allows automatic cache invalidation when attributes change.
     *
     * @param string $controller Fully qualified controller class name
     * @param string $method Method name
     * @return string Cache key for this controller method
     * @throws \ReflectionException
     */
    public static function generate(string $controller, string $method): string
    {
        $normalizedController = str_replace('\\', '.', $controller);

        // Check if auto-invalidation is enabled
        if (config('akindutire-authorization.auto_invalidate_reflection_cache', true)) {
            // Include hash to detect attribute changes
            $hash = self::generateAttributeHash($controller, $method);
            return sprintf('reflection.%s.%s.%s', $normalizedController, $method, $hash);
        }

        // Old behavior: cache key without hash (persists until manual clear)
        return sprintf('reflection.%s.%s', $normalizedController, $method);
    }

    /**
     * Generate a hash of the method's attribute configuration
     *
     * This hash changes whenever:
     * - Attribute parameters change (e.g., different actions, different subject class)
     * - SubjectValue parameter changes
     * - Attributes are added or removed
     *
     * This enables automatic cache invalidation without manual cache clearing.
     *
     * @param string $controller Fully qualified controller class name
     * @param string $method Method name
     * @return string Short hash of attribute configuration
     * @throws \ReflectionException
     */
    private static function generateAttributeHash(string $controller, string $method): string
    {
        $methodInstance = new \ReflectionMethod($controller, $method);

        // Build a string representation of the attribute configuration
        $configParts = [];

        // 1. Capture permission guard attributes (HasAny, HasAll, etc.)
        $permissionAttribArr = $methodInstance->getAttributes(
            SubjectActionGuardInterface::class,
            \ReflectionAttribute::IS_INSTANCEOF
        );

        foreach ($permissionAttribArr as $attr) {
            $configParts[] = $attr->getName(); // Class name
            $configParts[] = json_encode($attr->getArguments()); // Constructor arguments
        }

        // 2. Capture SubjectValue parameter attributes
        foreach ($methodInstance->getParameters() as $parameter) {
            $paramAttrib = $parameter->getAttributes(
                SubjectValueInterface::class,
                \ReflectionAttribute::IS_INSTANCEOF
            );

            foreach ($paramAttrib as $attr) {
                $configParts[] = $attr->getName();
                $configParts[] = json_encode($attr->getArguments());
            }
        }

        // 3. If no attributes found, return a special marker
        // This prevents hash collisions between different methods with no attributes
        if (empty($configParts)) {
            return 'none';
        }

        // Generate a short hash (first 8 chars of MD5 for readability)
        // Full MD5 not needed - collisions are acceptable since we also include controller+method in key
        $fullConfig = implode('|', $configParts);
        return substr(md5($fullConfig), 0, 8);
    }
}
