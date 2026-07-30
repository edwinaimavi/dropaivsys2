<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class DocumentLookupService
{
    /**
     * Compatibilidad temporal para controladores internos antiguos.
     * Los consumidores nuevos deben usar searchRuc().
     */
    public function lookupRuc(string $ruc): array
    {
        $result = $this->searchRuc($ruc);

        if (!($result['success'] ?? false)) {
            $status = in_array($result['code'] ?? '', ['RUC_NOT_FOUND'], true) ? 404 : 503;
            throw new DocumentLookupException($result['message'], $status);
        }

        return $result;
    }

    public function searchRuc(string $ruc): array
    {
        if (!preg_match('/^\d{11}$/', $ruc)) {
            return $this->error('El RUC debe tener 11 dígitos.', 'INVALID_RUC');
        }

        $result = $this->request('ruc', $ruc);
        if (isset($result['error'])) {
            return $result['error'];
        }

        $data = $result['data'];
        $businessName = $this->first($data, ['razonSocial', 'razon_social', 'nombre', 'nombre_o_razon_social']);

        if ($businessName === '') {
            return $this->error(
                'La consulta respondió, pero no devolvió una razón social. Complete los datos manualmente.',
                'INCOMPLETE_RESPONSE'
            );
        }

        $document = $this->first($data, ['ruc', 'numeroDocumento', 'numero_documento'], $ruc);
        $commercialName = $this->first($data, ['nombreComercial', 'nombre_comercial']);
        $address = $this->first($data, ['direccion', 'domicilioFiscal', 'domicilio_fiscal']);
        $department = $this->first($data, ['departamento']);
        $province = $this->first($data, ['provincia']);
        $district = $this->first($data, ['distrito']);
        $status = $this->first($data, ['estado']);
        $condition = $this->first($data, ['condicion']);

        return [
            'success' => true,
            'status' => true,
            'type' => 'RUC',
            'document' => $document,
            'ruc' => $document,
            'business_name' => $businessName,
            'razon_social' => $businessName,
            'razonSocial' => $businessName,
            'nombre' => $businessName,
            'commercial_name' => $commercialName,
            'nombre_comercial' => $commercialName,
            'nombreComercial' => $commercialName,
            'address' => $address,
            'direccion' => $address,
            'department' => $department,
            'departamento' => $department,
            'province' => $province,
            'provincia' => $province,
            'district' => $district,
            'distrito' => $district,
            'ubigeo' => $this->first($data, ['ubigeo']),
            'status_text' => $status,
            'estado' => $status,
            'condition' => $condition,
            'condicion' => $condition,
            'telefonos' => $data['telefonos'] ?? [],
            'raw' => $data,
            'data' => $data,
        ];
    }

    public function searchDni(string $dni): array
    {
        if (!preg_match('/^\d{8}$/', $dni)) {
            return $this->error('El DNI debe tener 8 dígitos.', 'INVALID_DNI');
        }

        $result = $this->request('dni', $dni);
        if (isset($result['error'])) {
            return $result['error'];
        }

        $data = $result['data'];
        $names = $this->first($data, ['nombres', 'names']);
        $paternalLastname = $this->first($data, ['apellidoPaterno', 'apellido_paterno', 'paternal_lastname']);
        $maternalLastname = $this->first($data, ['apellidoMaterno', 'apellido_materno', 'maternal_lastname']);

        if ($names === '' && $paternalLastname === '' && $maternalLastname === '') {
            return $this->error(
                'La consulta respondió, pero no devolvió nombres. Complete los datos manualmente.',
                'INCOMPLETE_RESPONSE'
            );
        }

        $document = $this->first($data, ['dni', 'numeroDocumento', 'numero_documento'], $dni);
        $fullName = trim($names.' '.$paternalLastname.' '.$maternalLastname);

        return [
            'success' => true,
            'status' => true,
            'type' => 'DNI',
            'document' => $document,
            'dni' => $document,
            'names' => $names,
            'nombres' => $names,
            'paternal_lastname' => $paternalLastname,
            'apellido_paterno' => $paternalLastname,
            'apellidoPaterno' => $paternalLastname,
            'maternal_lastname' => $maternalLastname,
            'apellido_materno' => $maternalLastname,
            'apellidoMaterno' => $maternalLastname,
            'full_name' => $fullName,
            'nombre_completo' => $fullName,
            'cod_verifica' => $this->first($data, ['codVerifica', 'cod_verifica']),
            'raw' => $data,
            'data' => $data,
        ];
    }

    private function request(string $type, string $document): array
    {
        $token = trim((string) config('services.apisperu.token'));
        $baseUrl = rtrim((string) config('services.apisperu.base_url'), '/');

        if ($token === '' || $baseUrl === '') {
            return ['error' => $this->error(
                'El servicio de consulta no está configurado. Complete los datos manualmente.',
                'SERVICE_NOT_CONFIGURED'
            )];
        }

        try {
            $response = Http::timeout((int) config('services.apisperu.timeout', 15))
                ->acceptJson()
                ->get($baseUrl.'/'.$type.'/'.$document, ['token' => $token]);
        } catch (ConnectionException) {
            return ['error' => $this->error(
                'El servicio de consulta no respondió. Complete los datos manualmente.',
                'CONNECTION_ERROR'
            )];
        } catch (Throwable) {
            return ['error' => $this->error(
                'No se pudo consultar el documento. Complete los datos manualmente.',
                'API_ERROR'
            )];
        }

        if (!$response->successful()) {
            return ['error' => $this->responseError($response, strtoupper($type))];
        }

        try {
            $data = $response->json();
        } catch (Throwable) {
            $data = null;
        }

        if (!is_array($data)) {
            return ['error' => $this->error(
                'La API devolvió una respuesta inválida. Complete los datos manualmente.',
                'INVALID_RESPONSE'
            )];
        }

        if (($data['success'] ?? true) === false) {
            return ['error' => $this->notFound(strtoupper($type))];
        }

        return ['data' => $data];
    }

    private function responseError(Response $response, string $type): array
    {
        return match ($response->status()) {
            401, 403 => $this->error(
                'No se pudo autorizar la consulta. Complete los datos manualmente.',
                'AUTH_ERROR'
            ),
            404 => $this->notFound($type),
            422 => $this->error("El {$type} ingresado no es válido.", "INVALID_{$type}"),
            default => $this->error(
                'No se pudo consultar el documento. Complete los datos manualmente.',
                'API_ERROR'
            ),
        };
    }

    private function notFound(string $type): array
    {
        return $this->error(
            "El {$type} ingresado no existe o no fue encontrado.",
            "{$type}_NOT_FOUND"
        );
    }

    private function error(string $message, string $code): array
    {
        return [
            'success' => false,
            'status' => false,
            'message' => $message,
            'code' => $code,
        ];
    }

    private function first(array $data, array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                $value = trim((string) $data[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return $default;
    }
}
