<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // tests must never call the real ML service; fake the request instead
        Http::preventStrayRequests();
        // uploads go to the private documents disk; never write real files from a test
        Storage::fake('documents');
    }
}
