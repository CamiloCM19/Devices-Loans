<?php

namespace Tests\Feature;

use App\Models\Camara;
use App\Models\Estudiante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
        $estudiante = Estudiante::create([
            'nombre' => 'Test Student Name 123',
            'nfc_id' => null,
        ]);

        $response = $this->post(route('inventory.storeStudent'), [
            'nfc_id' => 'TEST_UNK_TAG',
            'estudiante_id' => $estudiante->id,
            'alias' => 'Monitor',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('estudiantes', [
            'id' => $estudiante->id,
            'nfc_id' => 'TEST_UNK_TAG',
            'nombre' => 'Test Student Name 123',
            'alias' => 'Monitor',
        ]);

        // Assert session has user logged in
        $response->assertSessionHas('estudiante_actual');
    }

    public function test_can_register_new_camera()
    {
        $camara = Camara::create([
            'modelo' => 'Test Camera Model X',
            'nfc_id' => null,
            'estado' => 'Disponible',
        ]);

        $response = $this->post(route('inventory.storeCamera'), [
            'nfc_id' => 'TEST_UNK_TAG_CAM',
            'camara_id' => $camara->id,
            'alias' => 'Body 01',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $this->assertDatabaseHas('camaras', [
            'id' => $camara->id,
            'nfc_id' => 'TEST_UNK_TAG_CAM',
            'modelo' => 'Test Camera Model X',
            'alias' => 'Body 01',
        ]);
    }

    public function test_unknown_esp_tag_returns_absolute_and_relative_register_links()
    {
        $response = $this->postJson(route('inventory.scan.esp'), [
            'uid' => 'ESP_TAG_404',
            'source' => 'esp-rc522-1',
        ]);

        $response->assertStatus(404);
        $this->assertStringEndsWith(
            '/inventory/register/ESP_TAG_404',
            $response->json('register_url')
        );
        $response->assertJsonPath('register_path', '/inventory/register/ESP_TAG_404');
    }
}
