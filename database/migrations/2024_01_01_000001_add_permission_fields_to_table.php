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
     * Adds JSON permission columns to any table with indexes for performance.
     * JSON format provides better performance, queryability, and data integrity.
     *
     * IMPORTANT: Change $tableName to match your entity table.
     * Examples:
     *   - 'users' for User model
     *   - 'articles' for Article model
     *   - 'team_members' for TeamMember model
     *
     * Copy this migration for each table that needs permissions.
     *
     * @return void
     */
    public function up()
    {
        $tableName = 'users'; // ⚠️ CHANGE THIS to your table name

        Schema::table($tableName, function (Blueprint $table) {
            // Use JSON for structured permission storage
            // More efficient than CSV strings, enables database-level querying
            $table->json('allowed_permissions')->nullable();
            $table->json('revoked_permissions')->nullable();
        });

        // Add database-specific indexes for performance at scale
        $driver = DB::getDriverName();

        // MySQL 5.7+: Add generated column for permission hash indexing
        // Enables fast lookups when filtering by permission sets
        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE {$tableName}
                ADD COLUMN permissions_hash VARCHAR(64)
                AS (SHA2(COALESCE(allowed_permissions, '[]'), 256)) STORED
            ");
            DB::statement("ALTER TABLE {$tableName} ADD INDEX idx_permissions_hash (permissions_hash)");
        }

        // PostgreSQL 9.4+: GIN indexes for fast JSON containment queries
        // Enables queries like: WHERE allowed_permissions @> '[\"can_edit\"]'
        if ($driver === 'pgsql') {
            DB::statement("CREATE INDEX idx_{$tableName}_allowed_perms ON {$tableName} USING GIN (allowed_permissions)");
            DB::statement("CREATE INDEX idx_{$tableName}_revoked_perms ON {$tableName} USING GIN (revoked_permissions)");
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
        $tableName = 'users'; // ⚠️ CHANGE THIS to match the up() method
        $driver = DB::getDriverName();

        // Drop database-specific indexes first
        if ($driver === 'mysql') {
            // Drop generated column and its index
            DB::statement("ALTER TABLE {$tableName} DROP INDEX idx_permissions_hash");
            DB::statement("ALTER TABLE {$tableName} DROP COLUMN permissions_hash");
        }

        if ($driver === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS idx_{$tableName}_allowed_perms");
            DB::statement("DROP INDEX IF EXISTS idx_{$tableName}_revoked_perms");
        }

        // Drop the permission columns
        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn(['allowed_permissions', 'revoked_permissions']);
        });
    }
};
