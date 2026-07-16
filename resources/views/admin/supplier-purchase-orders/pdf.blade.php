@php
    $currencySymbol = $order->currency?->symbol ?? '';
    $company = $order->company;
    $supplier = $order->supplier;
    $account = $order->supplierAccount;
    $bank = $account?->bank;
    $shippingAgency = $order->shippingAgency;
    $shippingBranch = $order->shippingAgencyBranch;
    $shippingContact = $order->shippingAgencyContact;
    $ubigeo = $order->destinationUbigeo;
    $formatMoney = fn ($value) => trim($currencySymbol . ' ' . number_format((float) $value, 2, '.', ','));
    $formatDecimal = function ($value, int $maxDecimals = 6): string {
        $formatted = rtrim(rtrim(number_format((float) $value, $maxDecimals, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    };
    $pdfLineTotal = fn ($item) => $item->total_with_igv !== null
        ? (float) $item->total_with_igv
        : ($item->line_total !== null
            ? (float) $item->line_total
            : (float) $item->quantity * (float) $item->unit_price);
    $pdfTaxableBase = fn ($item) => $item->taxable_base !== null
        ? (float) $item->taxable_base
        : ($order->affect_igv ? $pdfLineTotal($item) / 1.18 : $pdfLineTotal($item));
    $pdfIgvAmount = fn ($item) => $item->igv_amount !== null
        ? (float) $item->igv_amount
        : ($order->affect_igv ? $pdfLineTotal($item) - $pdfTaxableBase($item) : 0.0);
    $pdfSubtotal = $order->items->sum(fn ($item) => $pdfTaxableBase($item));
    $pdfIgv = $order->items->sum(fn ($item) => $pdfIgvAmount($item));
    $pdfGrandTotal = $order->items->sum(fn ($item) => $pdfLineTotal($item));
    $formatDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d-m-Y') : null;
    $optionLabel = function (?string $value): string {
        $labels = [
            'contado' => 'Contado',
            'credito_20_dias' => 'Credito 20 dias',
            'credito_30_dias' => 'Credito 30 dias',
            'credito_45_dias' => 'Credito 45 dias',
            'credito_60_dias' => 'Credito 60 dias',
            'terrestre' => 'Terrestre',
            'aereo' => 'Aereo',
            'agencia' => 'Agencia',
            'agencia_transporte' => 'Agencia de transporte',
            'en_agencia' => 'En agencia',
            'transporte' => 'Transporte',
            'recojo_almacen' => 'Recojo de almacen',
            'transportista_proveedor' => 'Transportista del proveedor',
        ];

        return $labels[$value] ?? ($value ? ucfirst(str_replace('_', ' ', $value)) : '-');
    };
    $userName = function ($user): string {
        return $user
            ? (trim(($user->name ?? '') . ' ' . ($user->lastname ?? '')) ?: ($user->email ?? '-'))
            : '-';
    };
    $destinationDetail = collect([
        $ubigeo ? $ubigeo->department . ' / ' . $ubigeo->province . ' / ' . $ubigeo->district : null,
        $order->destination_text,
    ])->filter()->join(' | ') ?: ($order->shipping_address ?: '-');
    $headlineDestinationRaw = $order->destination_text
        ?: ($ubigeo ? collect([$ubigeo->department, $ubigeo->district])->filter()->unique()->join(' / ') : null)
        ?: ($order->shipping_address ?: '-');
    $headlineDestination = mb_strtoupper(\Illuminate\Support\Str::ascii($headlineDestinationRaw));
    $instructionBankRaw = $bank?->short_name ?: ($bank?->description ?: '');
    $instructionBankNormalized = mb_strtoupper(\Illuminate\Support\Str::ascii(trim($instructionBankRaw)));
    $instructionBankCompact = preg_replace('/[^A-Z0-9]+/', '', $instructionBankNormalized);
    $instructionBank = collect(['BBVA', 'BCP', 'INTERBANK', 'SCOTIABANK'])
        ->first(fn ($knownBankCode) => str_contains((string) $instructionBankCompact, $knownBankCode))
        ?: trim((string) preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9 ]+/', ' ', $instructionBankNormalized)));
    $instructionDestinationRaw = $order->destination_text
        ?: ($ubigeo ? collect([$ubigeo->department, $ubigeo->district])->filter()->unique()->join(' / ') : null);
    $instructionDestination = $instructionDestinationRaw
        ? mb_strtoupper(\Illuminate\Support\Str::ascii($instructionDestinationRaw))
        : '-';
    $defaultPurchaseInstructions = 'Abono de la presente Orden de compra se realizo a cuentas de la empresa '
        . $instructionBank
        . ' - Factura enviar al correo, embalaje y rotulado de forma correcta, para ser enviado a la ciudad de '
        . $instructionDestination;
    $savedPurchaseInstructions = trim((string) $order->purchase_instructions);
    $savedPurchaseInstructionsNormalized = mb_strtoupper(\Illuminate\Support\Str::ascii($savedPurchaseInstructions));
    $purchaseInstructions = $savedPurchaseInstructions
        && ! str_contains($savedPurchaseInstructionsNormalized, 'PRUEBA DE INSTRUCCIONES')
        && ! str_contains($savedPurchaseInstructionsNormalized, 'PRUEBA INSTRUCCIONES')
        && ! str_contains($savedPurchaseInstructionsNormalized, 'TEST')
        && ! str_contains($savedPurchaseInstructionsNormalized, 'LOREM')
            ? $savedPurchaseInstructions
            : $defaultPurchaseInstructions;
    $displayCode = preg_replace_callback('/^(.+-\d{4}-)(.+)$/', function ($matches) {
        $bankSegment = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(\Illuminate\Support\Str::ascii($matches[2])));

        return $matches[1] . ($bankSegment ?: $matches[2]);
    }, (string) $order->code);
    $agencyLocation = $shippingBranch
        ? collect([$shippingBranch->district, $shippingBranch->province, $shippingBranch->department])->filter()->join(' / ')
        : null;
    $agencyPhones = $shippingContact
        ? collect([
            $shippingContact->phone ? 'Tel: ' . $shippingContact->phone : null,
            $shippingContact->whatsapp ? 'WhatsApp: ' . $shippingContact->whatsapp : null,
        ])->filter()->join(' | ')
        : null;
    $billingEmail = $company?->email ?: 'gerencia@dropaiv.com';
    $deliveryText = $order->delivery_text ?: 'EN AGENCIA DE TRANSPORTES - ENVIO A PROVINCIA';
    $departmentText = $order->request_department ?: 'COMPRAS';
    $requestedBy = $userName($order->updater ?: $order->creator);
    $bankLabelRaw = $bank?->short_name ?: $bank?->description;
    $bankLabelCompact = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(\Illuminate\Support\Str::ascii((string) $bankLabelRaw)));
    $bankLabel = collect(['BBVA', 'BCP', 'INTERBANK', 'SCOTIABANK'])
        ->first(fn ($knownBankCode) => str_contains($bankLabelCompact, $knownBankCode))
        ?: $bankLabelRaw;
    $paymentTerms = collect([
        $order->payment_condition ? $optionLabel($order->payment_condition) : null,
        $bankLabel,
        $account?->account_number ?: $account?->cci,
    ])->filter()->join(' - ') ?: '-';
    $importantNote = $order->important_note ?: "ADJUNTAR JUNTAMENTE CON LA FACTURA Y GUIA DE REMISION AL CORREO: LOGISTIC@DROPAIV.COM, LOS DOCUMENTOS LEGALES NECESARIOS TALES COMO:\n1. BPM O ISO DEL BIEN ADQUIRIDO O SU EQUIVALENTE - VIGENTE\n2. CERTIFICADO O PROTOCOLO DE ANALISIS DEL BIEN ADQUIRIDO - VIGENTE\n3. REGISTRO SANITARIO DEL BIEN ADQUIRIDO - VIGENTE";
    $relatedCustomer = $order->customerPurchaseOrders->first()?->customer;
    $relatedCustomerName = $relatedCustomer
        ? ($relatedCustomer->business_name
            ?? $relatedCustomer->full_name
            ?? trim(($relatedCustomer->first_name ?? '') . ' ' . ($relatedCustomer->last_name ?? '')))
        : null;
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Orden de compra {{ $displayCode }}</title>
    <style>
        @page { margin: 16px 20px; }
        * { box-sizing: border-box; }
        body {
            color: #111827;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8.3px;
            line-height: 1.18;
            margin: 0;
        }
        table { border-collapse: collapse; width: 100%; }
        .header-table td { vertical-align: top; }
        .company-box {
            border: 1px solid #cfd8d3;
            padding: 5px;
        }
        .company-box img {
            max-height: 36px;
            max-width: 140px;
            object-fit: contain;
        }
        .title-box {
            padding: 0 7px;
            text-align: center;
        }
        .title-box h1 {
            color: #08712f;
            font-size: 18px;
            line-height: 1;
            margin: 2px 0 7px;
            white-space: nowrap;
        }
        .destination-box {
            border: 1px solid #cfd8d3;
            text-align: center;
        }
        .destination-label {
            background: #e5e7eb;
            color: #111827;
            font-size: 11px;
            font-weight: 800;
            padding: 3px 4px;
        }
        .destination-value {
            color: #08712f;
            font-size: 18px;
            font-weight: 900;
            letter-spacing: .7px;
            padding: 7px 4px 8px;
        }
        .order-box td {
            border: 1px solid #cfd8d3;
            padding: 4px 5px;
        }
        .order-box .label {
            background: #eaf5ee;
            color: #14532d;
            font-weight: 800;
            width: 38%;
        }
        .section-grid {
            margin-top: 5px;
            width: 100%;
        }
        .section-grid td {
            vertical-align: top;
        }
        .col-gap { width: 7px; }
        .block {
            border: 1px solid #cfd8d3;
            min-height: 66px;
        }
        .block-title,
        .section-title {
            background: #08712f;
            color: #fff;
            font-size: 7.8px;
            font-weight: 800;
            padding: 3px 5px;
            text-transform: uppercase;
        }
        .block-body {
            padding: 5px 6px;
        }
        .gray-title {
            background: #e5e7eb;
            border: 1px solid #cfd8d3;
            border-bottom: 0;
            color: #111827;
            font-size: 8px;
            font-weight: 800;
            padding: 3px 5px;
            text-transform: uppercase;
        }
        .line { margin-bottom: 2px; }
        .label-text {
            color: #475569;
            font-size: 6.8px;
            text-transform: uppercase;
        }
        .value {
            color: #111827;
            font-size: 8.6px;
            font-weight: 800;
        }
        .summary,
        .items,
        .totals {
            width: 100%;
        }
        .summary td,
        .items th,
        .items td,
        .totals td {
            border: 1px solid #cfd8d3;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        .summary .label-text {
            display: block;
            line-height: 1;
            margin-bottom: 1px;
        }
        .items th {
            background: #08712f;
            color: #fff;
            font-size: 7px;
            padding: 3px 4px;
            text-transform: uppercase;
        }
        .items td { font-size: 7.4px; }
        .items tbody tr:nth-child(even) td { background: #f6fff8; }
        .description-main { font-weight: 800; }
        .description-meta { color: #374151; font-size: 7px; margin-top: 1px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bottom-table { margin-top: 5px; }
        .box {
            border: 1px solid #cfd8d3;
            min-height: 31px;
            padding: 5px;
            white-space: pre-line;
        }
        .totals td { padding: 4px 5px; }
        .totals .grand td {
            background: #dcfce7;
            color: #14532d;
            font-size: 10px;
            font-weight: 900;
        }
        .signature {
            border: 1px solid #cfd8d3;
            margin-top: 5px;
            min-height: 43px;
            padding: 5px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #111827;
            display: inline-block;
            margin-top: 12px;
            min-width: 160px;
            padding-top: 3px;
        }
        .note-block { margin-top: 5px; }
        .footer {
            border-top: 1px solid #e5e7eb;
            bottom: 0;
            color: #64748b;
            font-size: 6.8px;
            left: 0;
            padding-top: 3px;
            position: fixed;
            right: 0;
        }
    </style>
</head>

<body>
    <table class="header-table">
        <tr>
            <td width="32%">
                <div class="company-box">
                    @if (!empty($logoUrl))
                        <img src="{{ $logoUrl }}" alt="Dropaiv">
                    @endif
                    <div class="value">{{ $company?->business_name ?? 'DROPAIV S.A.C.' }}</div>
                    <div>RUC: {{ $company?->ruc ?? '-' }}</div>
                    <div>{{ $company?->address ?? '-' }}</div>
                    <div>Telefono: {{ $company?->phone ?? '-' }}</div>
                    <div>Correo: {{ $company?->email ?? $billingEmail }}</div>
                </div>
            </td>
            <td width="39%">
                <div class="title-box">
                    <h1>ORDEN DE COMPRA</h1>
                    <div class="destination-box">
                        <div class="destination-label">DESTINO</div>
                        <div class="destination-value">{{ $headlineDestination }}</div>
                    </div>
                </div>
            </td>
            <td width="29%">
                <table class="order-box">
                    <tr><td class="label">Transporte</td><td class="value">{{ $optionLabel($order->transport_type) }}</td></tr>
                    <tr><td class="label">Fecha</td><td class="value">{{ $order->created_at?->format('d/m/Y') }}</td></tr>
                    <tr><td class="label">Nro. Orden</td><td class="value">{{ $displayCode }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="section-grid">
        <tr>
            <td width="49%">
                <div class="block">
                    <div class="block-title">Proveedor</div>
                    <div class="block-body">
                        <div class="line value">{{ $supplier?->business_name ?? '-' }}</div>
                        <div class="line"><span class="label-text">RUC:</span> {{ $supplier?->ruc ?? '-' }}</div>
                        <div class="line"><span class="label-text">Direccion:</span> {{ $supplier?->address ?? '-' }}</div>
                        <div class="line"><span class="label-text">Contacto:</span> {{ $supplier?->contact_name ?? '-' }}</div>
                        <div class="line"><span class="label-text">Telefono:</span> {{ $supplier?->phone ?? '-' }}</div>
                    </div>
                </div>
            </td>
            <td class="col-gap"></td>
            <td width="49%">
                <div class="block">
                    <div class="block-title">Direccion de entrega - Ag. Transportes</div>
                    <div class="block-body">
                        @if ($shippingAgency)
                            <div class="line value">{{ $shippingAgency->trade_name ?? $shippingAgency->business_name ?? '-' }}</div>
                            <div class="line"><span class="label-text">RUC:</span> {{ $shippingAgency->ruc ?? '-' }}</div>
                            <div class="line"><span class="label-text">Direccion sede:</span> {{ $shippingBranch?->address ?? '-' }}</div>
                            <div class="line"><span class="label-text">Ubicacion:</span> {{ $agencyLocation ?: '-' }}</div>
                            <div class="line"><span class="label-text">Referencia:</span> {{ $order->shipping_reference ?: ($shippingBranch?->reference ?? '-') }}</div>
                            <div class="line"><span class="label-text">Contacto:</span> {{ $shippingContact?->contact_name ?? '-' }}</div>
                            <div class="line"><span class="label-text">Telefono / WhatsApp:</span> {{ $agencyPhones ?: '-' }}</div>
                            <div class="line"><span class="label-text">Correo:</span> {{ $shippingContact?->email ?? '-' }}</div>
                        @else
                            <div class="value">Sin agencia de envio seleccionada</div>
                        @endif
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="section-grid">
        <tr>
            <td width="49%">
                <div class="block">
                    <div class="block-title">Datos de facturacion</div>
                    <div class="block-body">
                        <div class="line value">{{ $company?->business_name ?? 'DROPAIV SAC' }}</div>
                        <div class="line"><span class="label-text">RUC:</span> {{ $company?->ruc ?? '-' }}</div>
                        <div class="line"><span class="label-text">Direccion fiscal:</span> {{ $company?->address ?? '-' }}</div>
                        <div class="line"><span class="label-text">Factura enviar a correo:</span> {{ $billingEmail }}</div>
                    </div>
                </div>
            </td>
            <td class="col-gap"></td>
            <td width="49%">
                <div class="block">
                    <div class="block-title">Datos de envio</div>
                    <div class="block-body">
                        <div class="line"><span class="label-text">Direccion final:</span> {{ $order->shipping_address ?: '-' }}</div>
                        <div class="line"><span class="label-text">Destino / ciudad:</span> {{ $destinationDetail }}</div>
                        <div class="line"><span class="label-text">Cliente o entidad relacionada:</span> {{ $relatedCustomerName ?: '-' }}</div>
                        <div class="line"><span class="label-text">Referencia de envio:</span> {{ $order->shipping_reference ?: '-' }}</div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-title" style="margin-top:5px;">Resumen interno</div>
    <table class="summary">
        <tr>
            <td width="31%"><span class="label-text">Delivery</span><span class="value">{{ $deliveryText }}</span></td>
            <td width="33%"><span class="label-text">Terminos de pago</span><span class="value">{{ $paymentTerms }}</span></td>
            <td width="18%"><span class="label-text">Solicitado por</span><span class="value">{{ $requestedBy }}</span></td>
            <td width="18%"><span class="label-text">Departamento</span><span class="value">{{ $departmentText }}</span></td>
        </tr>
    </table>

    <div class="section-title" style="margin-top:5px;">Detalle de articulos</div>
    <table class="items">
        <thead>
            <tr>
                <th width="7%">Cod.</th>
                <th width="33%">Descripcion</th>
                <th width="7%">Cant.</th>
                <th width="7%">Und.</th>
                <th width="10%">P. Unit.</th>
                <th width="10%">P. Total IGV</th>
                <th width="10%">B. Imponible</th>
                <th width="7%">% IGV</th>
                <th width="9%">IGV</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($order->items as $item)
                @php
                    $itemTotalWithIgv = $pdfLineTotal($item);
                    $itemTaxableBase = $pdfTaxableBase($item);
                    $itemIgvAmount = $pdfIgvAmount($item);
                @endphp
                <tr>
                    <td>{{ $item->article_code ?: '-' }}</td>
                    <td>
                        <div class="description-main">{{ $item->billing_name_snapshot ?: '-' }}</div>
                        @if ($item->presentation?->description)
                            <div class="description-meta">Presentacion: {{ $item->presentation->description }}</div>
                        @endif
                        @if ($item->brand?->description || $item->origin || $item->expiration_date)
                            <div class="description-meta">
                                {{ collect([
                                    $item->brand?->description ? 'Marca: ' . $item->brand->description : null,
                                    $item->origin ? 'Procedencia: ' . $item->origin : null,
                                    $item->expiration_date ? 'Vcto: ' . $formatDate($item->expiration_date) : null,
                                ])->filter()->join(' | ') }}
                            </div>
                        @endif
                        @if ($item->note)
                            <div class="description-meta">Nota: {{ $item->note }}</div>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td>{{ $item->unit?->abbreviation ?? $item->unit?->description ?? '-' }}</td>
                    <td class="text-right">{{ $formatDecimal($item->unit_price) }}</td>
                    <td class="text-right">{{ $formatDecimal($itemTotalWithIgv) }}</td>
                    <td class="text-right">{{ $formatDecimal($itemTaxableBase) }}</td>
                    <td class="text-right">{{ $formatDecimal($item->igv_percent ?? ($order->affect_igv ? 18 : 0), 2) }}%</td>
                    <td class="text-right">{{ $formatDecimal($itemIgvAmount) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center">Sin articulos registrados</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="bottom-table">
        <tr>
            <td width="61%" style="vertical-align:top;">
                <div class="gray-title">Instrucciones</div>
                <div class="box">{{ $purchaseInstructions }}</div>
            </td>
            <td class="col-gap"></td>
            <td width="37%" style="vertical-align:top;">
                <table class="totals">
                    <tr><td>Base imponible</td><td class="text-right">{{ $formatMoney($pdfSubtotal) }}</td></tr>
                    <tr><td>IGV {{ $order->affect_igv ? '18%' : '0%' }}</td><td class="text-right">{{ $formatMoney($pdfIgv) }}</td></tr>
                    <tr class="grand"><td>TOTAL</td><td class="text-right">{{ $formatMoney($pdfGrandTotal) }}</td></tr>
                </table>
                <div class="signature">
                    <div>Autorizado por</div>
                    <div class="signature-line">
                        <strong>{{ $order->authorized_by_name ?: 'IVAN CUBAS BINCES' }}</strong><br>
                        <span>{{ $order->authorized_by_position ?: 'GERENTE GENERAL' }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="note-block">
        <div class="gray-title">Nota importante</div>
        <div class="box">{{ $importantNote }}</div>
    </div>

    <div class="footer">
        Documento generado automaticamente por DropaivSys2 |
        {{ now()->format('d/m/Y H:i') }} |
        orden_compra_proveedor_{{ $displayCode }}.pdf
    </div>
</body>

</html>
