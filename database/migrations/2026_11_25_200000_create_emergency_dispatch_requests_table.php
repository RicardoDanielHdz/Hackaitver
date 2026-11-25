<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_dispatch_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('folio', 32)->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('dock_code', 8);
            $table->string('unit_plate', 24);
            $table->string('driver_name', 120);
            $table->unsignedTinyInteger('estimated_pallets')->default(1);
            $table->decimal('estimated_weight_kg', 10, 2)->default(0);
            $table->decimal('weight_pct', 5, 1)->default(0);
            $table->decimal('estimated_volume_m3', 8, 2)->default(0);
            $table->decimal('volume_pct', 5, 1)->default(0);
            $table->string('emergency_reason', 255);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('manager_notes', 255)->nullable();
            $table->foreignId('outbound_order_id')->nullable()->constrained('outbound_orders')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_dispatch_requests');
    }
};
