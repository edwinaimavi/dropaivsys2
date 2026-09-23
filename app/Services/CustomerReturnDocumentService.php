<?php

namespace App\Services;

use App\Models\CustomerReturn;
use App\Models\Document;
use App\Models\DocumentType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerReturnDocumentService
{
    public const MAX_FILES_PER_REQUEST = 10;
    public const MAX_FILE_SIZE_KB = 10240;
    public const ALLOWED_EXTENSIONS = 'pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx';

    private const TYPES = [
        'return_guide' => ['code' => 'CR_GUIDE', 'label' => 'Guía de devolución'],
        'return_record' => ['code' => 'CR_RECORD', 'label' => 'Acta / cargo'],
        'customer_communication' => ['code' => 'CR_CUSTOMER', 'label' => 'Comunicación del cliente'],
        'credit_note' => ['code' => 'CR_CREDIT_NOTE', 'label' => 'Nota de crédito'],
        'photo' => ['code' => 'CR_PHOTO', 'label' => 'Foto'],
        'other' => ['code' => 'CR_OTHER', 'label' => 'Otro'],
    ];

    public function types(): array
    {
        return collect(self::TYPES)->map(fn (array $type) => $type['label'])->all();
    }

    public function typeKeys(): array
    {
        return array_keys(self::TYPES);
    }

    public function activeDocuments(CustomerReturn $return): Collection
    {
        return $return->documents()->where('status', 'ACTIVE')
            ->with(['documentType', 'creator'])->latest('id')->get();
    }

    public function storeMany(CustomerReturn $return, array $documents, ?int $userId): Collection
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($return, $documents, $userId, &$storedPaths) {
                $created = collect();
                foreach ($documents as $index => $data) {
                    $file = $data['file'] ?? null;
                    $type = (string) ($data['type'] ?? '');
                    if (! $file instanceof UploadedFile || ! $file->isValid()) {
                        throw ValidationException::withMessages(["documents.$index.file" => 'Seleccione un archivo válido.']);
                    }
                    if (! isset(self::TYPES[$type])) {
                        throw ValidationException::withMessages(["documents.$index.type" => 'Seleccione un tipo de documento válido.']);
                    }
                    $extension = strtolower($file->getClientOriginalExtension());
                    $storedName = Str::uuid().($extension ? '.'.$extension : '');
                    $storedPath = $file->storeAs("customer-returns/{$return->id}/documents", $storedName, 'public');
                    if (! $storedPath) {
                        throw ValidationException::withMessages(["documents.$index.file" => 'No se pudo almacenar el documento.']);
                    }
                    $storedPaths[] = $storedPath;
                    $documentType = $this->resolveType($type, $userId);
                    $created->push($return->documents()->create([
                        'document_type_id' => $documentType->id,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_name' => $storedName,
                        'file_path' => $storedPath,
                        'mime_type' => $file->getMimeType(),
                        'extension' => $extension,
                        'file_size' => $file->getSize() ?: 0,
                        'observation' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
                        'status' => 'ACTIVE', 'created_by' => $userId, 'updated_by' => $userId,
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

    public function remove(CustomerReturn $return, Document $document, ?int $userId): void
    {
        $this->ensureBelongsToReturn($return, $document);
        DB::transaction(function () use ($document, $userId) {
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['status' => 'INACTIVE', 'updated_by' => $userId, 'deleted_by' => $userId])->save();
            $locked->delete();
        });
    }

    public function ensureBelongsToReturn(CustomerReturn $return, Document $document): void
    {
        abort_unless($document->documentable_type === CustomerReturn::class
            && (int) $document->documentable_id === (int) $return->id, 404);
    }

    public function typeKey(Document $document): string
    {
        $code = $document->documentType?->code;
        foreach (self::TYPES as $key => $type) {
            if ($type['code'] === $code) return $key;
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
            'observation' => 'Documento de devolución de cliente',
            'status' => 'ACTIVE', 'updated_by' => $userId,
        ]);
        if (! $documentType->exists) $documentType->created_by = $userId;
        $documentType->deleted_by = null;
        $documentType->deleted_at = null;
        $documentType->save();
        return $documentType;
    }
}
