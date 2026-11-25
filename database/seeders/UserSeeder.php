<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('cedis2026');

        $users = [
            [
                'name' => 'Lic. Roberto Peña',
                'email' => 'gerente@cedis.com',
                'password' => $password,
                'role' => 'gerente',
                'job_title' => 'Gerente General y Compras CEDIS',
                'assigned_branch_code' => null,
                'supplier_rfc' => null,
            ],
            [
                'name' => 'Ing. Carlos Mendoza',
                'email' => 'embarque@cedis.com',
                'password' => $password,
                'role' => 'jefe_embarque',
                'job_title' => 'Jefe de Embarque y Tráfico',
                'assigned_branch_code' => null,
                'supplier_rfc' => null,
            ],
            [
                'name' => 'Juan Pérez Gómez',
                'email' => 'operador@cedis.com',
                'password' => $password,
                'role' => 'operador',
                'job_title' => 'Operador de Recibo y Handheld',
                'assigned_branch_code' => null,
                'supplier_rfc' => null,
            ],
            [
                'name' => 'Distribuidora Metropolitana S.A.',
                'email' => 'proveedor@cedis.com',
                'password' => $password,
                'role' => 'proveedor',
                'job_title' => 'Proveedor Externo Autorizado',
                'assigned_branch_code' => null,
                'supplier_rfc' => 'DVE940812KM3',
            ],
            [
                'name' => 'Mario Gómez Lara',
                'email' => 'sucursal@cedis.com',
                'password' => $password,
                'role' => 'sucursal',
                'job_title' => 'Receptor Sucursal 01 - Centro Histórico',
                'assigned_branch_code' => 'SUC-01',
                'supplier_rfc' => null,
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
