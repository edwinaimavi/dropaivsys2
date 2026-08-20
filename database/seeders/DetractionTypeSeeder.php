<?php

namespace Database\Seeders;

use App\Models\DetractionType;
use Illuminate\Database\Seeder;

class DetractionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['ANEXO_I', '001', 'Azúcar y melaza de caña', 10, 'RS 183-2004/SUNAT'],
            ['OTROS', '002', 'Arroz pilado', 3.85, 'RS 266-2004/SUNAT'],
            ['ANEXO_I', '003', 'Alcohol etílico', 10, 'RS 183-2004/SUNAT'],
            ['ANEXO_II', '004', 'Recursos hidrobiológicos', 4, 'RS 183-2004/SUNAT'],
            ['ANEXO_II', '005', 'Maíz amarillo duro', 4, 'RS 183-2004/SUNAT'],
            ['ANEXO_II', '007', 'Caña de azúcar', 10, 'RS 183-2004/SUNAT'],
            ['ANEXO_II', '008', 'Madera', 4, 'RS 183-2004/SUNAT'],
            ['ANEXO_II', '009', 'Arena y piedra', 10, 'RS 183-2004/SUNAT'],
            ['ANEXO_II', '010', 'Residuos, subproductos, desechos, recortes, desperdicios', 15, 'RS 183-2004/SUNAT'],
            ['ANEXO_II', '011', 'Bienes gravados con el IGV por renuncia a la exoneración', 10, 'RS 183-2004/SUNAT'],
            ['ANEXO_III', '012', 'Intermediación laboral y tercerización', 12, 'RS 183-2004/SUNAT'],
            ['ANEXO_II', '014', 'Carnes y despojos comestibles', 4, 'RS 183-2004/SUNAT'],
            ['ANEXO_II', '016', 'Aceite de pescado', 10, 'RS 183-2004/SUNAT'],
            ['ANEXO_II', '017', 'Harina, polvo y pellets de pescado, crustáceos, moluscos y demás invertebrados acuáticos', 4, 'RS 183-2004/SUNAT'],
            ['ANEXO_III', '019', 'Arrendamiento de bienes', 10, 'RS 183-2004/SUNAT'],
            ['ANEXO_III', '020', 'Mantenimiento y reparación de bienes muebles', 12, 'RS 183-2004/SUNAT'],
            ['ANEXO_III', '021', 'Movimiento de carga', 10, 'RS 183-2004/SUNAT'],
            ['ANEXO_III', '022', 'Otros servicios empresariales', 12, 'RS 183-2004/SUNAT'],
            ['ANEXO_II', '023', 'Leche cruda entera', 4, 'RS 183-2004/SUNAT'],
            ['ANEXO_III', '024', 'Comisión mercantil', 10, 'RS 183-2004/SUNAT'],
            ['ANEXO_III', '025', 'Fabricación de bienes por encargo', 10, 'RS 183-2004/SUNAT'],
            ['ANEXO_III', '026', 'Servicio de transporte de personas', 10, 'RS 183-2004/SUNAT'],
            ['OTROS', '027', 'Servicio de transporte de carga', 4, 'RS 073-2006/SUNAT'],
            ['ANEXO_III', '030', 'Contratos de construcción', 4, 'RS 183-2004/SUNAT'],
            ['ANEXO_II', '031', 'Oro gravado con el IGV', 10, 'RS 183-2004/SUNAT'],
            ['ANEXO_II', '032', 'Páprika y otros frutos de los géneros capsicum o pimienta', 10, 'RS 183-2004/SUNAT'],
            ['ANEXO_I', '034', 'Minerales metálicos no auríferos', 10, 'RS 086-2025/SUNAT'],
            ['ANEXO_II', '035', 'Bienes exonerados del IGV', 1.5, 'RS 183-2004/SUNAT'],
            ['ANEXO_II', '036', 'Oro y demás minerales metálicos exonerados del IGV', 1.5, 'RS 183-2004/SUNAT'],
            ['ANEXO_III', '037', 'Demás servicios gravados con el IGV', 12, 'RS 183-2004/SUNAT'],
            ['ANEXO_II', '039', 'Minerales no metálicos', 10, 'RS 183-2004/SUNAT'],
            ['OTROS', '040', 'Primera venta de inmuebles gravada con IGV', 4, 'RS 022-2013/SUNAT'],
            ['ANEXO_II', '041', 'Plomo', 15, 'RS 183-2004/SUNAT'],
            ['ANEXO_III', '044', 'Servicio de beneficio de minerales metálicos gravado con el IGV', 12, 'RS 086-2025/SUNAT'],
            ['ANEXO_I', '045', 'Minerales de oro y sus concentrados gravados con el IGV', 10, 'RS 086-2025/SUNAT'],
            ['OTROS', '099', 'Ley 30737', 8, 'Ley 30737'],
        ];
        $legacyCodes = [
            '001' => 'ANEXO_I_01', '003' => 'ANEXO_I_02',
            '004' => 'ANEXO_II_01', '005' => 'ANEXO_II_02', '009' => 'ANEXO_II_03',
            '010' => 'ANEXO_II_04', '014' => 'ANEXO_II_05', '017' => 'ANEXO_II_06',
            '008' => 'ANEXO_II_07', '031' => 'ANEXO_II_08', '034' => 'ANEXO_II_09',
            '035' => 'ANEXO_II_10', '036' => 'ANEXO_II_11', '039' => 'ANEXO_II_12',
            '007' => 'ANEXO_II_13', '011' => 'ANEXO_II_14', '016' => 'ANEXO_II_15',
            '023' => 'ANEXO_II_16', '032' => 'ANEXO_II_17', '041' => 'ANEXO_II_18',
            '012' => 'ANEXO_III_01', '019' => 'ANEXO_III_02', '020' => 'ANEXO_III_03',
            '021' => 'ANEXO_III_04', '022' => 'ANEXO_III_05', '024' => 'ANEXO_III_06',
            '025' => 'ANEXO_III_07', '026' => 'ANEXO_III_08', '030' => 'ANEXO_III_09',
            '037' => 'ANEXO_III_10',
        ];

        DetractionType::query()->update(['status' => 'INACTIVE']);

        foreach ($items as [$appendix, $code, $name, $percentage, $legalReference]) {
            $type = DetractionType::query()->where('code', $code)->first();
            if (! $type && isset($legacyCodes[$code])) {
                $type = DetractionType::query()->where('code', $legacyCodes[$code])->first();
            }

            ($type ?? new DetractionType())->fill([
                'appendix' => $appendix,
                'code' => $code,
                'name' => $name,
                'description' => $name,
                'percentage' => $percentage,
                'legal_reference' => $legalReference,
                'effective_from' => null,
                'status' => DetractionType::STATUS_ACTIVE,
            ])->save();
        }
    }
}
