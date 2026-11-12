<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response()
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('property="og:image"', false)
            ->assertSee(asset('og-image.png'), false)
            ->assertSee('/favicon.svg', false)
            ->assertSee('/apple-touch-icon.png', false);
    }
}
