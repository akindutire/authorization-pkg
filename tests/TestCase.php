<?php

namespace Akindutire\Authorization\Tests;

use Akindutire\Authorization\AuthorizationServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    /**
     * Get package providers.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            AuthorizationServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Setup authorization config
        $app['config']->set('akindutire-authorization.abilities', [
            'owner' => ['can_update', 'can_delete', 'can_create', 'can_view'],
            'admin' => ['can_update', 'can_create', 'can_view'],
            'member' => ['can_view'],
        ]);
    }

    /**
     * Set up the database for testing.
     *
     * @return void
     */
    protected function setUpDatabase()
    {
        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->text('allowed_permissions')->nullable()->default('[]');
            $table->text('revoked_permissions')->nullable()->default('[]');
            $table->timestamps();
        });

        Schema::create('test_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('role');
            $table->text('allowed_permissions')->nullable()->default('[]');
            $table->text('revoked_permissions')->nullable()->default('[]');
            $table->timestamps();
        });
    }

    /**
     * Clean up after tests.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Schema::dropIfExists('test_users');
        Schema::dropIfExists('test_team_members');

        parent::tearDown();
    }
}
