<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('inventory.index'));
    }

    public function test_workflow_guide_is_accessible(): void
    {
        $response = $this->get(route('inventory.workflow'));

        $response->assertStatus(200);
        $response->assertSee('Guia de uso');
        $response->assertSee('Orden recomendado de escaneo');
        $response->assertSee('Como se enlaza un tag');
    }
}
