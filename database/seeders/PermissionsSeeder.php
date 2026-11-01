<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        // 1. Создаем роли
        $roles = [
            'admin' => 'Супер администратор',
            'manager' => 'Менеджер'
        ];

        foreach ($roles as $name => $label) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // 2. Создаем базовые права
        $permissions = ['view users', 'view roles'];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        // 3. Создаем Супер-админа
        $admin = User::firstOrCreate(
            ['email' => 'yurkoi83@gmail.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
            ]
        );
        $admin->assignRole('admin');

    }
}
