<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class StationeryCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $hygroscopicSkus = [
            'PAP-001', 'PAP-002', 'PAP-003', 'PAP-004', 'PAP-005',
            'PAP-006', 'PAP-007', 'PAP-008', 'PAP-009', 'PAP-010',
            'ARC-009', 'ARC-013', 'ARC-014',
        ];

        $liquidSkus = [
            'ESC-003', 'ESC-007', 'ESC-009', 'OFI-002', 'OFI-003',
            'ADH-007', 'ADH-009', 'ADH-011', 'ADH-012', 'ART-007',
        ];

        $horizontalInkSkus = ['ESC-003', 'ESC-007'];

        $tempSensitiveSkus = [
            'ADH-001', 'ADH-002', 'ADH-003', 'ADH-004', 'ADH-005', 'ADH-006',
            'ADH-008', 'ADH-010', 'ART-003', 'ART-005', 'OFI-004', 'ESC-002', 'ESC-005',
        ];

        $crushableSkus = [
            'COR-003', 'ESC-008', 'OFI-005', 'COR-008', 'ART-002',
            'ARC-008', 'ARC-010', 'ARC-011',
        ];

        $highDensityMetalSkus = [
            'COR-001', 'ARC-001', 'ARC-002', 'ARC-003', 'ARC-004', 'ARC-006', 'ARC-007',
            'OFI-006', 'OFI-007', 'OFI-009', 'ESC-001', 'ESC-010', 'ART-001', 'ART-006',
        ];

        /**
         * Catálogo maestro auditado de 72 productos de papelería en México (orden ascendente estricto por peso unitario).
         * Campos: [order, sku, name, category, presentation, unit_g, uL, uW, uH, uVol, factor, mKg, mL, mW, mH, mVol, mDens, ect, maxEct, maxOp, zone]
         *
         * @var list<array{int, string, string, string, string, float, float, float, float, float, int, float, float, float, float, float, int, int, int, int, string}>
         */
        $rows = [
            [1, 'ESC-001', 'Lápiz de grafito hexagonal #2 (HB) con goma (Dixon / Mirado)', 'Escritura', '1 pieza', 5.5, 19.0, 0.75, 0.75, 10.7, 1440, 8.57, 39.0, 21.0, 18.0, 0.0147, 581, 32, 6, 6, 'Intermedio / Estándar'],
            [2, 'ESC-002', 'Bolígrafo punto mediano 1.0 mm (BIC Cristal Dura+)', 'Escritura', '1 pieza', 6.0, 15.0, 0.85, 0.85, 10.8, 1200, 7.92, 34.0, 26.0, 18.0, 0.0159, 498, 32, 6, 6, 'Intermedio / Estándar'],
            [3, 'COR-001', 'Sacapuntas metálico sencillo cuña aluminio (Maped / Barrilito)', 'Corte y medición', '1 pieza', 9.0, 2.5, 1.5, 1.1, 4.1, 720, 6.96, 28.0, 22.0, 16.0, 0.0099, 706, 32, 6, 6, 'Intermedio / Estándar'],
            [4, 'ESC-003', 'Marcador permanente punta fina (Sharpie / Esterbrook)', 'Escritura', '1 pieza', 10.0, 13.8, 1.2, 1.2, 19.9, 576, 6.38, 36.0, 25.0, 19.0, 0.0171, 373, 32, 6, 6, 'Intermedio / Estándar'],
            [5, 'ESC-004', 'Portaminas plástico 0.5 / 0.7 mm (BIC Matic / Zebra)', 'Escritura', '1 pieza', 10.5, 14.5, 1.1, 1.1, 17.5, 576, 6.65, 35.0, 25.0, 18.0, 0.0158, 422, 32, 6, 6, 'Intermedio / Estándar'],
            [6, 'ESC-005', 'Bolígrafo tinta gel retráctil 0.7 mm (Paper Mate / Pentel)', 'Escritura', '1 pieza', 12.0, 14.5, 1.2, 1.2, 20.9, 576, 7.55, 36.0, 26.0, 19.0, 0.0178, 425, 32, 6, 6, 'Intermedio / Estándar'],
            [7, 'ESC-006', 'Marcatextos fluorescente punta cincel (Azor Visión Plus / Stabilo)', 'Escritura', '1 pieza', 16.0, 11.5, 2.5, 1.5, 43.1, 480, 8.30, 38.0, 28.0, 22.0, 0.0234, 355, 32, 5, 5, 'Intermedio / Estándar'],
            [8, 'COR-002', 'Goma de borrar blanca plástica mediana WS30 (Pelikan / Factis)', 'Corte y medición', '1 pieza', 18.0, 4.3, 2.2, 1.2, 11.4, 600, 11.38, 32.0, 24.0, 20.0, 0.0154, 741, 32, 6, 6, 'Base / Pesado'],
            [9, 'ESC-007', 'Plumón para pizarrón blanco punta cincel (Azor Magistral)', 'Escritura', '1 pieza', 20.0, 13.5, 1.8, 1.8, 43.7, 480, 10.28, 40.0, 28.0, 24.0, 0.0269, 382, 32, 5, 5, 'Intermedio / Estándar'],
            [10, 'ADH-001', 'Lápiz adhesivo escolar chico 11 g neto (Pritt Henkel)', 'Adhesivos y cintas', '1 pieza (11 g)', 22.0, 8.5, 2.2, 2.2, 41.1, 288, 6.86, 32.0, 24.0, 20.0, 0.0154, 446, 29, 5, 5, 'Intermedio / Estándar'],
            [11, 'COR-003', 'Sacapuntas escolar plástico con depósito doble (Maped / Barrilito)', 'Corte y medición', '1 pieza', 23.0, 5.2, 4.2, 2.8, 61.2, 288, 7.17, 42.0, 30.0, 24.0, 0.0302, 237, 26, 4, 4, 'Superior / Ligero'],
            [12, 'OFI-001', 'Quitagrapas metálico tipo pinza (Mae / Barrilito)', 'Suministros de oficina', '1 pieza', 24.0, 5.8, 4.0, 3.8, 88.2, 288, 7.47, 38.0, 28.0, 24.0, 0.0255, 293, 29, 5, 5, 'Intermedio / Estándar'],
            [13, 'COR-004', 'Goma de borrar de migajón suave M20 (Pelikan)', 'Corte y medición', '1 pieza', 25.0, 5.5, 2.8, 1.4, 21.6, 400, 10.55, 34.0, 24.0, 20.0, 0.0163, 646, 32, 6, 6, 'Base / Pesado'],
            [14, 'ESC-008', 'Cinta correctora seca 4.2 mm × 8–12 m (BIC Wite-Out / Pelikan)', 'Escritura', '1 blíster (1 pza)', 26.0, 15.5, 8.5, 1.8, 237.2, 144, 4.28, 42.0, 32.0, 24.0, 0.0323, 133, 26, 4, 4, 'Superior / Ligero'],
            [15, 'COR-005', 'Regla de plástico rígido transparente 30 cm (Baco / Barrilito)', 'Corte y medición', '1 pieza', 28.0, 31.0, 3.5, 0.3, 32.6, 240, 7.26, 35.0, 24.0, 20.0, 0.0168, 432, 29, 5, 5, 'Intermedio / Estándar'],
            [16, 'ADH-002', 'Cinta adhesiva transparente tipo Diurex 12 mm × 33 m (Janel / Tuk)', 'Adhesivos y cintas', '1 rollo', 29.0, 6.5, 6.5, 1.2, 50.7, 288, 8.93, 34.0, 28.0, 22.0, 0.0209, 426, 29, 5, 5, 'Intermedio / Estándar'],
            [17, 'COR-006', 'Tijera escolar punta roma 5" acero inox. (Mae / Barrilito)', 'Corte y medición', '1 blíster (1 pza)', 30.0, 17.5, 7.5, 1.2, 157.5, 240, 7.80, 40.0, 30.0, 24.0, 0.0288, 271, 29, 5, 5, 'Intermedio / Estándar'],
            [18, 'ARC-001', 'Clips estándar galvanizados #2 (ACCO / Baco)', 'Archivo y organización', '1 cajilla (100 pzas)', 35.0, 6.2, 4.1, 2.0, 50.8, 200, 7.48, 28.0, 22.0, 18.0, 0.0111, 675, 32, 6, 6, 'Base / Pesado'],
            [19, 'ESC-009', 'Corrector líquido frasco 20 ml brocha/esponja (BIC Rapid / Liquid Paper)', 'Escritura', '1 frasco (20 ml)', 38.0, 3.1, 3.1, 7.9, 75.9, 144, 5.99, 30.0, 24.0, 20.0, 0.0144, 416, 29, 4, 4, 'Intermedio / Estándar'],
            [20, 'ARC-002', 'Chinchetas metálicas niqueladas (Baco / Barrilito)', 'Archivo y organización', '1 cajilla (100 pzas)', 40.0, 7.0, 5.0, 2.0, 70.0, 200, 8.48, 32.0, 24.0, 18.0, 0.0138, 613, 32, 6, 6, 'Base / Pesado'],
            [21, 'ADH-003', 'Lápiz adhesivo escolar mediano 22 g neto (Pritt Henkel)', 'Adhesivos y cintas', '1 pieza (22 g)', 42.0, 10.5, 2.7, 2.7, 76.5, 192, 8.60, 36.0, 26.0, 22.0, 0.0206, 418, 29, 5, 5, 'Intermedio / Estándar'],
            [22, 'PAP-001', 'Notas adhesivas cuadradas 3"×3" 100 hojas (3M Post-it / Janel)', 'Papel y cuadernos', '1 block (100 h)', 46.0, 7.6, 7.6, 1.1, 63.5, 192, 9.39, 34.0, 26.0, 20.0, 0.0177, 531, 32, 6, 6, 'Intermedio / Estándar'],
            [23, 'ARC-003', 'Sujetadocumentos metálicos 25 mm (Nextep / Baco)', 'Archivo y organización', '1 cajilla (12 pzas)', 48.0, 8.0, 5.2, 3.2, 133.1, 240, 12.14, 38.0, 28.0, 24.0, 0.0255, 475, 32, 6, 6, 'Base / Pesado'],
            [24, 'OFI-002', 'Cojín entintado para sello #1 (Azor / Pelikan)', 'Suministros de oficina', '1 pieza', 55.0, 11.5, 7.5, 1.8, 155.3, 120, 7.12, 36.0, 28.0, 22.0, 0.0222, 321, 29, 5, 5, 'Intermedio / Estándar'],
            [25, 'ARC-004', 'Clips estándar galvanizados #1 (Baco / ACCO)', 'Archivo y organización', '1 cajilla (100 pzas)', 60.0, 7.2, 4.8, 2.2, 76.0, 100, 6.45, 28.0, 17.5, 28.3, 0.0139, 465, 32, 6, 6, 'Intermedio / Estándar'],
            [26, 'COR-007', 'Cúter / exacto 18 mm alma metálica (Barrilito / Mae / Truper)', 'Corte y medición', '1 blíster (1 pza)', 68.0, 21.0, 7.0, 2.0, 294.0, 144, 10.37, 40.0, 30.0, 26.0, 0.0312, 332, 29, 5, 5, 'Intermedio / Estándar'],
            [27, 'ADH-004', 'Lápiz adhesivo grande / jumbo 42 g neto (Pritt Henkel)', 'Adhesivos y cintas', '1 pieza (42 g)', 70.0, 12.2, 3.2, 3.2, 124.9, 120, 8.94, 36.0, 28.0, 24.0, 0.0242, 370, 29, 5, 5, 'Intermedio / Estándar'],
            [28, 'ARC-005', 'Separadores plásticos tamaño carta 5 divisiones (Baco / Avery)', 'Archivo y organización', '1 juego (5 h)', 72.0, 29.5, 23.0, 0.3, 203.6, 120, 9.20, 32.0, 25.0, 24.0, 0.0192, 479, 29, 5, 5, 'Intermedio / Estándar'],
            [29, 'ESC-010', 'Lápices de grafito #2 caja comercial 12 pzas (Dixon / Mirado)', 'Escritura', '1 cajilla (12 pzas)', 75.0, 19.2, 4.8, 1.7, 156.7, 120, 9.58, 40.0, 28.0, 22.0, 0.0246, 389, 32, 6, 6, 'Intermedio / Estándar'],
            [30, 'ADH-005', 'Cinta adhesiva transparente 18 mm × 65 m (Janel 119 / Tuk)', 'Adhesivos y cintas', '1 rollo', 78.0, 10.2, 10.2, 1.8, 187.3, 144, 11.85, 42.0, 32.0, 26.0, 0.0349, 339, 29, 5, 5, 'Intermedio / Estándar'],
            [31, 'ESC-011', 'Bolígrafos punto mediano caja comercial 12 pzas (BIC Cristal)', 'Escritura', '1 cajilla (12 pzas)', 80.0, 15.5, 6.6, 2.5, 255.8, 120, 10.20, 38.0, 30.0, 24.0, 0.0274, 373, 32, 6, 6, 'Intermedio / Estándar'],
            [32, 'OFI-003', 'Tinta para sello base agua frasco 60 ml (Azor / Pelikan)', 'Suministros de oficina', '1 frasco (60 ml)', 85.0, 4.2, 4.2, 10.5, 185.2, 72, 6.64, 30.0, 24.0, 22.0, 0.0158, 419, 29, 4, 4, 'Intermedio / Estándar'],
            [33, 'ART-001', 'Lápices de colores largos caja 12 pzas (Norma / Maped / Prismacolor Jr.)', 'Arte y escolar', '1 caja (12 pzas)', 98.0, 20.5, 9.0, 1.0, 184.5, 96, 9.99, 42.0, 28.0, 22.0, 0.0259, 386, 32, 6, 6, 'Intermedio / Estándar'],
            [34, 'OFI-005', 'Calculadora científica escolar 240 funciones (Casio FX-82MS-2)', 'Suministros de oficina', '1 pieza en caja', 105.0, 16.2, 7.7, 1.4, 174.6, 60, 6.88, 36.0, 28.0, 22.0, 0.0222, 310, 29, 4, 4, 'Superior / Ligero'],
            [35, 'OFI-004', 'Ligas de hule natural #18 bolsa 100 g (Pelikan / Baco)', 'Suministros de oficina', '1 bolsa (100 g)', 105.0, 16.0, 10.0, 3.0, 480.0, 100, 11.06, 42.0, 32.0, 26.0, 0.0349, 317, 29, 4, 4, 'Superior / Ligero'],
            [36, 'COR-008', 'Juego de geometría escolar 5–6 pzas regla 30 cm (Baco / Maped)', 'Corte y medición', '1 estuche/bolsa', 105.0, 33.0, 14.5, 1.5, 717.8, 100, 11.18, 48.0, 36.0, 32.0, 0.0553, 202, 29, 4, 4, 'Superior / Ligero'],
            [37, 'ART-002', 'Hojas de fomy (goma EVA) tamaño carta paq. 10 h (Barrilito / Pascua)', 'Arte y escolar', '1 paq. (10 hojas)', 110.0, 28.5, 21.8, 2.0, 1242.6, 60, 7.24, 48.0, 36.0, 36.0, 0.0622, 116, 26, 3, 3, 'Superior / Ligero'],
            [38, 'ADH-006', 'Cinta masking tape uso general 24 mm × 50 m (Janel 110 / Tuk)', 'Adhesivos y cintas', '1 rollo', 122.0, 10.6, 10.6, 2.4, 269.7, 72, 9.38, 34.0, 34.0, 24.0, 0.0277, 338, 32, 5, 5, 'Intermedio / Estándar'],
            [39, 'ART-003', 'Crayones de cera estándar caja 24 pzas (Crayola)', 'Arte y escolar', '1 caja (24 pzas)', 130.0, 11.4, 7.1, 2.8, 226.6, 48, 6.76, 34.0, 26.0, 20.0, 0.0177, 382, 29, 5, 5, 'Intermedio / Estándar'],
            [40, 'ART-004', 'Acuarelas escolares 12 pastillas con pincel (Dixon Vinci / Pelikan)', 'Arte y escolar', '1 estuche (12 past.)', 130.0, 19.0, 8.5, 1.6, 258.4, 72, 9.96, 40.0, 30.0, 24.0, 0.0288, 346, 29, 5, 5, 'Intermedio / Estándar'],
            [41, 'ADH-007', 'Pegamento líquido blanco escolar 110 g neto (Resistol 850 Henkel)', 'Adhesivos y cintas', '1 frasco (110 g)', 135.0, 5.5, 3.5, 15.0, 288.8, 48, 7.00, 34.0, 24.0, 18.0, 0.0147, 477, 29, 5, 5, 'Intermedio / Estándar'],
            [42, 'ADH-008', 'Cinta de empaque transparente/canela 48 mm × 50 m (Janel / Tuk / Truper)', 'Adhesivos y cintas', '1 rollo', 140.0, 9.8, 9.8, 4.8, 461.0, 72, 10.60, 41.6, 31.8, 31.2, 0.0413, 257, 32, 5, 5, 'Intermedio / Estándar'],
            [43, 'ARC-006', 'Sujetadocumentos metálicos Jumbo 51 mm (Nextep / Baco)', 'Archivo y organización', '1 cajilla (12 pzas)', 155.0, 10.5, 5.5, 5.2, 300.3, 60, 9.88, 36.0, 28.0, 24.0, 0.0242, 408, 32, 6, 6, 'Intermedio / Estándar'],
            [44, 'PAP-002', 'Block de notas / taquigrafía 80 hojas 14×21.5 cm (Scribe / Estrella)', 'Papel y cuadernos', '1 pieza', 155.0, 21.5, 14.0, 1.0, 301.0, 80, 13.02, 44.0, 30.0, 22.0, 0.0290, 448, 32, 6, 6, 'Base / Pesado'],
            [45, 'ART-005', 'Plastilina escolar barra / marqueta 180 g neto (Pelikan / Vinci)', 'Arte y escolar', '1 barra (180 g)', 185.0, 14.0, 6.8, 1.3, 123.8, 80, 15.45, 36.0, 30.0, 20.0, 0.0216, 715, 32, 5, 5, 'Base / Pesado'],
            [46, 'ARC-007', 'Broches para archivo 8 cm hojalata (Baco B-082 / ACCO)', 'Archivo y organización', '1 cajilla (50 jgos)', 192.0, 10.5, 7.5, 3.8, 299.3, 100, 20.00, 39.3, 28.9, 21.2, 0.0241, 831, 32, 5, 5, 'Base / Pesado'],
            [47, 'ART-006', 'Lápices de colores largos caja 24 pzas (Prismacolor Jr. / Norma)', 'Arte y escolar', '1 caja (24 pzas)', 195.0, 20.5, 17.5, 1.0, 358.8, 60, 12.32, 42.0, 36.0, 22.0, 0.0333, 370, 32, 5, 5, 'Intermedio / Estándar'],
            [48, 'PAP-003', 'Cuaderno forma francesa cosido/espiral 100 hojas (Scribe / Estrella)', 'Papel y cuadernos', '1 pieza', 198.0, 21.0, 15.0, 1.2, 378.0, 60, 12.53, 44.0, 32.0, 24.0, 0.0338, 371, 32, 6, 6, 'Base / Pesado'],
            [49, 'OFI-006', 'Grapas estándar galvanizadas 26/6 caja 5,000 pzas (Mae / Barrilito / Swingline)', 'Suministros de oficina', '1 caja (5,000 pzas)', 200.0, 11.2, 5.5, 3.0, 184.8, 80, 16.65, 34.0, 26.0, 20.0, 0.0177, 942, 32, 6, 4, 'Base / Pesado'],
            [50, 'OFI-007', 'Engrapadora metálica tira completa usa 26/6 (Nextep NE-100 / Mae)', 'Suministros de oficina', '1 pieza en caja', 230.0, 16.5, 4.2, 6.0, 415.8, 72, 17.41, 44.0, 36.0, 32.0, 0.0507, 343, 32, 5, 5, 'Base / Pesado'],
            [51, 'OFI-008', 'Tabla sujetapapeles (clipboard) tamaño carta con broche (Mae / Baco)', 'Suministros de oficina', '1 pieza', 250.0, 31.8, 22.8, 1.5, 1087.6, 48, 12.72, 48.0, 34.0, 28.0, 0.0457, 278, 32, 5, 5, 'Intermedio / Estándar'],
            [52, 'ADH-009', 'Pegamento líquido blanco escolar 225–250 g neto (Resistol 850 Henkel)', 'Adhesivos y cintas', '1 envase (250 g)', 285.0, 7.0, 4.8, 17.5, 588.0, 24, 7.38, 34.0, 26.0, 20.0, 0.0177, 417, 29, 5, 5, 'Intermedio / Estándar'],
            [53, 'ARC-008', 'Carpeta panorámica 3 argollas 1" tamaño carta (Oxford / Kyma)', 'Archivo y organización', '1 pieza', 295.0, 29.4, 26.2, 3.9, 3004.1, 24, 7.80, 48.0, 32.0, 31.0, 0.0476, 164, 29, 4, 4, 'Superior / Ligero'],
            [54, 'OFI-009', 'Perforadora metálica de 2 orificios 20–30 hojas (Mae / Swingline)', 'Suministros de oficina', '1 pieza en caja', 310.0, 11.5, 9.5, 6.5, 710.1, 48, 15.63, 42.0, 32.0, 28.0, 0.0376, 415, 32, 5, 5, 'Base / Pesado'],
            [55, 'ART-007', 'Pintura acrílica / escolar bote 250 ml (Dixon Vinci / Politec)', 'Arte y escolar', '1 bote (250 ml)', 335.0, 6.5, 6.5, 11.5, 485.9, 24, 8.59, 38.0, 26.0, 14.0, 0.0138, 621, 29, 5, 5, 'Intermedio / Estándar'],
            [56, 'PAP-004', 'Cuaderno profesional espiral/cosido 100 hojas (Scribe / Norma)', 'Papel y cuadernos', '1 pieza', 360.0, 26.3, 20.0, 1.2, 631.2, 36, 13.68, 42.0, 28.0, 24.0, 0.0282, 485, 32, 6, 6, 'Base / Pesado'],
            [57, 'ADH-010', 'Cinta de empaque industrial 48 mm × 150 m (Janel 156 / Tuk)', 'Adhesivos y cintas', '1 rollo', 385.0, 13.5, 13.5, 4.8, 874.8, 36, 14.54, 42.0, 42.0, 22.0, 0.0388, 375, 32, 5, 5, 'Base / Pesado'],
            [58, 'ARC-009', 'Caja para archivo muerto de cartón carta desarmada (Baco / Geo / Ofix)', 'Archivo y organización', '1 pza (desarmada)', 410.0, 74.0, 56.0, 0.6, 2486.4, 25, 10.90, 76.0, 58.0, 17.0, 0.0749, 145, 32, 5, 5, 'Intermedio / Estándar'],
            [59, 'ARC-010', 'Carpeta registradora carta lomo 3" 2 arillos (Lefort / Kyma / Nextep)', 'Archivo y organización', '1 pieza', 450.0, 28.5, 26.5, 7.5, 5664.4, 20, 9.82, 58.0, 32.0, 30.0, 0.0557, 176, 29, 4, 4, 'Superior / Ligero'],
            [60, 'ARC-011', 'Carpeta panorámica 3 argollas 2" tamaño carta (Oxford / Kyma)', 'Archivo y organización', '1 pieza', 460.0, 29.5, 29.0, 7.3, 6245.2, 18, 9.06, 58.0, 32.0, 31.0, 0.0575, 157, 29, 4, 4, 'Superior / Ligero'],
            [61, 'ARC-012', 'Protectores de hojas polipropileno carta 100 pzas (Kinera / Dietrix)', 'Archivo y organización', '1 caja (100 pzas)', 560.0, 29.0, 23.0, 2.2, 1467.4, 24, 14.12, 48.0, 31.0, 28.0, 0.0417, 339, 32, 5, 5, 'Base / Pesado'],
            [62, 'ADH-011', 'Pegamento líquido blanco profesional 500 g neto (Resistol 850 Henkel)', 'Adhesivos y cintas', '1 botella (500 g)', 580.0, 21.0, 10.0, 4.8, 1008.0, 12, 7.44, 32.0, 22.0, 23.0, 0.0162, 459, 29, 5, 5, 'Intermedio / Estándar'],
            [63, 'PAP-005', 'Cuaderno profesional espiral doble 200 hojas (Scribe / Norma)', 'Papel y cuadernos', '1 pieza', 660.0, 26.3, 20.0, 2.2, 1157.2, 24, 16.60, 42.0, 28.0, 29.0, 0.0341, 487, 32, 5, 5, 'Base / Pesado'],
            [64, 'ARC-013', 'Sobres bolsa manila tamaño carta 90 g paq. 50 pzas (Fortec / Kyma)', 'Archivo y organización', '1 paq. (50 pzas)', 680.0, 31.5, 24.0, 2.5, 1890.0, 20, 14.28, 50.0, 33.0, 27.0, 0.0446, 321, 32, 5, 5, 'Base / Pesado'],
            [65, 'ADH-012', 'Pegamento líquido blanco escolar/prof. 1 kg neto (Resistol 850 Henkel)', 'Adhesivos y cintas', '1 porrón (1 kg)', 1090.0, 24.0, 11.0, 7.5, 1980.0, 12, 13.70, 35.0, 24.0, 26.0, 0.0218, 627, 32, 5, 5, 'Base / Pesado'],
            [66, 'OFI-010', 'Despachador de cinta escritorio base pesada (Scotch 3M / Mae)', 'Suministros de oficina', '1 pieza en caja', 1180.0, 20.5, 9.5, 10.5, 2044.9, 12, 14.88, 42.0, 32.0, 24.0, 0.0323, 461, 32, 5, 5, 'Base / Pesado'],
            [67, 'PAP-006', 'Papel opalina cartulina blanca carta 225 g/m² paq. 100 h (Copamex / Lumen)', 'Papel y cuadernos', '1 paq. (100 h)', 1400.0, 28.2, 21.8, 2.8, 1721.3, 12, 17.45, 45.0, 30.0, 19.0, 0.0257, 680, 32, 6, 6, 'Base / Pesado'],
            [68, 'PAP-007', 'Papel bond blanco tamaño carta 75 g/m² resma 500 h (Scribe / Xerox / Copamex)', 'Papel y cuadernos', '1 resma (500 h)', 2300.0, 28.0, 21.6, 5.2, 3145.0, 10, 23.70, 45.0, 29.0, 25.0, 0.0326, 726, 32, 5, 5, 'Base / Pesado'],
            [69, 'ARC-014', 'Fólder manila tamaño carta 1/2 ceja caja 100 pzas (Oxford / Baco)', 'Archivo y organización', '1 caja (100 pzas)', 2500.0, 31.0, 26.0, 7.5, 6045.0, 5, 13.15, 39.5, 32.5, 28.0, 0.0359, 366, 32, 5, 5, 'Base / Pesado'],
            [70, 'PAP-008', 'Rollo papel bond plotter 61 cm × 50 m 75–90 g/m² (Kronaline / Scribe)', 'Papel y cuadernos', '1 rollo en caja', 2700.0, 63.0, 11.5, 11.5, 8331.8, 4, 11.38, 65.0, 25.0, 25.0, 0.0406, 280, 32, 4, 4, 'Base / Pesado'],
            [71, 'PAP-009', 'Papel bond blanco tamaño oficio 75 g/m² resma 500 h (Xerox / Scribe / Copamex)', 'Papel y cuadernos', '1 resma (500 h)', 2800.0, 34.2, 21.6, 5.2, 3841.3, 10, 28.80, 45.0, 35.5, 25.5, 0.0407, 707, 40, 4, 4, 'Base / Pesado'],
            [72, 'PAP-010', 'Cartulina Bristol blanca 50×65 cm 180 g/m² paq. 100 pliegos (Estrella / Unibristolina)', 'Papel y cuadernos', '1 paq. (100 pl.)', 6050.0, 66.0, 51.0, 2.5, 8415.0, 3, 18.90, 68.0, 53.0, 9.0, 0.0324, 583, 32, 5, 5, 'Base / Pesado'],
        ];

        foreach ($rows as $row) {
            [
                $order,
                $sku,
                $name,
                $category,
                $presentation,
                $unitWeightG,
                $unitL,
                $unitW,
                $unitH,
                $unitVol,
                $masterFactor,
                $masterWeightKg,
                $masterL,
                $masterW,
                $masterH,
                $masterVol,
                $masterDensity,
                $ect,
                $maxLayersEct,
                $maxLayersOp,
                $stowZone,
            ] = $row;

            $zonePriority = match ($stowZone) {
                'Base / Pesado' => 1,
                'Intermedio / Estándar' => 2,
                default => 3,
            };

            $isLiquid = in_array($sku, $liquidSkus, true);
            $isHygroscopic = in_array($sku, $hygroscopicSkus, true);
            $isTempSensitive = in_array($sku, $tempSensitiveSkus, true);
            $isCrushable = in_array($sku, $crushableSkus, true);
            $isHighDensityMetal = in_array($sku, $highDensityMetalSkus, true);

            $riskProfile = match (true) {
                $isLiquid => 'liquido',
                $isHygroscopic => 'higroscopico',
                $isCrushable => 'aplastamiento',
                $isHighDensityMetal => 'metalico_minas',
                $isTempSensitive => 'temperatura',
                default => 'estandar',
            };

            $orientationRule = match (true) {
                in_array($sku, $horizontalInkSkus, true) => 'HORIZONTAL_TINTA',
                $isLiquid => 'VERTICAL_FLECHAS_ARRIBA',
                default => 'ESTANDAR',
            };

            $nom036Level = match (true) {
                $masterWeightKg > 25.0 => 'equipo_auxiliar_obligatorio',
                $masterWeightKg > 20.0 => 'alerta_20_25',
                $masterWeightKg > 15.0 => 'precaucion_15_20',
                default => 'estandar',
            };

            $notes = [];
            if ($isLiquid) {
                $notes[] = in_array($sku, $horizontalInkSkus, true)
                    ? 'Marcador base alcohol: estibar HORIZONTAL para preservar tinta; segregar de papelería higroscópica.'
                    : 'Líquido/tinta: mantener VERTICAL (flechas arriba), nunca estibar encima de papel higroscópico.';
            }
            if ($isHygroscopic) {
                $notes[] = 'Higroscópico: conservar a 20–24 °C y 45–55% HR sobre tarima (nunca piso directo) y sin líquidos arriba.';
            }
            if ($isTempSensitive) {
                $notes[] = 'Sensible a temperatura/UV: mantener a ≤25 °C (cintas/ceras/hule lejos de sol directo).';
            }
            if ($isCrushable) {
                $notes[] = 'Baja densidad / deformable: asignar obligatoriamente a zona Superior / Ligero (máx. '.$maxLayersOp.' camas).';
            }
            if ($isHighDensityMetal) {
                $notes[] = $sku === 'OFI-006'
                    ? 'Hiperdenso metálico (942 kg/m³): restringir a 4 camas operativas (821.2 kg) para no rebasar 1,000 kg en tarima.'
                    : 'Alta densidad metálica o minas frágiles: área seca y protección contra impactos/caídas.';
            }
            if ($masterWeightKg > 25.0) {
                $notes[] = 'NOM-036 (>25 kg): obligatorio patín hidráulico, mesa elevadora o carga asistida en pareja masculina.';
            } elseif ($masterWeightKg > 20.0) {
                $notes[] = 'NOM-036 (>20 kg): excede límite mujeres 18–45 / hombres >45 años; usar equipo auxiliar.';
            }

            Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'catalog_order' => $order,
                    'name' => $name,
                    'category' => $category,
                    'commercial_presentation' => $presentation,
                    'unit_weight_g' => $unitWeightG,
                    'unit_length_cm' => $unitL,
                    'unit_width_cm' => $unitW,
                    'unit_height_cm' => $unitH,
                    'unit_volume_cm3' => $unitVol,
                    'master_factor' => $masterFactor,
                    'master_weight_kg' => $masterWeightKg,
                    'master_length_cm' => $masterL,
                    'master_width_cm' => $masterW,
                    'master_height_cm' => $masterH,
                    'master_volume_m3' => $masterVol,
                    'master_density_kg_m3' => $masterDensity,
                    'ect_rating' => $ect,
                    'max_layers_ect' => $maxLayersEct,
                    'max_layers_operational' => $maxLayersOp,
                    'stow_zone' => $stowZone,
                    'zone_priority' => $zonePriority,
                    'risk_profile' => $riskProfile,
                    'is_liquid' => $isLiquid,
                    'is_hygroscopic' => $isHygroscopic,
                    'is_temp_sensitive' => $isTempSensitive,
                    'is_crushable' => $isCrushable,
                    'is_high_density_metal' => $isHighDensityMetal,
                    'orientation_rule' => $orientationRule,
                    'nom036_level' => $nom036Level,
                    'handling_notes' => implode(' ', $notes),
                ]
            );
        }
    }
}
