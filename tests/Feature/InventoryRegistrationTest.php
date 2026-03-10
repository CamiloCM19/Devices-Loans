<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Estudiante;
use App\Models\Camara;

class InventoryRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_tag_redirects_to_register()
    {
        $response = $this->post(route('inventory.scan'), [
            'nfc_id' => 'TEST_UNK_TAG',
        ]);

        $response->assertRedirect(route('inventory.register', ['nfc_id' => 'TEST_UNK_TAG']));
    }

    public function test_register_page_shows_nfc_id()
    {
        $response = $this->get(route('inventory.register', ['nfc_id' => 'TEST_UNK_TAG']));

        $response->assertStatus(200);
        $response->assertSee('TEST_UNK_TAG');
        $response->assertSee('Registrar Estudiante');
        $response->assertSee('Registrar Cámara');
    }

    public function test_can_register_new_student()
    {
        $response = $this->post(route('inventory.storeStudent'), [
            'nfc_id' => 'TEST_UNK_TAG',
            'nombre' => 'Test Student Name 123',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('estudiantes', [
            'nfc_id' => 'TEST_UNK_TAG',
            'nombre' => 'Test Student Name 123',
        ]);

        // Assert session has user logged in
        $response->assertSessionHas('estudiante_actual');
    }

    public function test_can_register_new_camera()
    {
        $response = $this->post(route('inventory.storeCamera'), [
            'nfc_id' => 'TEST_UNK_TAG_CAM',
            'modelo' => 'Test Camera Model X',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('camaras', [
            'nfc_id' => 'TEST_UNK_TAG_CAM',
            'modelo' => 'Test Camera Model X',
        ]);
    }
}
