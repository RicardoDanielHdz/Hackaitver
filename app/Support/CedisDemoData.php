<?php

declare(strict_types=1);

namespace App\Support;

final class CedisDemoData
{
    /**
     * Las 32 sucursales destino de entrega (clientes, no propias) en la zona metropolitana de CDMX.
     * El único CEDIS está en CDMX. Capacidad Tortón: 12 tarimas | 12,000 kg (90% = 10,800 kg) | 28.59 m³ (90% = 25.73 m³).
     *
     * @return list<array<string, mixed>>
     */
    public static function branches(): array
    {
        $catalog = [
            ['Centro Histórico', 'Centro', 5, 12, 11420.0, 26.84],
            ['Polanco', 'Poniente', 14, 12, 11180.0, 26.22],
            ['Coyoacán', 'Sur', 18, 12, 11640.0, 27.15],
            ['Iztapalapa Central', 'Oriente', 20, 9, 8640.0, 20.45],
            ['Tlalpan', 'Sur', 22, 11, 10910.0, 25.92],
            ['Azcapotzalco', 'Norte', 15, 8, 7520.0, 18.10],
            ['Gustavo A. Madero', 'Norte', 14, 10, 9450.0, 22.30],
            ['Xochimilco', 'Sur', 28, 7, 6480.0, 15.60],
            ['Milpa Alta', 'Sur', 45, 6, 5610.0, 13.40],
            ['Tláhuac', 'Oriente', 35, 12, 11310.0, 26.48],
            ['Naucalpan', 'Poniente', 20, 5, 4690.0, 11.25],
            ['Tlalnepantla', 'Norte', 18, 8, 7390.0, 17.85],
            ['Ecatepec', 'Norte', 28, 9, 8420.0, 19.90],
            ['Nezahualcóyotl', 'Oriente', 22, 4, 3780.0, 9.10],
            ['Chalco', 'Oriente', 45, 7, 6590.0, 15.80],
            ['Texcoco', 'Oriente', 38, 6, 5540.0, 13.20],
            ['Chimalhuacán', 'Oriente', 32, 5, 4720.0, 11.40],
            ['Cuautitlán Izcalli', 'Norte', 32, 8, 7610.0, 18.20],
            ['Tultitlán', 'Norte', 30, 3, 2840.0, 6.85],
            ['Coacalco', 'Norte', 30, 10, 9620.0, 22.90],
            ['Atizapán de Zaragoza', 'Poniente', 30, 6, 5680.0, 13.65],
            ['Huixquilucan', 'Poniente', 25, 4, 3690.0, 8.95],
            ['Lerma', 'Poniente', 50, 9, 8510.0, 20.15],
            ['Ixtapaluca', 'Oriente', 38, 5, 4610.0, 11.05],
            ['La Paz', 'Oriente', 28, 7, 6520.0, 15.55],
            ['Tecámac', 'Norte', 40, 6, 5580.0, 13.30],
            ['Zumpango', 'Norte', 55, 4, 3720.0, 8.85],
            ['Tizayuca', 'Norte', 45, 5, 4680.0, 11.15],
            ['Huehuetoca', 'Norte', 50, 8, 7490.0, 17.95],
            ['Tepotzotlán', 'Norte', 40, 3, 2790.0, 6.70],
            ['Valle de Chalco', 'Oriente', 40, 6, 5640.0, 13.50],
            ['Santa Fe', 'Poniente', 18, 10, 9880.0, 23.40],
        ];

        $branches = [];
        foreach ($catalog as $idx => [$name, $zone, $km, $pallets, $weightKg, $volumeM3]) {
            $num = $idx + 1;
            $code = sprintf('SUC-%02d', $num);
            $rack = sprintf('RACK-%02d', $num);
            $weightPct = round(($weightKg / 12000.0) * 100, 1);
            $volumePct = round(($volumeM3 / 28.59) * 100, 1);
            $rackPct = round(($pallets / 32.0) * 100, 1);
            $readyForTorton = $weightPct >= 90.0 && $volumePct >= 90.0;

            // Fecha compromiso de entrega (SLA) y ventana de corte de ruta
            if ($readyForTorton || in_array($num, [5, 7, 20, 32], true)) {
                $deliveryDate = '2026-11-25';
                $deliveryLabel = 'HOY · Corte 14:00 h';
                $daysInRack = 2;
                $slaPriority = $readyForTorton ? 1 : 2; // 1 = Hoy + >=90%, 2 = Hoy + <90% (Urgente por fecha)
                $slaStatus = $readyForTorton ? 'SALIDA HOY (≥90%)' : 'URGENTE POR FECHA (<90%)';
            } elseif ($weightPct >= 60.0) {
                $deliveryDate = '2026-11-26';
                $deliveryLabel = 'MAÑANA · Corte 09:00 h';
                $daysInRack = 1;
                $slaPriority = 3;
                $slaStatus = 'PROGRAMADA MAÑANA';
            } else {
                $deliveryDate = '2026-11-27';
                $deliveryLabel = '27/NOV · Corte 12:00 h';
                $daysInRack = 0;
                $slaPriority = 4;
                $slaStatus = 'EN CONSOLIDACIÓN';
            }

            $branches[] = [
                'number' => $num,
                'code' => $code,
                'name' => $name,
                'zone' => $zone,
                'distance_km' => $km,
                'rack_code' => $rack,
                'pallets' => $pallets,
                'max_rack_pallets' => 32,
                'rack_pct' => $rackPct,
                'weight_kg' => $weightKg,
                'volume_m3' => $volumeM3,
                'weight_pct' => $weightPct,
                'volume_pct' => $volumePct,
                'missing_weight_kg' => max(0.0, round(10800.0 - $weightKg, 1)),
                'missing_volume_m3' => max(0.0, round(25.73 - $volumeM3, 2)),
                'ready_for_torton' => $readyForTorton,
                'delivery_date' => $deliveryDate,
                'delivery_label' => $deliveryLabel,
                'days_in_rack' => $daysInRack,
                'sla_priority' => $slaPriority,
                'sla_status' => $slaStatus,
            ];
        }

        return $branches;
    }

    /**
     * Estado operativo de los 4 andenes de recibo (R1-R4) y 3 de embarque (E1-E3).
     *
     * @return array<string, list<array<string, string>>>
     */
    public static function docks(): array
    {
        return [
            'inbound' => [
                ['code' => 'R1', 'status' => 'EN OPERACIÓN', 'oc' => 'OC-2026-0103', 'supplier' => 'Alimentos del Golfo', 'slot' => '09:00 – 11:00', 'progress' => '28/32 bultos'],
                ['code' => 'R2', 'status' => 'EN OPERACIÓN', 'oc' => 'OC-2026-0104', 'supplier' => 'Distribuidora Metropolitana', 'slot' => '10:00 – 12:00', 'progress' => '18/24 bultos'],
                ['code' => 'R3', 'status' => 'PROGRAMADO', 'oc' => 'OC-2026-0106', 'supplier' => 'Empaques Olmeca', 'slot' => '12:00 – 13:30', 'progress' => 'Próximo arribo'],
                ['code' => 'R4', 'status' => 'DISPONIBLE', 'oc' => '—', 'supplier' => 'Ventana abierta', 'slot' => '11:00 – 18:00', 'progress' => 'Sin asignación'],
            ],
            'outbound' => [
                ['code' => 'E1', 'status' => 'CARGANDO', 'branch' => 'SUC-01 Centro Histórico', 'unit' => 'Tortón #04 · 55-XA-2', 'fill' => '95.2% Peso · 93.9% Vol'],
                ['code' => 'E2', 'status' => 'CARGANDO', 'branch' => 'SUC-03 Coyoacán', 'unit' => 'Tortón #09 · 71-VR-8', 'fill' => '97.0% Peso · 95.0% Vol'],
                ['code' => 'E3', 'status' => 'DISPONIBLE', 'branch' => 'Listo para posicionar', 'unit' => 'Andén despejado', 'fill' => 'Esperando liberación ≥90%'],
            ],
        ];
    }

    /**
     * Órdenes de compra activas para Gerencia/Compras y Portal Proveedor.
     *
     * @return list<array<string, mixed>>
     */
    public static function purchaseOrders(): array
    {
        return [
            [
                'oc' => 'OC-2026-0103',
                'supplier' => 'Alimentos del Golfo S.A. de C.V.',
                'rfc' => 'AGO880315TR1',
                'invoice' => 'FAC-AG-77120',
                'branches' => 'SUC-01, SUC-05, SUC-10',
                'requested_pieces' => 640,
                'received_pieces' => 560,
                'dock_slot' => 'R1 · 09:00–11:00',
                'status' => 'EN RECEPCIÓN',
            ],
            [
                'oc' => 'OC-2026-0104',
                'supplier' => 'Distribuidora Metropolitana S.A.',
                'rfc' => 'DVE940812KM3',
                'invoice' => 'FAC-DV-98412',
                'branches' => 'SUC-01, SUC-02, SUC-03',
                'requested_pieces' => 480,
                'received_pieces' => 360,
                'dock_slot' => 'R2 · 10:00–12:00',
                'status' => 'EN RECEPCIÓN',
            ],
            [
                'oc' => 'OC-2026-0105',
                'supplier' => 'Distribuidora Metropolitana S.A.',
                'rfc' => 'DVE940812KM3',
                'invoice' => 'Pendiente de registro',
                'branches' => 'SUC-04, SUC-07, SUC-20',
                'requested_pieces' => 540,
                'received_pieces' => 0,
                'dock_slot' => 'Sin agendar',
                'status' => 'EMITIDA',
            ],
            [
                'oc' => 'OC-2026-0106',
                'supplier' => 'Empaques y Plásticos Olmeca',
                'rfc' => 'EPO010522PL9',
                'invoice' => 'FAC-OL-44019',
                'branches' => 'SUC-06, SUC-09, SUC-15',
                'requested_pieces' => 320,
                'received_pieces' => 0,
                'dock_slot' => 'R3 · 12:00–13:30',
                'status' => 'AGENDADA',
            ],
            [
                'oc' => 'OC-2026-0102',
                'supplier' => 'Abarrotera Nacional del Sureste',
                'rfc' => 'ANS991104QW2',
                'invoice' => 'FAC-AN-30218',
                'branches' => 'SUC-02, SUC-03, SUC-32',
                'requested_pieces' => 720,
                'received_pieces' => 720,
                'dock_slot' => 'R1 · 08:00–09:00',
                'status' => 'COMPLETA',
            ],
        ];
    }

    /**
     * Bitácora auditable de incidencias detectadas en recepción.
     *
     * @return list<array<string, string>>
     */
    public static function incidents(): array
    {
        return [
            [
                'time' => '10:18',
                'code' => 'INC-2026-041',
                'type' => 'EXCEDENTE DE PIEZAS',
                'oc' => 'OC-2026-0104',
                'package' => 'BLT-0104-025',
                'detail' => 'SKU-ACE-900 supera las 192 pzas solicitadas para SUC-01. Bulto no recibido.',
                'operator' => 'Juan Pérez Gómez (R2)',
            ],
            [
                'time' => '09:42',
                'code' => 'INC-2026-040',
                'type' => 'SKU NO SOLICITADO',
                'oc' => 'OC-2026-0103',
                'package' => 'BLT-0103-019',
                'detail' => 'Etiqueta declara SKU-JAB-400 ausente en la orden de compra. Rechazado en andén.',
                'operator' => 'Juan Pérez Gómez (R1)',
            ],
            [
                'time' => '08:35',
                'code' => 'INC-2026-039',
                'type' => 'NORMA DE TARIMA',
                'oc' => 'OC-2026-0102',
                'package' => 'TAR-EXT-09',
                'detail' => 'Tarima rebasaba 1.85 m (1.94 m medidos). Se reacomodó y emplayó antes de ingreso.',
                'operator' => 'Ing. Carlos Mendoza',
            ],
        ];
    }

    /**
     * Cotización real en MXN dimensionada para la bodega de 100×40×9 m y operación diaria.
     *
     * @return array<string, mixed>
     */
    public static function quotation(): array
    {
        return [
            'capex_items' => [
                [
                    'category' => 'Infraestructura Wi-Fi Industrial',
                    'spec' => '6 APs Ubiquiti UniFi U6 Mesh Pro IP67 + Switch Pro 16 PoE + 450m cable Cat6A blindado F/UTP (cobertura 4,000 m² entre racks metálicos de 6 m)',
                    'qty' => '1 lote (6 APs)',
                    'unit_mxn' => 68400,
                    'subtotal_mxn' => 68400,
                ],
                [
                    'category' => 'Terminales Portátiles (Handhelds)',
                    'spec' => 'Zebra TC22 Android 14 Uso Rudo, Imager 2D QR SE4710, caída 1.5 m MIL-STD-810H, IP67 + cunas de carga (4 recibo, 3 embarque/racks, 1 respaldo)',
                    'qty' => '8 equipos',
                    'unit_mxn' => 15850,
                    'subtotal_mxn' => 126800,
                ],
                [
                    'category' => 'Impresión Láser de Embarques',
                    'spec' => 'Brother Empresarial HL-L5210DN monocromática 48 ppm, dúplex, Ethernet Gigabit (caseta embarques + respaldo)',
                    'qty' => '2 equipos',
                    'unit_mxn' => 6490,
                    'subtotal_mxn' => 12980,
                ],
                [
                    'category' => 'Servidor Local / Puesta en Marcha',
                    'spec' => 'Nodo local/nube híbrida, configuración VLAN industrial, montaje a 7.5 m y capacitación a operadores y proveedores',
                    'qty' => '1 servicio',
                    'unit_mxn' => 24500,
                    'subtotal_mxn' => 24500,
                ],
            ],
            'opex_items' => [
                [
                    'concept' => 'Papel Bond Carta 75g (95% blancura QR)',
                    'calculation' => '20 embarques/día × 26 días = 520 embarques/mes × 6 hojas (3 tantos) + reportes = 3,500 hojas (7 paquetes)',
                    'monthly_mxn' => 826,
                ],
                [
                    'concept' => 'Tóner Alto Rendimiento + Tambor Láser',
                    'calculation' => 'Brother TN920XL (6,000 págs. a $1,890 MXN → $0.315/pág.) + prorrateo tambor DR para 3,500 págs./mes',
                    'monthly_mxn' => 1297,
                ],
                [
                    'concept' => 'Conectividad Dedicada y Nube Portal Proveedor',
                    'calculation' => 'Enlace empresarial simétrico con failover celular + respaldo diario de BD y soporte',
                    'monthly_mxn' => 4200,
                ],
            ],
            'capex_total_mxn' => 232680,
            'capex_with_iva_mxn' => 269909,
            'opex_monthly_mxn' => 6323,
        ];
    }
}
