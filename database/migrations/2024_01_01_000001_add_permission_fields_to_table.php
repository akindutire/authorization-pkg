<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds permission fields to any table.
     * Publish and customize the table name as needed.
     *
     * Example: For 'users' table, change $table variable to 'users'
     * Example: For 'team_members' table, change $table variable to 'team_members'
     *
     * @return void
     */
    public function up()
    {
        $table = 'users'; // Change this to your table name

        Schema::table($table, function (Blueprint $table) {
            $table->text('allowed_permissions')->nullable()->after('password');
            $table->text('revoked_permissions')->nullable()->after('allowed_permissions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $table = 'users'; // Change this to your table name

        Schema::table($table, function (Blueprint $table) {
            $table->dropColumn(['allowed_permissions', 'revoked_permissions']);
        });
    }
};
