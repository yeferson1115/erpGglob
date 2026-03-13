<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Crear el rol admin si no existe
        $role = Role::firstOrCreate(['name' => 'admin']);

        // Crear un usuario con rol admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => 'Administrador',
                'password' => Hash::make('admin1234'),
            ]
        );

        // Asignar el rol
        $admin->assignRole($role);
    }
}
