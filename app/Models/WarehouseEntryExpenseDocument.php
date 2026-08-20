<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseEntryExpenseDocument extends Model
{
    public const TYPE_INVOICE = 'invoice';

    public const TYPE_PAYMENT_PROOF = 'payment_proof';

    public const TYPE_DETRACTION_PROOF = 'detraction_proof';

    protected $fillable = ['warehouse_entry_expense_id', 'source_document_id', 'source_context', 'document_type', 'description', 'file_path', 'original_name', 'mime_type', 'file_size', 'status', 'created_by', 'updated_by'];

    protected $casts = ['file_size' => 'integer'];

    public static function normalizeType(?string $type): string
    {
        $normalized = strtolower(trim((string) $type));

        return in_array($normalized, [
            self::TYPE_PAYMENT_PROOF,
            self::TYPE_DETRACTION_PROOF,
        ], true) ? $normalized : self::TYPE_INVOICE;
    }

    public function expense() { return $this->belongsTo(WarehouseEntryExpense::class, 'warehouse_entry_expense_id'); }
    public function sourceDocument() { return $this->belongsTo(Document::class, 'source_document_id'); }
}
