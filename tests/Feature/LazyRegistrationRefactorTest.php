<?php

namespace Tests\Feature;

use App\Models\Estudiante;
use App\Models\Camara;
use App\Models\LogPrestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LazyRegistrationRefactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_lazy_registration_flow_for_student()
    {
        // 1. Create unassigned student (as per seeder)
        $estudiante = Estudiante::create([
            'nombre' => 'Juan Perez',
            'nfc_id' => null, // Unassigned
        ]);

        // 2. Scan a NEW tag
        $newTag = 'TAG_JUAN_123';
        $response = $this->post(route('inventory.scan'), ['nfc_id' => $newTag]);

        // 3. Should redirect to register
        $response->assertRedirect(route('inventory.register', ['nfc_id' => $newTag]));

        // 4. Register page should show the dropdown with our student
        $response = $this->get(route('inventory.register', ['nfc_id' => $newTag]));
        $response->assertSee('Juan Perez');
        $response->assertSee($estudiante->id); // Value in dropdown

        // 5. Submit form to LINK tag to student
        $response = $this->post(route('inventory.storeStudent'), [
            'nfc_id' => $newTag,
            'estudiante_id' => $estudiante->id,
            'alias' => 'Jefe de Grupo'
        ]);

        // 6. Verify DB update
        $this->assertDatabaseHas('estudiantes', [
            'id' => $estudiante->id,
            'nfc_id' => $newTag,
            'alias' => 'Jefe de Grupo'
        ]);

        // 7. Verify Auto-Login (Session)
        $this->assertEquals(session('estudiante_actual')->id, $estudiante->id);
    }

    public function test_lazy_registration_flow_for_camera()
    {
        // 1. Create unassigned camera
        $camara = Camara::create([
            'modelo' => 'Canon T7',
            'nfc_id' => null,
            'estado' => 'Disponible'
        ]);

        // 2. Scan NEW tag
        $newTag = 'TAG_CAM_999';
        $response = $this->post(route('inventory.scan'), ['nfc_id' => $newTag]);
        $response->assertRedirect(route('inventory.register', ['nfc_id' => $newTag]));

        // 3. Link tag to camera
        $response = $this->post(route('inventory.storeCamera'), [
            'nfc_id' => $newTag,
            'camara_id' => $camara->id,
            'alias' => 'Lente 50mm'
        ]);

        // 4. Verify DB
        $this->assertDatabaseHas('camaras', [
            'id' => $camara->id,
            'nfc_id' => $newTag,
            'alias' => 'Lente 50mm'
        ]);
    }
}
