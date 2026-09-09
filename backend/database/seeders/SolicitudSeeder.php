<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Solicitud;
use App\Models\User;
use App\Models\Convocatoria;
use Illuminate\Support\Str;

class SolicitudSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Obtener una convocatoria existente
        $convocatoria = Convocatoria::first();

        if (!$convocatoria) {
            $this->command->error('No hay convocatorias registradas.');
            return;
        }

        // 2. Crear 60 alumnos falsos y su correspondiente solicitud
        for ($i = 1; $i <= 60; $i++) {
            // Creamos un alumno de prueba para evitar duplicar (user_id, convocatoria_id)
            $nuevoAlumno = User::create([
                'name' => "Alumno Prueba $i",
                'email' => "prueba_alumno_$i@" . Str::random(5) . ".com",
                'password' => bcrypt('password'),
                'role' => 'alumno',
                'matricula' => 'TEST' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'carrera_id' => 1, // ID de carrera existente
            ]);

            Solicitud::create([
                'user_id' => $nuevoAlumno->id,
                'convocatoria_id' => $convocatoria->id,
                'carrera_id' => $nuevoAlumno->carrera_id,
                'folio' => 'BEC-TEST-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'estado' => $i % 3 == 0 ? 'ACEPTADA' : ($i % 2 == 0 ? 'RECHAZADA' : 'PENDIENTE'),
                'porcentaje_beca' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('¡60 solicitudes y alumnos de prueba generados correctamente!');
    }
}