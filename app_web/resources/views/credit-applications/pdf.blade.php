<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 14px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { border: 1px solid #1f1f1f; padding: 2px 4px; vertical-align: middle; word-wrap: break-word; }

        .header-main { background: #7a1f7b; color: #fff; font-weight: 700; text-transform: uppercase; font-size: 16px; }
        .header-section { background: #8f2a90; color: #fff; font-weight: 700; text-transform: uppercase; text-align: center; font-size: 12px; }
        .header-gray { background: #b5b5b5; font-weight: 700; text-transform: uppercase; text-align: center; }
        .label { background: #bdbdbd; font-weight: 700; text-transform: uppercase; }
        .small-label { background: #c7c7c7; font-weight: 700; text-transform: uppercase; font-size: 10px; }
        .center { text-align: center; }
        .right { text-align: right; }
        .h24 { height: 24px; }
        .h34 { height: 34px; }
        .h44 { height: 44px; }
        .h58 { height: 58px; }
        .h72 { height: 72px; }
        .h88 { height: 88px; }
        .signature-box img { max-height: 84px; max-width: 100%; }
        .block-space { margin-top: 2px; }
        .text-justify { text-align: justify; }
    </style>
</head>
<body>
    <table>
        <tr class="header-main h44">
            <td style="width: 60%;" class="center">SOLICITUD DE CREDITO</td>
            <td style="width: 40%;" class="right">NETSECURITYBERBOU<br>NIT: 21388364-8</td>
        </tr>
        <tr>
            <td class="label">FECHA DE SOLICITUD</td>
            <td>{{ optional($application->request_date)->format('Y-m-d') }}</td>
        </tr>
    </table>

    <table class="block-space">
        <tr><td class="header-section" colspan="8">DATOS PERSONALES</td></tr>
        <tr>
            <td class="label" colspan="2">NOMBRES Y APELLIDOS</td>
            <td colspan="6">{{ $application->full_name }}</td>
        </tr>
        <tr>
            <td class="label" colspan="2">DOCUMENTO DE IDENTIDAD</td>
            <td class="small-label">TIPO</td>
            <td>{{ $application->document_type }}</td>
            <td class="small-label">NUMERO</td>
            <td>{{ $application->document_number }}</td>
            <td class="small-label">FECHA DE EXPEDICION</td>
            <td>{{ optional($application->document_issue_date)->format('Y-m-d') }}</td>
        </tr>
        <tr>
            <td class="label" colspan="2">TELEFONOS DE CONTACTO</td>
            <td class="small-label">CELULAR 1</td>
            <td>{{ $application->phone_primary }}</td>
            <td class="small-label">CELULAR 2</td>
            <td colspan="3">{{ $application->phone_secondary }}</td>
        </tr>
        <tr>
            <td class="label" colspan="2">CORREO ELECTRONICO</td>
            <td colspan="6">{{ $application->email }}</td>
        </tr>
        <tr>
            <td class="label" colspan="2">DIRECCION DE RESIDENCIA</td>
            <td colspan="4">{{ $application->residential_address }}</td>
            <td class="small-label">CIUDAD</td>
            <td>{{ $application->city }}</td>
        </tr>
        <tr>
            <td class="label" colspan="2">BARRIO</td>
            <td colspan="6">{{ $application->neighborhood }}</td>
        </tr>
    </table>

    <table class="block-space">
        <tr><td class="header-section" colspan="8">DATOS LABORALES</td></tr>
        <tr>
            <td class="label" colspan="2">EMPRESA DONDE LABORA</td>
            <td colspan="6">{{ $application->company?->name ?? $application->employer_name }}</td>
        </tr>
        <tr>
            <td class="label" colspan="2">SEDE</td>
            <td colspan="3">{{ $application->work_site }}</td>
            <td class="small-label">FECHA DE INGRESO</td>
            <td colspan="2">{{ optional($application->hire_date)->format('Y-m-d') }}</td>
        </tr>
        <tr>
            <td class="label" colspan="2">TIPO DE CONTRATO</td>
            <td colspan="3">{{ $application->contract_type }}</td>
            <td class="small-label">INGRESOS MENSUALES</td>
            <td colspan="2">${{ number_format((float) $application->monthly_income, 0, ',', '.') }}</td>
        </tr>
    </table>

    <table class="block-space">
        <tr><td class="header-section" colspan="8">DATOS DEL CREDITO</td></tr>
        <tr>
            <td class="label" colspan="2">PRODUCTOS SOLICITADOS</td>
            <td colspan="4">{{ $application->requested_products }}</td>
            <td class="small-label">$</td>
            <td></td>
        </tr>
        <tr>
            <td class="label" colspan="2">VALOR NETO SIN INTERES</td>
            <td colspan="2">${{ number_format((float) $application->net_value_without_interest, 0, ',', '.') }}</td>
            <td class="small-label">MONTO APROBADO</td>
            <td>${{ number_format((float) $application->net_value_without_interest, 0, ',', '.') }}</td>
            <td class="small-label">$</td>
            <td></td>
        </tr>
        <tr>
            <td class="label" colspan="2">VALOR DE LA CUOTA</td>
            <td colspan="2">${{ number_format((float) $application->installment_value, 0, ',', '.') }}</td>
            <td class="small-label center">NUMERO DE CUOTAS</td>
            <td class="center">{{ $application->installments_count }}</td>
            <td colspan="2">{{ strtoupper((string) $application->payment_frequency) }}</td>
        </tr>
        <tr>
            <td class="label" colspan="2">FECHA PRIMER CUOTA</td>
            <td colspan="6">{{ optional($application->first_installment_date)->format('Y-m-d') }}</td>
        </tr>
    </table>

    <table class="block-space">
        <tr><td class="header-section">OBSERVACIONES</td></tr>
        <tr><td class="h44">{{ $application->observations }}</td></tr>
    </table>

    <table class="block-space">
        <tr>
            <td class="center text-justify" style="font-size: 10px;">
                <strong>Autorizo a Netsecurityberbou para la toma y verificacion de los datos suministrados con el fin de realizar los cobros pertinentes, en caso de incumplir con las cuotas o pagos pactados, se procedera con el cobro juridico como se estipula en la ley del articulo 11 del Decreto 2677 de 2012.</strong>
            </td>
        </tr>
        <tr>
            <td class="text-justify" style="font-size: 9px;">
                Los datos personales aqui recolectados seran recolectados, almacenados, procesados, usados, compilados, transmitidos, transferidos, actualizados y dispuestos conforme lo establece la Ley 1581 de 2012 y su decreto reglamentario.
            </td>
        </tr>
    </table>

    <table class="block-space">
        <tr><td class="header-gray" colspan="8">AUTORIZACION DE DESCUENTO</td></tr>
        <tr>
            <td class="label" colspan="2">EMPLEADOR:</td>
            <td colspan="4">{{ $application->employer_name }}</td>
            <td class="label">FECHA</td>
            <td>{{ optional($application->discount_authorization_date)->format('Y-m-d') }}</td>
        </tr>
        <tr>
            <td class="label" colspan="2">NIT:</td>
            <td colspan="6">{{ $application->employer_nit }}</td>
        </tr>
        <tr>
            <td class="label" colspan="2">NOMBRE DEL EMPLEADO:</td>
            <td colspan="3">{{ $application->employee_name }}</td>
            <td class="label">DOCUMENTO:</td>
            <td colspan="2">{{ $application->employee_document }}</td>
        </tr>
        <tr>
            <td class="label" colspan="2">CARGO:</td>
            <td colspan="6">{{ $application->employee_position }}</td>
        </tr>
        <tr>
            <td class="label" colspan="2">DESCUENTO POR:</td>
            <td colspan="6">{{ $application->discount_concept }}</td>
        </tr>
        <tr>
            <td class="label" colspan="2">VALOR TOTAL:</td>
            <td colspan="6">${{ number_format((float) $application->discount_total_value, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td colspan="8" class="text-justify h58">
                Mediante la firma del presente documento, manifiesto de manera expresa y voluntaria mi autorizacion para que la empresa realice el descuento correspondiente en la nomina que me corresponde.
            </td>
        </tr>
        <tr>
            <td class="header-gray" colspan="4">VALOR CUOTA QUINCENAL</td>
            <td class="header-gray" colspan="4">NRO. DE CUOTAS</td>
        </tr>
        <tr>
            <td colspan="4" class="h24">${{ number_format((float) $application->installment_value, 0, ',', '.') }}</td>
            <td colspan="4" class="h24">{{ $application->installments_count }}</td>
        </tr>
        <tr>
            <td colspan="8" class="text-justify h58">
                Autorizo a mi empleador para que descuente de una vez, terminado mi contrato de trabajo de mis salarios y prestaciones sociales el valor adeudado.
            </td>
        </tr>
        <tr>
            <td class="header-gray" colspan="4">SALDO PENDIENTE EN LA TERMINACION DE CONTRATO</td>
            <td class="header-gray" colspan="4">VALOR A DESCONTAR DE LA LIQUIDACION</td>
        </tr>
        <tr>
            <td colspan="4">$</td>
            <td colspan="4">$</td>
        </tr>
        <tr>
            <td colspan="4" class="h88"></td>
            <td class="label" colspan="4">VENDEDOR</td>
        </tr>
        <tr>
            <td colspan="4" class="label center">FIRMA DEL EMPLEADO</td>
            <td colspan="4" class="center">Santiago Muñoz Henao</td>
        </tr>
        <tr>
            <td class="label" colspan="2">DOCUMENTO DE IDENTIDAD</td>
            <td class="small-label">TIPO</td>
            <td>{{ $application->document_type }}</td>
            <td class="label" colspan="2">CONTACTO</td>
            <td colspan="2">3103653021</td>
        </tr>
        <tr>
            <td class="center" colspan="4">NOMBRE COMPLETO</td>
            <td class="center" colspan="4">FIRMA</td>
        </tr>
        <tr>
            <td colspan="4">{{ $application->full_name }}</td>
            <td colspan="4" class="signature-box center">
                @if ($application->signature_path)
                    <img src="{{ public_path($application->signature_path) }}" alt="Firma">
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
