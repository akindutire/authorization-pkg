<?php

namespace Akindutire\Authorization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Akindutire\Authorization\Attributes\Interfaces\SubjectActionGuardInterface;
use Akindutire\Authorization\Attributes\Interfaces\SubjectValueInterface;
use Akindutire\Authorization\Support\ReflectionCacheKeyGenerator;

/**
 * Pre-warm authorization caches for optimal performance
 *
 * This command builds reflection metadata cache for all routes.
 * Run this during deployment to ensure first requests don't experience cache misses.
 *
 * Supports auto-invalidation: When enabled, cache keys include a hash of attribute
 * configuration, ensuring cache is automatically updated when attributes change.
 *
 * Usage:
 *   php artisan permission:cache        # Warm caches
 *   php artisan permission:cache-clear  # Clear caches (see ClearPermissionCache)
 */
class WarmPermissionCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-warm permission reflection metadata caches';

    /**
     * Execute the console command.
     *
     * Parses all route controller methods and caches their attribute metadata.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Warming permission reflection caches...');

        $routes = app('router')->getRoutes();
        $cached = 0;
        $skipped = 0;

        // Use progress bar for visual feedback
        $bar = $this->output->createProgressBar(count($routes));
        $bar->start();

        foreach ($routes as $route) {
            $action = $route->getActionName();

            // Skip closure routes (no reflection needed)
            if ($action === 'Closure' || !str_contains($action, '@')) {
                $skipped++;
                $bar->advance();
                continue;
            }

            try {
                [$controller, $method] = explode('@', $action);

                // Generate cache key using shared logic (includes hash when auto-invalidation enabled)
                $cacheKey = ReflectionCacheKeyGenerator::generate($controller, $method);

                // Check if already cached (skip if warm)
                if (Cache::has($cacheKey)) {
                    $bar->advance();
                    continue;
                }

                // Parse reflection and cache metadata
                $methodInstance = new \ReflectionMethod($controller, $method);

                // Find permission guard attributes
                $permissionAttribArr = $methodInstance->getAttributes(
                    SubjectActionGuardInterface::class,
                    \ReflectionAttribute::IS_INSTANCEOF
                );

                // Only cache if method has permission attributes
                if (empty($permissionAttribArr)) {
                    Cache::forever($cacheKey, null); // Cache "no attributes" to prevent re-checking
                    $bar->advance();
                    continue;
                }

                $attr = $permissionAttribArr[0];

                // Find SubjectValue parameter attribute
                $subjectValueKey = null;
                foreach ($methodInstance->getParameters() as $parameter) {
                    $paramAttrib = $parameter->getAttributes(
                        SubjectValueInterface::class,
                        \ReflectionAttribute::IS_INSTANCEOF
                    );

                    if (!empty($paramAttrib)) {
                        $subjectValueKey = $paramAttrib[0]->getArguments()[0];
                        break;
                    }
                }

                // Cache the metadata
                $metadata = [
                    'attribute_class' => $attr->getName(),
                    'attribute_args' => $attr->getArguments(),
                    'subject_value_key' => $subjectValueKey,
                ];

                Cache::forever($cacheKey, $metadata);
                $cached++;

            } catch (\ReflectionException $e) {
                // Skip routes with reflection errors (might be invalid or dynamic)
                $this->warn("Skipping {$action}: {$e->getMessage()}");
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Permission cache warmed successfully");
        $this->line("  Cached: {$cached} routes");
        $this->line("  Skipped: {$skipped} routes");

        return Command::SUCCESS;
    }
}
