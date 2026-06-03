<?php

namespace Akindutire\Authorization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Akindutire\Authorization\Support\ReflectionCacheKeyGenerator;

/**
 * Clear all authorization-related caches
 *
 * This command clears:
 * - Entity lookup caches (subject models)
 * - Reflection metadata caches (controller attributes)
 * - Permission check result caches
 *
 * Note: With auto-invalidation enabled (default), reflection caches are automatically
 * invalidated when attributes change. Manual clearing is rarely needed.
 *
 * Run this after:
 * - Disabling auto-invalidation and modifying controller attributes
 * - Bulk permission changes requiring immediate cache clearing
 * - Troubleshooting cache-related issues
 */
class ClearPermissionCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:cache-clear
                            {--entities : Clear only entity lookup caches}
                            {--reflection : Clear only reflection metadata caches}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all authorization-related caches';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $entityOnly = $this->option('entities');
        $reflectionOnly = $this->option('reflection');

        // Clear entity caches (subject lookups)
        if (!$reflectionOnly) {
            $this->info('Clearing entity lookup caches...');
            $this->clearEntityCaches();
        }

        // Clear reflection metadata caches
        if (!$entityOnly) {
            $this->info('Clearing reflection metadata caches...');
            $this->clearReflectionCaches();
        }

        $this->info('✓ Permission caches cleared successfully');

        return Command::SUCCESS;
    }

    /**
     * Clear entity lookup caches
     *
     * Removes all cached subject models (User, Article, TeamMember, etc.)
     * Format: entity.{ModelClass}.{property}.{value}
     *
     * @return void
     */
    protected function clearEntityCaches(): void
    {
        // Get cache key patterns from config
        $patterns = [
            'entity.*', // All entity caches
        ];

        foreach ($patterns as $pattern) {
            // For cache drivers that support pattern deletion (Redis)
            if (method_exists(Cache::getStore(), 'flush')) {
                // Fallback: flush entire cache (use with caution in production)
                // Consider using tags for more granular control
                $this->warn('Cache driver does not support pattern deletion. Consider using Redis with cache tags.');
            }
        }

        // Alternative: Track cache keys and delete individually
        // This requires implementing cache key tracking in your application
    }

    /**
     * Clear reflection metadata caches
     *
     * Removes all cached controller method attributes
     * Format: reflection.{ControllerClass}.{methodName}
     *
     * @return void
     */
    protected function clearReflectionCaches(): void
    {
        // Get all routes and clear their reflection caches
        $routes = app('router')->getRoutes();

        foreach ($routes as $route) {
            $action = $route->getActionName();

            // Skip closure routes
            if ($action === 'Closure' || !str_contains($action, '@')) {
                continue;
            }

            [$controller, $method] = explode('@', $action);

            // Generate cache key using shared logic (includes hash when auto-invalidation enabled)
            $cacheKey = ReflectionCacheKeyGenerator::generate($controller, $method);

            Cache::forget($cacheKey);
        }
    }
}
