<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\WarehouseDispatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WarehouseDispatchDocumentService
{
    public const MAX_FILES_PER_REQUEST = 10;

    public const MAX_FILE_SIZE_KB = 10240;

    public const ALLOWED_EXTENSIONS = 'pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx';

    private const TYPES = [
        'dispatch_guide' => ['code' => 'WD_GUIDE', 'label' => 'Guía de remisión'],
        'delivery_receipt' => ['code' => 'WD_DELIVERY', 'label' => 'Constancia de entrega'],
        'signed_receipt' => ['code' => 'WD_SIGNED', 'label' => 'Cargo firmado'],
        'transport_document' => ['code' => 'WD_TRANSPORT', 'label' => 'Documento de transporte'],
        'photo_evidence' => ['code' => 'WD_PHOTO', 'label' => 'Foto / evidencia'],
        'dispatch_record' => ['code' => 'WD_RECORD', 'label' => 'Acta'],
        'other' => ['code' => 'WD_OTHER', 'label' => 'Otro'],
    ];

    public function types(): array
    {
        return collect(self::TYPES)
            ->map(fn (array $type) => $type['label'])
            ->all();
    }

    public function typeKeys(): array
    {
        return array_keys(self::TYPES);
    }

    public function activeDocuments(WarehouseDispatch $dispatch): Collection
    {
        return $dispatch->documents()
            ->where('status', 'ACTIVE')
            ->with(['documentType', 'creator'])
            ->latest('id')
            ->get();
    }

    public function storeMany(WarehouseDispatch $dispatch, array $documents, ?int $userId): Collection
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($dispatch, $documents, $userId, &$storedPaths) {
                $created = collect();

                foreach ($documents as $index => $data) {
                    $file = $data['file'] ?? null;
                    $type = (string) ($data['type'] ?? '');

                    if (! $file instanceof UploadedFile || ! $file->isValid()) {
                        throw ValidationException::withMessages([
                            "documents.$index.file" => 'Seleccione un archivo válido.',
                        ]);
                    }
                    if (! isset(self::TYPES[$type])) {
                        throw ValidationException::withMessages([
                            "documents.$index.type" => 'Seleccione un tipo de documento válido.',
                        ]);
                    }

                    $extension = strtolower($file->getClientOriginalExtension());
                    $storedName = Str::uuid().($extension ? '.'.$extension : '');
                    $storedPath = $file->storeAs(
                        "warehouse-dispatches/{$dispatch->id}/documents",
                        $storedName,
                        'public'
                    );

                    if (! $storedPath) {
                        throw ValidationException::withMessages([
                            "documents.$index.file" => 'No se pudo almacenar el documento.',
                        ]);
                    }

                    $storedPaths[] = $storedPath;
                    $documentType = $this->resolveType($type, $userId);
                    $created->push($dispatch->documents()->create([
                        'document_type_id' => $documentType->id,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_name' => $storedName,
                        'file_path' => $storedPath,
                        'mime_type' => $file->getMimeType(),
                        'extension' => $extension,
                        'file_size' => $file->getSize() ?: 0,
                        'observation' => filled($data['description'] ?? null)
                            ? trim((string) $data['description'])
                            : null,
                        'status' => 'ACTIVE',
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]));
                }

                return $created;
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }
    }

    public function remove(
        WarehouseDispatch $dispatch,
        Document $document,
        ?int $userId
    ): void {
        $this->ensureBelongsToDispatch($dispatch, $document);

        DB::transaction(function () use ($document, $userId) {
            $document = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $document->forceFill([
                'status' => 'INACTIVE',
                'updated_by' => $userId,
                'deleted_by' => $userId,
            ])->save();
            $document->delete();
        });
    }

    public function ensureBelongsToDispatch(WarehouseDispatch $dispatch, Document $document): void
    {
        abort_unless(
            $document->documentable_type === WarehouseDispatch::class
                && (int) $document->documentable_id === (int) $dispatch->id,
            404
        );
    }

    public function typeKey(Document $document): string
    {
        $code = $document->documentType?->code;

        foreach (self::TYPES as $key => $type) {
            if ($type['code'] === $code) {
                return $key;
            }
        }

        return 'other';
    }

    public function typeLabel(Document $document): string
    {
        return self::TYPES[$this->typeKey($document)]['label'];
    }

    private function resolveType(string $type, ?int $userId): DocumentType
    {
        $definition = self::TYPES[$type];
        $documentType = DocumentType::withTrashed()->firstOrNew(['code' => $definition['code']]);
        $documentType->fill([
            'description' => mb_strtoupper($definition['label']),
            'observation' => 'Documento de salida de almacén',
            'status' => 'ACTIVE',
            'updated_by' => $userId,
        ]);
        if (! $documentType->exists) {
            $documentType->created_by = $userId;
        }
        $documentType->deleted_by = null;
        $documentType->deleted_at = null;
        $documentType->save();

        return $documentType;
    }
}
