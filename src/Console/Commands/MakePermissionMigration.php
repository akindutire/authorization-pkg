<?php

namespace Akindutire\Authorization\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Generate a migration to add permission columns to a table
 *
 * This command creates a timestamped migration file that adds
 * allowed_permissions and revoked_permissions JSON columns to
 * the specified table, along with appropriate indexes.
 *
 * Usage:
 *   php artisan make:permission-migration users
 *   php artisan make:permission-migration articles
 *   php artisan make:permission-migration team_members
 */
class MakePermissionMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:permission-migration {table : The name of the table to add permissions to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a migration to add permission columns to a table';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $table = $this->argument('table');

        // Validate table name
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table)) {
            $this->error('Invalid table name. Use lowercase letters, numbers, and underscores only.');
            return Command::FAILURE;
        }

        // Generate migration name
        $migrationName = 'add_permission_fields_to_' . $table . '_table';
        $className = Str::studly($migrationName);

        // Generate timestamp
        $timestamp = date('Y_m_d_His');
        $filename = $timestamp . '_' . $migrationName . '.php';

        // Get migrations path
        $migrationsPath = database_path('migrations');
        $filepath = $migrationsPath . '/' . $filename;

        // Check if migration already exists
        $existingMigrations = glob($migrationsPath . '/*_' . $migrationName . '.php');
        if (!empty($existingMigrations)) {
            $this->error("Migration already exists: " . basename($existingMigrations[0]));
            return Command::FAILURE;
        }

        // Generate migration content
        $stub = $this->getStub();
        $content = str_replace(
            ['{{className}}', '{{table}}'],
            [$className, $table],
            $stub
        );

        // Write migration file
        file_put_contents($filepath, $content);

        $this->info("Migration created successfully: {$filename}");
        $this->line("\nNext steps:");
        $this->line("  1. Review the migration: database/migrations/{$filename}");
        $this->line("  2. Run: php artisan migrate");

        return Command::SUCCESS;
    }

    /**
     * Get the migration stub content
     *
     * @return string
     */
    protected function getStub(): string
    {
        return <<<'STUB'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds JSON permission columns to the {{table}} table with indexes for performance.
     * JSON format provides better performance, queryability, and data integrity.
     *
     * @return void
     */
    public function up()
    {
        $allowedColumn = config('akindutire-authorization.column_names.allowed_permissions');
        $revokedColumn = config('akindutire-authorization.column_names.revoked_permissions');
        Schema::table('{{table}}', function (Blueprint $table) use ($allowedColumn, $revokedColumn) {
            // Use JSON for structured permission storage
            // More efficient than CSV strings, enables database-level querying
            $table->json($allowedColumn)->nullable();
            $table->json($revokedColumn)->nullable();
        });

        // PostgreSQL 9.4+: GIN indexes for fast JSON containment queries
        // Enables queries like: WHERE allowed_permissions @> '["can_edit"]'
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("CREATE INDEX idx_{{table}}_allowed_perms ON {{table}} USING GIN (" . $allowedColumn . ")");
            DB::statement("CREATE INDEX idx_{{table}}_revoked_perms ON {{table}} USING GIN (" . $revokedColumn . ")");
        }
    }

    /**
     * Reverse the migrations.
     *
     * Removes permission columns and associated indexes.
     *
     * @return void
     */
    public function down()
    {
        $driver = DB::getDriverName();

        $allowedColumn = config('akindutire-authorization.column_names.allowed_permissions');
        $revokedColumn = config('akindutire-authorization.column_names.revoked_permissions');
        
        // Drop PostgreSQL GIN indexes
        if ($driver === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS idx_{{table}}_allowed_perms");
            DB::statement("DROP INDEX IF EXISTS idx_{{table}}_revoked_perms");
        }

        // Drop the permission columns
        Schema::table('{{table}}', function (Blueprint $table) use ($allowedColumn, $revokedColumn) {
            $table->dropColumn([$allowedColumn, $revokedColumn]);
        });
    }
};

STUB;
    }
}
