<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pages render app.blade.php with @vite; without built assets
        // (CI, fresh checkout) that throws "Vite manifest not found" and the
        // request answers 500. Tests check responses, not the asset bundle.
        $this->withoutVite();
    }
}
