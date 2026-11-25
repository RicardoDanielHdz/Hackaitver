<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallet_items', function (Blueprint $table): void {
            $table->string('internal_label_code', 40)->nullable()->index()->after('batch_code');
            $table->string('scanned_bulto_code', 60)->nullable()->index()->after('internal_label_code');
            $table->string('origin_supplier', 120)->nullable()->after('ergonomic_warning');
            $table->string('origin_dock', 16)->nullable()->after('origin_supplier');
            $table->string('planned_rack_coordinate', 40)->nullable()->after('origin_dock');
            $table->string('outbound_dock_target', 40)->nullable()->after('planned_rack_coordinate');
            $table->string('outbound_cutoff', 60)->nullable()->after('outbound_dock_target');
        });
    }

    public function down(): void
    {
        Schema::table('pallet_items', function (Blueprint $table): void {
            $table->dropColumn([
                'internal_label_code',
                'scanned_bulto_code',
                'origin_supplier',
                'origin_dock',
                'planned_rack_coordinate',
                'outbound_dock_target',
                'outbound_cutoff',
            ]);
        });
    }
};
