<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable Eloquent observers during tests to prevent:
        // 1. External API calls to Campaign Monitor
        // 2. Database lock contention in SQLite
        // 3. Job dispatches during test execution
        // 4. Observer-triggered queries that cause N+1 problems
        \Illuminate\Database\Eloquent\Model::unsetEventDispatcher();
    }
}
