<?php

namespace App\Imports;

use App\Models\Ingreso;
use App\Models\Avaluo;
use App\Models\User;
use App\Models\Inspeccion;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;


class IngresosImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // 1️⃣ Crear el ingreso
           
            $ingreso = Ingreso::create([
                'tiposervicio'              =>'Avaluo e Inspección',
                'solicitante'              => $row['solicitante'] ?? null,
                'documento_solicitante'    => $row['documento'] ?? null,
                'tipo_propiedad'           => $row['tipo'] ?? null,
                'placa'                    => $row['placa'] ?? null,
                'codigo_interno_movil'     => $row['codigointerno'] ?? null,
                'marca'                    => $row['marca'] ?? null,
                'linea'                    => $row['linea'] ?? null,
                'tipo_carroceria'          => $row['carroceria_tipo_y_cabina'] ?? null,
                'modelo'                   => $row['modelo'] ?? null,
                'cilindraje'               => $row['cilindraje'] ?? null,
                'color'                    => $row['color'] ?? null,
                'numero_motor'             => $row['serialmotor'] ?? null,
                'numero_chasis'            => $row['serial_chasis'] ?? null,
                'clase'                    => $row['clase'] ?? null,
                'kilometraje'              => $row['kilometraje'] ?? null,
                'no_licencia'              => $row['licencia_de_transito'] ?? null,
                'propietario'              => $row['propietario'] ?? null,
                'documento_propietario'    => $row['nit_cc'] ?? null,
                'organismo_transito'       => $row['organismo_de_transito'] ?? null,
                'fecha_expedicion_licencia'=> $this->parseExcelDate($row['fecha_expedicion_licencia_transito_dd_mm_aaaa'] ??  null),
                'numero_pasajeros'         => $row['capacidad_psj_o_carga'] ?? null,
                'capacidad_carga'          => $row['capacidad_psj_o_carga'] ?? null,
                'peso_bruto'               => $row['peso_bruto_vehicular'] ?? null,
                'fecha_vencimiento_soat'   => $this->parseExcelDate($row['soat_vencimiento_aa_mm_dd'] ?? null),
                'fecha_vencimiento_rtm'    => $this->parseExcelDate($row['revision_tecnomecanica_vencimiento_aaaa_mm_dd'] ?? null),
                'estado'                   => 'En Inspección',
            ]);

            // 🔎 Buscar avaluador
            $avaluadorName = $row['avaluador'] ?? null;
            $avaluadorId   = null;

            if ($avaluadorName) {
                $user = User::where('name', 'like', '%' . trim($avaluadorName) . '%')->first();
                $avaluadorId = $user ? $user->id : null;
            }

            // 2️⃣ Crear el avaluo relacionado
            Avaluo::create([
                'ingreso_id'=> $ingreso->id,
                'fecha_inspeccion' => $this->parseExcelDate($row['fecha_avaluo'] ?? null),
                'valor_reposicion'=> $row['valor_reposicion_nuevo'] ?? null,
                'gps' => $row['gps'] ?? null,
                'declaracion_importacion' => $row['manifiesto_de_aduana'] ?? null,
                'no_factura'=> $this->parseExcelDate($row['factura_compra_nuevo_aaaa_mm_dd'] ?? null), 
                'fecha_importacion'=> $this->parseExcelDate($row['fecha_manifiesto_importacion_aaaa_mm_dd'] ?? null),
                'valor_carroceria'=> $row['valor_razonable_carroceria'] ?? null,
                'valor_accesorios'=> $row['valor_accesorios_o_adecuaciones'] ?? null,
                'valor_razonable'=> $row['valor_razonable_automotor'] ?? null,
                'capacidad_transportadora'=> $row['cap_transportadora'] ?? null,
                'valor_pintura'=> $row['valor_pintura'] ?? null,
                'valor_llantas'=> $row['valor_llantas'] ?? null,
                'avaluador'=> $avaluadorName,
                'user_id'=> $avaluadorId,
            ]);

            // 🔎 Buscar inspector
            $inspectorName = $row['inspector'] ?? null;
            $inspectorId   = null;

            if ($inspectorName) {
                $user = User::where('name', 'like', '%' . trim($inspectorName) . '%')->first();
                $inspectorId = $user ? $user->id : null;
            }

            // 3️⃣ Crear inspección
            Inspeccion::create([
                'ingreso_id'=> $ingreso->id,
                'ciudad' => $row['ciudad'] ?? null,
                'novedades_inspeccion'=> $row['observaciones_inspector'] ?? null,
                'cod_fasecolda' => $row['codigo_fasecolda'] ?? null,
                'valor_accesorios'=> $row['valor_accesorios_o_adecuaciones'] ?? null,
                'kilometraje' => $row['kilometraje'] ?? null,
                'servicio' => $row['servicio'] ?? null,
                'color'=> $row['color'] ?? null,
                'inspector'=> $inspectorName,            
                'user_id'=>$inspectorId
            ]);
        }
    }


    private function parseExcelDate($value)
    {
        if (empty($value)) {
            return null;
        }

        // Si es un número => serial de Excel
        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        // Si es texto => intentar con Carbon
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

}
