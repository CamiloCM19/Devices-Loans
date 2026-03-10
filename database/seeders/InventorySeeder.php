<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Estudiante;
use App\Models\Camara;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar tablas para evitar duplicados en seed
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Estudiante::truncate();
        Camara::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 10 Estudiantes
        $estudiantes = [
            ['nfc_id' => 'ST001', 'nombre' => 'Ana Garcia'],
            ['nfc_id' => 'ST002', 'nombre' => 'Carlos Perez'],
            ['nfc_id' => 'ST003', 'nombre' => 'Maria Lopez'],
            ['nfc_id' => 'ST004', 'nombre' => 'Juan Diaz'],
            ['nfc_id' => 'ST005', 'nombre' => 'Sofia Rodriguez'],
            ['nfc_id' => 'ST006', 'nombre' => 'Luis Martinez'],
            ['nfc_id' => 'ST007', 'nombre' => 'Lucia Sanchez'],
            ['nfc_id' => 'ST008', 'nombre' => 'Diego Torres'],
            ['nfc_id' => 'ST009', 'nombre' => 'Elena Ramirez'],
            ['nfc_id' => 'ST010', 'nombre' => 'Javier Gonzalez'],
        ];

        foreach ($estudiantes as $est) {
            Estudiante::create($est);
        }

        // 22 Cámaras
        for ($i = 1; $i <= 22; $i++) {
            $num = str_pad($i, 2, '0', STR_PAD_LEFT);
            Camara::create([
                'nfc_id' => "CAM{$num}",
                'modelo' => "Canon T7 - Unit {$num}",
                'estado' => 'Disponible'
            ]);
        }
    }
}
