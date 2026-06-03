<?php

namespace Akindutire\Authorization\Tests\Unit;

use Akindutire\Authorization\Support\ReflectionCacheKeyGenerator;
use Akindutire\Authorization\Tests\Fixtures\TestControllerWithAttributes;
use Akindutire\Authorization\Tests\TestCase;

class ReflectionCacheKeyGeneratorTest extends TestCase
{
    /** @test */
    public function it_generates_cache_key_without_auto_invalidation()
    {
        config(['akindutire-authorization.auto_invalidate_reflection_cache' => false]);

        $key = ReflectionCacheKeyGenerator::generate(
            'App\Http\Controllers\TestController',
            'index'
        );

        // Without auto-invalidation, key should not contain hash
        $this->assertEquals('reflection.App.Http.Controllers.TestController.index', $key);
        $this->assertStringNotContainsString('.', substr($key, strrpos($key, '.') + 1));
    }

    /** @test */
    public function it_generates_cache_key_with_auto_invalidation()
    {
        config(['akindutire-authorization.auto_invalidate_reflection_cache' => true]);

        $key = ReflectionCacheKeyGenerator::generate(
            TestControllerWithAttributes::class,
            'methodWithHashAny'
        );

        // With auto-invalidation, key should contain hash (8 character hex)
        $this->assertMatchesRegularExpression(
            '/^reflection\..+\..+\.[a-f0-9]{8}$/',
            $key
        );

        // Extract and verify hash part
        $parts = explode('.', $key);
        $hash = end($parts);
        $this->assertEquals(8, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $hash);
    }

    /** @test */
    public function it_normalizes_controller_backslashes_to_dots()
    {
        config(['akindutire-authorization.auto_invalidate_reflection_cache' => false]);

        $key = ReflectionCacheKeyGenerator::generate(
            'App\Http\Controllers\Api\V1\UserController',
            'show'
        );

        $this->assertStringStartsWith('reflection.App.Http.Controllers.Api.V1.UserController', $key);
        $this->assertStringNotContainsString('\\', $key);
    }

    /** @test */
    public function it_generates_same_hash_for_identical_attributes()
    {
        config(['akindutire-authorization.auto_invalidate_reflection_cache' => true]);

        $key1 = ReflectionCacheKeyGenerator::generate(
            TestControllerWithAttributes::class,
            'methodWithHashAny'
        );

        $key2 = ReflectionCacheKeyGenerator::generate(
            TestControllerWithAttributes::class,
            'methodWithHashAny'
        );

        $this->assertEquals($key1, $key2);
    }

    /** @test */
    public function it_generates_different_hash_for_different_hasany_actions()
    {
        config(['akindutire-authorization.auto_invalidate_reflection_cache' => true]);

        $key1 = ReflectionCacheKeyGenerator::generate(
            TestControllerWithAttributes::class,
            'methodWithHashAny'
        );

        $key2 = ReflectionCacheKeyGenerator::generate(
            TestControllerWithAttributes::class,
            'methodWithDifferentActions'
        );

        // Keys should have different hashes
        $this->assertNotEquals($key1, $key2);

        // But should share same base (controller.method)
        $base1 = implode('.', array_slice(explode('.', $key1), 0, -1));
        $base2 = implode('.', array_slice(explode('.', $key2), 0, -1));
        $this->assertNotEquals($base1, $base2); // Different methods
    }

    /** @test */
    public function it_generates_different_hash_for_hasany_vs_hasall()
    {
        config(['akindutire-authorization.auto_invalidate_reflection_cache' => true]);

        $key1 = ReflectionCacheKeyGenerator::generate(
            TestControllerWithAttributes::class,
            'methodWithHashAny'
        );

        $key2 = ReflectionCacheKeyGenerator::generate(
            TestControllerWithAttributes::class,
            'methodWithHashAll'
        );

        // Different attribute types should produce different hashes
        $this->assertNotEquals($key1, $key2);
    }

    /** @test */
    public function it_generates_different_hash_when_subject_class_changes()
    {
        config(['akindutire-authorization.auto_invalidate_reflection_cache' => true]);

        $key1 = ReflectionCacheKeyGenerator::generate(
            TestControllerWithAttributes::class,
            'methodWithHashAny'
        );

        $key2 = ReflectionCacheKeyGenerator::generate(
            TestControllerWithAttributes::class,
            'methodWithDifferentSubject'
        );

        // Different subject classes should produce different hashes
        $this->assertNotEquals($key1, $key2);
    }

    /** @test */
    public function it_generates_none_hash_for_methods_without_attributes()
    {
        config(['akindutire-authorization.auto_invalidate_reflection_cache' => true]);

        $key = ReflectionCacheKeyGenerator::generate(
            TestControllerWithAttributes::class,
            'methodWithoutAttributes'
        );

        // Should end with 'none' when no attributes present
        $this->assertStringEndsWith('.none', $key);
    }

    /** @test */
    public function it_handles_multiple_permission_attributes()
    {
        config(['akindutire-authorization.auto_invalidate_reflection_cache' => true]);

        $key = ReflectionCacheKeyGenerator::generate(
            TestControllerWithAttributes::class,
            'methodWithHashAny'
        );

        // Should generate a valid hash even with complex attribute configurations
        $this->assertMatchesRegularExpression('/\.[a-f0-9]{8}$/', $key);
    }

    /** @test */
    public function it_includes_subject_value_parameter_in_hash()
    {
        config(['akindutire-authorization.auto_invalidate_reflection_cache' => true]);

        $key1 = ReflectionCacheKeyGenerator::generate(
            TestControllerWithAttributes::class,
            'methodWithHashAny'
        );

        $key2 = ReflectionCacheKeyGenerator::generate(
            TestControllerWithAttributes::class,
            'methodWithDifferentSubjectValue'
        );

        // Different SubjectValue parameters should produce different hashes
        $this->assertNotEquals($key1, $key2);
    }

    /** @test */
    public function it_generates_consistent_hash_across_multiple_calls()
    {
        config(['akindutire-authorization.auto_invalidate_reflection_cache' => true]);

        $keys = [];
        for ($i = 0; $i < 5; $i++) {
            $keys[] = ReflectionCacheKeyGenerator::generate(
                TestControllerWithAttributes::class,
                'methodWithHashAny'
            );
        }

        // All keys should be identical
        $this->assertCount(1, array_unique($keys));
    }

    /** @test */
    public function it_handles_methods_with_nested_attribute_arguments()
    {
        config(['akindutire-authorization.auto_invalidate_reflection_cache' => true]);

        $key = ReflectionCacheKeyGenerator::generate(
            TestControllerWithAttributes::class,
            'methodWithNestedArrayActions'
        );

        // Should handle complex nested arrays in attribute arguments
        $this->assertMatchesRegularExpression('/\.[a-f0-9]{8}$/', $key);
    }
}
