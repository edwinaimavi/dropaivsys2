<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //Roles creados por defecto
        $role1 = Role::Create(['name' => 'Administrador']);
     /*    $role2 = Role::Create(['name' => 'vendedor']); */
        $role2 = Role::Create(['name' => 'Vendedor']);


        Permission::create(['name' => 'admin.users.index','description'=>'Ver los Usuarios'])->syncRoles([$role1]);
        Permission::create(['name' => 'admin.users.store','description'=>'Crear Usuarios'])->syncRoles([$role1]);
        Permission::create(['name' => 'admin.users.update','description'=>'Actualizar Usuarios'])->syncRoles([$role1]);
        Permission::create(['name' => 'admin.users.destroy','description'=>'Eliminar Usuarios'])->syncRoles([$role1]);

        Permission::create(['name' => 'admin.roles.index','description'=>'Ver los Roles'])->syncRoles([$role1]);
        Permission::create(['name' => 'admin.roles.store','description'=>'Crear Roles'])->syncRoles([$role1]);
        Permission::create(['name' => 'admin.roles.update','description'=>'Actualizar Roles'])->syncRoles([$role1]);
        Permission::create(['name' => 'admin.roles.destroy','description'=>'Eliminar Roles'])->syncRoles([$role1]);

        Permission::create(['name' => 'admin.customers.index','description'=>'Ver los Clientes'])->syncRoles([$role1]);
        Permission::create(['name' => 'admin.customers.store','description'=>'Crear Clientes'])->syncRoles([$role1]);
        Permission::create(['name' => 'admin.customers.update','description'=>'Actualizar Clientes'])->syncRoles([$role1]);
        Permission::create(['name' => 'admin.customers.destroy','description'=>'Eliminar Clientes'])->syncRoles([$role1]);
    }
}
