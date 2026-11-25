<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('catalog_order')->unique();
            $table->string('sku', 20)->unique();
            $table->string('name');
            $table->string('category', 60)->index();
            $table->string('commercial_presentation', 80);
            $table->decimal('unit_weight_g', 8, 2);
            $table->decimal('unit_length_cm', 6, 2);
            $table->decimal('unit_width_cm', 6, 2);
            $table->decimal('unit_height_cm', 6, 2);
            $table->decimal('unit_volume_cm3', 10, 2);
            $table->unsignedInteger('master_factor');
            $table->decimal('master_weight_kg', 6, 2);
            $table->decimal('master_length_cm', 6, 2);
            $table->decimal('master_width_cm', 6, 2);
            $table->decimal('master_height_cm', 6, 2);
            $table->decimal('master_volume_m3', 8, 4);
            $table->unsignedSmallInteger('master_density_kg_m3');
            $table->unsignedTinyInteger('ect_rating');
            $table->unsignedTinyInteger('max_layers_ect');
            $table->unsignedTinyInteger('max_layers_operational');
            $table->string('stow_zone', 40)->index();
            $table->unsignedTinyInteger('zone_priority')->index();
            $table->string('risk_profile', 40)->index();
            $table->boolean('is_liquid')->default(false);
            $table->boolean('is_hygroscopic')->default(false);
            $table->boolean('is_temp_sensitive')->default(false);
            $table->boolean('is_crushable')->default(false);
            $table->boolean('is_high_density_metal')->default(false);
            $table->string('orientation_rule', 40)->default('ESTANDAR');
            $table->string('nom036_level', 40)->default('estandar');
            $table->text('handling_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('number')->unique();
            $table->string('code', 12)->unique();
            $table->string('name');
            $table->string('zone', 60);
            $table->unsignedSmallInteger('distance_km');
            $table->string('rack_code', 16)->unique();
            $table->unsignedTinyInteger('max_rack_pallets')->default(32);
            $table->timestamps();
        });

        Schema::create('outbound_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('folio', 32)->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('status', 24)->default('dispatched')->index();
            $table->string('dock_code', 8);
            $table->string('unit_plate', 24);
            $table->string('driver_name', 120);
            $table->unsignedTinyInteger('pallet_count')->default(0);
            $table->decimal('total_weight_kg', 9, 2)->default(0);
            $table->decimal('weight_pct', 5, 1)->default(0);
            $table->decimal('total_volume_m3', 8, 3)->default(0);
            $table->decimal('volume_pct', 5, 1)->default(0);
            $table->boolean('exception_authorized')->default(false);
            $table->string('exception_reason')->nullable();
            $table->json('loading_sequence')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pallets', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('status', 24)->default('building')->index();
            $table->unsignedTinyInteger('rack_level')->nullable();
            $table->unsignedTinyInteger('rack_position')->nullable();
            $table->decimal('tare_weight_kg', 6, 2)->default(22.00);
            $table->decimal('goods_weight_kg', 8, 2)->default(0);
            $table->decimal('total_weight_kg', 8, 2)->default(22.00);
            $table->decimal('volume_m3', 8, 4)->default(0);
            $table->decimal('estimated_height_m', 5, 2)->default(0.15);
            $table->unsignedInteger('box_count')->default(0);
            $table->unsignedTinyInteger('highest_zone_priority')->default(0);
            $table->boolean('has_liquids')->default(false);
            $table->boolean('has_hygroscopic')->default(false);
            $table->string('pallet_profile', 40)->default('mixta_estratificada');
            $table->unsignedTinyInteger('wave_number')->default(1);
            $table->foreignId('outbound_order_id')->nullable()->constrained('outbound_orders')->nullOnDelete();
            $table->unsignedTinyInteger('truck_slot')->nullable();
            $table->timestamp('slotted_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['branch_id', 'rack_level', 'rack_position']);
        });

        Schema::create('pallet_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pallet_id')->constrained('pallets')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('batch_code', 40);
            $table->unsignedInteger('quantity_boxes');
            $table->unsignedTinyInteger('assigned_zone_priority');
            $table->unsignedTinyInteger('layer_index')->default(1);
            $table->decimal('total_weight_kg', 8, 2);
            $table->decimal('total_volume_m3', 8, 4);
            $table->string('orientation_instruction', 60)->default('ESTANDAR');
            $table->text('placement_reason')->nullable();
            $table->string('ergonomic_warning')->nullable();
            $table->foreignId('placed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('planned_arrivals', function (Blueprint $table): void {
            $table->id();
            $table->string('folio', 32);
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('boxes_expected');
            $table->unsignedInteger('boxes_received')->default(0);
            $table->string('dock_code', 8)->default('R2');
            $table->string('eta_window', 60)->default('Hoy 12:00–13:30');
            $table->string('supplier_name', 120)->default('Distribuidora Papelera Nacional S.A.');
            $table->string('status', 24)->default('scheduled')->index();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('operator_ergonomic_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operator_name', 120);
            $table->date('shift_date');
            $table->string('distance_band', 24)->default('under_10m');
            $table->decimal('moved_weight_kg', 9, 2)->default(0);
            $table->unsignedInteger('boxes_moved')->default(0);
            $table->unsignedInteger('assisted_moves')->default(0);
            $table->string('last_alert')->nullable();
            $table->timestamps();

            $table->unique(['operator_name', 'shift_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_ergonomic_logs');
        Schema::dropIfExists('planned_arrivals');
        Schema::dropIfExists('pallet_items');
        Schema::dropIfExists('pallets');
        Schema::dropIfExists('outbound_orders');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('products');
    }
};
