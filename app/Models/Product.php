<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'catalog_order',
        'sku',
        'name',
        'category',
        'commercial_presentation',
        'unit_weight_g',
        'unit_length_cm',
        'unit_width_cm',
        'unit_height_cm',
        'unit_volume_cm3',
        'master_factor',
        'master_weight_kg',
        'master_length_cm',
        'master_width_cm',
        'master_height_cm',
        'master_volume_m3',
        'master_density_kg_m3',
        'ect_rating',
        'max_layers_ect',
        'max_layers_operational',
        'stow_zone',
        'zone_priority',
        'risk_profile',
        'is_liquid',
        'is_hygroscopic',
        'is_temp_sensitive',
        'is_crushable',
        'is_high_density_metal',
        'orientation_rule',
        'nom036_level',
        'handling_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'catalog_order' => 'integer',
            'unit_weight_g' => 'float',
            'unit_length_cm' => 'float',
            'unit_width_cm' => 'float',
            'unit_height_cm' => 'float',
            'unit_volume_cm3' => 'float',
            'master_factor' => 'integer',
            'master_weight_kg' => 'float',
            'master_length_cm' => 'float',
            'master_width_cm' => 'float',
            'master_height_cm' => 'float',
            'master_volume_m3' => 'float',
            'master_density_kg_m3' => 'integer',
            'ect_rating' => 'integer',
            'max_layers_ect' => 'integer',
            'max_layers_operational' => 'integer',
            'zone_priority' => 'integer',
            'is_liquid' => 'boolean',
            'is_hygroscopic' => 'boolean',
            'is_temp_sensitive' => 'boolean',
            'is_crushable' => 'boolean',
            'is_high_density_metal' => 'boolean',
        ];
    }

    /**
     * Calcula cuántas cajas máster caben en una cama sobre tarima estándar de 112 × 115 cm.
     */
    public function boxesPerLayer(): int
    {
        $length = max(1.0, (float) $this->master_length_cm);
        $width = max(1.0, (float) $this->master_width_cm);

        $orientationA = (int) floor(112.0 / $length) * (int) floor(115.0 / $width);
        $orientationB = (int) floor(112.0 / $width) * (int) floor(115.0 / $length);

        return max(1, $orientationA, $orientationB);
    }

    /**
     * Devuelve el mensaje ergonómico conforme a la NOM-036-1-STPS-2018.
     */
    public function ergonomicAlertMessage(): ?string
    {
        if ($this->master_weight_kg > 25.0) {
            return sprintf(
                'BLOQUEO NOM-036 (>25 kg): %.2f kg supera tope máximo absoluto varones (25 kg). Obligatorio patín hidráulico, mesa elevadora o levantamiento en pareja masculina (máx. 33.3 kg).',
                $this->master_weight_kg
            );
        }

        if ($this->master_weight_kg > 20.0) {
            return sprintf(
                'ALERTA NOM-036 (>20 kg): %.2f kg supera límite mujeres 18–45 años y hombres >45 años (20 kg). Usar equipo auxiliar o personal autorizado.',
                $this->master_weight_kg
            );
        }

        if ($this->master_weight_kg > 15.0) {
            return sprintf(
                'PRECAUCIÓN NOM-036 (15–20 kg): %.2f kg requiere buena postura y control de cuota acumulada diaria.',
                $this->master_weight_kg
            );
        }

        return null;
    }

    /**
     * @return HasMany<PalletItem, $this>
     */
    public function palletItems(): HasMany
    {
        return $this->hasMany(PalletItem::class);
    }

    /**
     * @return HasMany<PlannedArrival, $this>
     */
    public function plannedArrivals(): HasMany
    {
        return $this->hasMany(PlannedArrival::class);
    }
}
