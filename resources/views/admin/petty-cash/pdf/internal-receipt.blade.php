<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo interno {{ $expense->document_full_number }}</title>
    <style>
        @page { margin: 26px 30px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #263a31;
            background: #ffffff;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.42;
        }
        table { width: 100%; border-collapse: collapse; }
        .sheet { border: 1px solid #cbded4; border-radius: 10px; background: #ffffff; overflow: hidden; }
        .top-accent { height: 7px; background: #11653f; }
        .header { padding: 20px 22px 17px; background: #f5faf7; }
        .header-table td { vertical-align: middle; }
        .brand-logo { width: 21%; padding-right: 15px; }
        .logo-frame {
            height: 76px;
            padding: 9px;
            border: 1px solid #d6e6de;
            border-radius: 8px;
            background: #ffffff;
            text-align: center;
        }
        .logo { max-width: 128px; max-height: 56px; }
        .logo-placeholder {
            display: block;
            padding-top: 17px;
            color: #17734a;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: .6px;
        }
        .company-data { width: 49%; padding-right: 18px; }
        .company-kicker {
            margin-bottom: 3px;
            color: #4f7462;
            font-size: 7.5px;
            font-weight: bold;
            letter-spacing: 1.1px;
            text-transform: uppercase;
        }
        .company-name {
            margin-bottom: 6px;
            color: #123f2c;
            font-size: 17px;
            font-weight: bold;
            line-height: 1.15;
        }
        .company-line { margin-top: 2px; color: #52665c; font-size: 8.5px; }
        .company-line strong { color: #2f4d3f; }
        .receipt-cell { width: 30%; }
        .receipt-card {
            padding: 13px 13px 12px;
            border: 1px solid #0e5736;
            border-radius: 9px;
            background: #11653f;
            color: #ffffff;
            text-align: center;
        }
        .receipt-type {
            font-size: 7.5px;
            font-weight: bold;
            letter-spacing: 1.5px;
            opacity: .9;
            text-transform: uppercase;
        }
        .receipt-title {
            margin: 3px 0 9px;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: .6px;
            line-height: 1.1;
        }
        .receipt-number {
            padding: 7px 6px;
            border: 1px solid #8ebfa7;
            border-radius: 5px;
            background: #ffffff;
            color: #124d32;
            font-size: 11px;
            font-weight: bold;
        }
        .meta-strip { border-top: 1px solid #dbe9e1; border-bottom: 1px solid #dbe9e1; background: #ffffff; }
        .meta-strip td { padding: 9px 12px; border-right: 1px solid #e1ece6; vertical-align: middle; }
        .meta-strip td:last-child { border-right: 0; }
        .meta-label {
            display: block;
            margin-bottom: 2px;
            color: #74867d;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: .5px;
            text-transform: uppercase;
        }
        .meta-value { color: #233e31; font-size: 9.5px; font-weight: bold; }
        .content { padding: 18px 22px 13px; }
        .section { margin-bottom: 14px; }
        .section-heading { margin-bottom: 7px; }
        .section-index {
            display: inline-block;
            width: 22px;
            padding: 3px 0;
            border-radius: 8px;
            background: #19734b;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            text-align: center;
        }
        .section-title {
            display: inline-block;
            margin-left: 7px;
            color: #174b34;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: .8px;
            text-transform: uppercase;
        }
        .data-card { border: 1px solid #d9e7df; border-radius: 7px; background: #ffffff; overflow: hidden; }
        .data-card td { padding: 9px 11px; border-right: 1px solid #e1ebe5; vertical-align: top; }
        .data-card td:last-child { border-right: 0; }
        .field-label {
            display: block;
            margin-bottom: 3px;
            color: #75877e;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: .45px;
            text-transform: uppercase;
        }
        .field-value { color: #243f32; font-size: 10px; font-weight: bold; }
        .detail-card { border: 1px solid #d7e6de; border-radius: 7px; background: #fbfdfc; overflow: hidden; }
        .concept-block { padding: 11px 13px 12px; }
        .concept-text { color: #173e2b; font-size: 11px; font-weight: bold; }
        .observation-block { padding: 8px 13px 9px; border-top: 1px solid #e0ebe5; background: #f4f9f6; color: #52665c; }
        .summary { margin-top: 2px; border: 1px solid #bcd7c9; border-radius: 8px; overflow: hidden; }
        .amount-words { width: 67%; padding: 11px 13px; background: #eef7f2; vertical-align: middle; }
        .amount-words-value { color: #214c37; font-size: 9.5px; font-weight: bold; line-height: 1.45; }
        .amount-total { width: 33%; padding: 10px 13px; background: #11653f; color: #ffffff; text-align: right; vertical-align: middle; }
        .amount-total .field-label { color: #cce4d7; }
        .currency { margin-right: 5px; font-size: 11px; font-weight: normal; }
        .total-value { font-size: 22px; font-weight: bold; letter-spacing: .2px; }
        .signatures-section { margin-top: 17px; }
        .signatures { table-layout: fixed; }
        .signatures td { width: 33.33%; padding: 0 15px; text-align: center; vertical-align: bottom; }
        .signature-space { height: 43px; }
        .signature-line { border-top: 1px solid #60766a; }
        .signature-role {
            margin-top: 6px;
            color: #174b34;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: .5px;
            text-transform: uppercase;
        }
        .signature-name { min-height: 24px; margin-top: 3px; color: #6a7c73; font-size: 7.5px; line-height: 1.35; }
        .footer { border-top: 1px solid #dbe8e1; background: #f5f9f7; }
        .footer td { padding: 9px 22px; color: #708078; font-size: 7.5px; }
        .footer-code { color: #315c46; font-weight: bold; text-align: right; }
    </style>
</head>
<body>
@php
    $companyName = $company?->business_name ?: $company?->trade_name ?: 'Empresa no especificada';
    $receiptNumber = $expense->document_full_number ?: ('N.º ' . $expense->id);
    $registeredBy = trim(($expense->creator?->name ?? '') . ' ' . ($expense->creator?->lastname ?? '')) ?: 'Usuario del sistema';
    $contact = collect([$company?->phone, $company?->email])->filter()->join('  |  ');
@endphp
<div class="sheet">
    <div class="top-accent"></div>

    <div class="header">
        <table class="header-table">
            <tr>
                <td class="brand-logo">
                    <div class="logo-frame">
                        @if ($logoDataUri)
                            <img class="logo" src="{{ $logoDataUri }}" alt="Logo">
                        @else
                            <span class="logo-placeholder">{{ $company?->trade_name ?: 'CAJA CHICA' }}</span>
                        @endif
                    </div>
                </td>
                <td class="company-data">
                    <div class="company-kicker">Documento corporativo</div>
                    <div class="company-name">{{ $companyName }}</div>
                    @if ($company?->ruc)<div class="company-line"><strong>RUC</strong> {{ $company->ruc }}</div>@endif
                    @if ($company?->address)<div class="company-line">{{ $company->address }}</div>@endif
                    @if ($contact)<div class="company-line">{{ $contact }}</div>@endif
                </td>
                <td class="receipt-cell">
                    <div class="receipt-card">
                        <div class="receipt-type">Caja chica</div>
                        <div class="receipt-title">RECIBO<br>INTERNO</div>
                        <div class="receipt-number">{{ $receiptNumber }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-strip">
        <tr>
            <td style="width: 25%;"><span class="meta-label">Fecha de emisión</span><span class="meta-value">{{ $expense->expense_date?->format('d/m/Y') }}</span></td>
            <td style="width: 27%;"><span class="meta-label">Caja chica</span><span class="meta-value">{{ $box->code ?: ('Caja #' . $box->id) }}</span></td>
            <td style="width: 48%;"><span class="meta-label">Registrado por</span><span class="meta-value">{{ $registeredBy }}</span></td>
        </tr>
    </table>

    <div class="content">
        <div class="section">
            <div class="section-heading"><span class="section-index">01</span><span class="section-title">Proveedor / receptor</span></div>
            <table class="data-card">
                <tr>
                    <td style="width: 31%;"><span class="field-label">RUC / documento</span><span class="field-value">{{ $expense->supplier_ruc ?: 'No consignado' }}</span></td>
                    <td><span class="field-label">Nombre o razón social</span><span class="field-value">{{ $expense->supplier_name ?: 'No consignado' }}</span></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-heading"><span class="section-index">02</span><span class="section-title">Detalle del gasto</span></div>
            <div class="detail-card">
                <div class="concept-block">
                    <span class="field-label">Concepto</span>
                    <div class="concept-text">{{ $expense->concept }}</div>
                </div>
                <div class="observation-block">
                    <span class="field-label">Observación</span>
                    {{ $expense->observation ?: 'Sin observación.' }}
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-heading"><span class="section-index">03</span><span class="section-title">Resumen del importe</span></div>
            <table class="summary">
                <tr>
                    <td class="amount-words">
                        <span class="field-label">Son</span>
                        <div class="amount-words-value">{{ $amountInWords }}</div>
                    </td>
                    <td class="amount-total">
                        <span class="field-label">Importe total</span>
                        <span class="currency">S/</span><span class="total-value">{{ number_format((float) $expense->amount, 2, '.', ',') }}</span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="signatures-section">
            <div class="section-heading"><span class="section-index">04</span><span class="section-title">Conformidad y firmas</span></div>
            <table class="signatures">
                <tr>
                    <td>
                        <div class="signature-space"></div><div class="signature-line"></div>
                        <div class="signature-role">Entregado por</div>
                        <div class="signature-name">{{ $box->responsible_name ?: 'Nombre y firma' }}@if($box->responsible_dni)<br>DNI {{ $box->responsible_dni }}@endif</div>
                    </td>
                    <td>
                        <div class="signature-space"></div><div class="signature-line"></div>
                        <div class="signature-role">Recibido por</div>
                        <div class="signature-name">{{ $expense->supplier_name ?: 'Nombre y firma' }}@if($expense->supplier_ruc)<br>{{ $expense->supplier_ruc }}@endif</div>
                    </td>
                    <td>
                        <div class="signature-space"></div><div class="signature-line"></div>
                        <div class="signature-role">Aprobado por</div>
                        <div class="signature-name">{{ $box->supervisor_name ?: 'Nombre y firma' }}@if($box->supervisor_dni)<br>DNI {{ $box->supervisor_dni }}@endif</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <table class="footer">
        <tr>
            <td>Documento interno generado automáticamente el {{ $generatedAt->format('d/m/Y H:i:s') }}.</td>
            <td class="footer-code">CAJA CHICA · {{ $receiptNumber }}</td>
        </tr>
    </table>
</div>
</body>
</html>
