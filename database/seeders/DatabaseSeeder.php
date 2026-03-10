<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear 10 Estudiantes (ST001 a ST010) - Inicialmente SIN TAG
        $estudiantes = [];
        for ($i = 1; $i <= 10; $i++) {
            $estudiantes[] = [
                'nfc_id' => null, // Esperando ser vinculado
                'nombre' => 'Estudiante ' . $i, // Podríamos poner "Estudiante ST00{$i}" si el usuario quiere códigos
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('estudiantes')->insert($estudiantes);

        // 2. Crear 22 Cámaras (CAM01 a CAM22) - Inicialmente SIN TAG
        $camaras = [];
        for ($i = 1; $i <= 22; $i++) {
            $camaras[] = [
                'nfc_id' => null, // Esperando ser vinculado
                'modelo' => 'Canon T7 - Unidad ' . $i,
                'estado' => 'Disponible',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('camaras')->insert($camaras);
    }
}
