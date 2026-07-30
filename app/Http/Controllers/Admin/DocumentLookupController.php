<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DocumentLookupService;
use Illuminate\Http\JsonResponse;

class DocumentLookupController extends Controller
{
    public function __construct(private readonly DocumentLookupService $documentLookup)
    {
    }

    public function ruc(string $ruc): JsonResponse
    {
        return $this->respond($this->documentLookup->searchRuc($ruc));
    }

    public function dni(string $dni): JsonResponse
    {
        return $this->respond($this->documentLookup->searchDni($dni));
    }

    private function respond(array $result): JsonResponse
    {
        if ($result['success'] ?? false) {
            return response()->json($result);
        }

        $status = match ($result['code'] ?? 'API_ERROR') {
            'INVALID_RUC', 'INVALID_DNI' => 422,
            'RUC_NOT_FOUND', 'DNI_NOT_FOUND' => 404,
            default => 503,
        };

        return response()->json($result, $status);
    }
}
