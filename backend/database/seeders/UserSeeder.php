<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Super Admin
        User::updateOrCreate(
            ['email' => 'superadmin@upt.edu.mx'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password123'),
                'role' => 'superadmin',
                'email_verified_at' => now(),
            ]
        );

        // 2. Jefe de Carrera (Admin)
        User::updateOrCreate(
            ['email' => 'admin@upt.edu.mx'],
            [
                'name' => 'Jefe de Carrera',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // 3. Tutor (Master / Profesor)
        User::updateOrCreate(
            ['email' => 'tutor@upt.edu.mx'],
            [
                'name' => 'Profesor Tutor',
                'password' => Hash::make('password123'),
                'role' => 'profesor',
                'email_verified_at' => now(),
            ]
        );

        // 4. Alumno
        User::updateOrCreate(
            ['email' => 'alumno@upt.edu.mx'],
            [
                'name' => 'Alumno',
                'password' => Hash::make('password123'),
                'matricula' => '132456789',
                'role' => 'alumno',
                'email_verified_at' => now(),
            ]
        );
    }
}