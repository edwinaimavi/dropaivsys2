<?php

namespace App\Services\ElectronicBilling;

use App\Models\ElectronicInvoice;
use App\Models\ElectronicInvoiceSetting;

class ApiPeruBillingService
{
    public function activeConfiguration(ElectronicInvoice $invoice): ?ElectronicInvoiceSetting
    {
        $environment = $invoice->electronicSeries?->environment;

        return ElectronicInvoiceSetting::query()
            ->where('company_id', $invoice->company_id)
            ->where('provider', 'apisperu')
            ->where('is_active', true)
            ->when($environment, fn ($query) => $query->where('environment', $environment))
            ->latest('id')
            ->first();
    }

    public function canSendToApi(ElectronicInvoice $invoice): bool
    {
        $setting = $this->activeConfiguration($invoice);

        return $setting !== null
            && filled($setting->api_base_url)
            && filled($setting->api_token);
    }

    public function externalStatus(ElectronicInvoice $invoice): string
    {
        return $this->canSendToApi($invoice) ? 'pending_send' : 'not_configured';
    }

    public function send(ElectronicInvoice $invoice): array
    {
        if (! $this->canSendToApi($invoice)) {
            return [
                'status' => 'not_configured',
                'message' => 'API de facturación aún no configurada.',
            ];
        }

        return [
            'status' => 'pending_send',
            'message' => 'La configuración está lista. El envío real a APIs Perú aún no está habilitado.',
        ];
    }

    public function buildPayload(ElectronicInvoice $invoice): array
    {
        $invoice->loadMissing('items', 'payments', 'legends', 'relatedDocuments');

        return [
            'ublVersion' => '2.1',
            'tipoOperacion' => $invoice->operation_type,
            'tipoDoc' => $invoice->document_type,
            'serie' => $invoice->serie,
            'correlativo' => $invoice->correlativo,
            'fechaEmision' => optional($invoice->issue_date)->format('Y-m-d'),
            'formaPago' => ['moneda' => $invoice->currency_code, 'tipo' => $invoice->payment_type],
            'tipoMoneda' => $invoice->currency_code,
            'client' => [
                'tipoDoc' => $invoice->client_document_type,
                'numDoc' => $invoice->client_document_number,
                'rznSocial' => $invoice->client_name,
                'address' => [
                    'direccion' => $invoice->client_address,
                    'ubigueo' => $invoice->client_ubigeo,
                    'departamento' => $invoice->client_department,
                    'provincia' => $invoice->client_province,
                    'distrito' => $invoice->client_district,
                ],
            ],
            'company' => [
                'ruc' => $invoice->company_ruc,
                'razonSocial' => $invoice->company_business_name,
                'nombreComercial' => $invoice->company_trade_name,
                'address' => [
                    'direccion' => $invoice->company_address,
                    'ubigueo' => $invoice->company_ubigeo,
                    'departamento' => $invoice->company_department,
                    'provincia' => $invoice->company_province,
                    'distrito' => $invoice->company_district,
                ],
            ],
            'mtoOperGravadas' => (float) $invoice->taxable_amount,
            'mtoOperExoneradas' => (float) $invoice->exonerated_amount,
            'mtoOperInafectas' => (float) $invoice->unaffected_amount,
            'mtoIGV' => (float) $invoice->igv_amount,
            'totalImpuestos' => (float) $invoice->total_taxes,
            'valorVenta' => (float) $invoice->subtotal,
            'subTotal' => (float) $invoice->total_amount,
            'mtoImpVenta' => (float) $invoice->total_amount,
            'details' => $invoice->items->map(fn ($item) => [
                'codProducto' => $item->product_code,
                'unidad' => $item->unit_code,
                'descripcion' => $item->description,
                'cantidad' => (float) $item->quantity,
                'mtoValorUnitario' => (float) $item->unit_value,
                'mtoValorVenta' => (float) $item->subtotal,
                'mtoBaseIgv' => (float) $item->igv_base,
                'porcentajeIgv' => (float) $item->igv_percentage,
                'igv' => (float) $item->igv_amount,
                'tipAfeIgv' => $item->tax_affectation_code,
                'totalImpuestos' => (float) $item->total_taxes,
                'mtoPrecioUnitario' => (float) $item->unit_price,
            ])->values(),
            'legends' => $invoice->legends->map(fn ($legend) => [
                'code' => $legend->code,
                'value' => $legend->value,
            ])->values(),
        ];
    }
}
