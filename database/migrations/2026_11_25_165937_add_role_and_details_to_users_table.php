<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('operador')->after('email'); // gerente, jefe_embarque, operador, proveedor, sucursal
            $table->string('job_title')->nullable()->after('role');
            $table->string('assigned_branch_code')->nullable()->after('job_title'); // ej. SUC-01 para receptores de sucursal
            $table->string('supplier_rfc')->nullable()->after('assigned_branch_code'); // para proveedores externos
            $table->boolean('is_active')->default(true)->after('supplier_rfc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'job_title', 'assigned_branch_code', 'supplier_rfc', 'is_active']);
        });
    }
};
