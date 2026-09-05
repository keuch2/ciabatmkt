<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // La SPA vive en el mismo origen: sus peticiones traen Referer y Sanctum las trata
        // como stateful (sesión por cookie). Los tests replican esa condición.
        $this->withHeader('Referer', rtrim(config('app.url'), '/').'/');
    }
}
