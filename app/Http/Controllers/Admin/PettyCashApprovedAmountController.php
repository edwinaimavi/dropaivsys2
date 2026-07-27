<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PettyCashApprovedAmount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PettyCashApprovedAmountController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.petty-cash.approved-amount.index')->only('active');
        $this->middleware('can:admin.petty-cash.approved-amount.update')->only(['show', 'update']);
    }

    public function active(Request $request)
    {
        $validated = $this->validateKey($request);
        $approvedAmount = PettyCashApprovedAmount::query()
            ->with(['currency:id,code,symbol', 'approvedBy:id,name,lastname'])
            ->where($validated)
            ->where('active', true)
            ->first();

        if (! $approvedAmount) {
            return response()->json([
                'status' => 'empty',
                'message' => 'No hay monto aprobado asignado.',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->payload($approvedAmount),
        ]);
    }

    public function show(Request $request)
    {
        $validated = $this->validateKey($request);
        $approvedAmount = PettyCashApprovedAmount::withTrashed()
            ->with([
                'company:id,business_name,trade_name',
                'currency:id,code,symbol',
                'approvedBy:id,name,lastname',
                'approvedAmountHistories' => fn ($query) => $query
                    ->with('approvedBy:id,name,lastname')
                    ->latest('approved_at')
                    ->latest('id'),
            ])
            ->where($validated)
            ->first();

        return response()->json([
            'status' => $approvedAmount ? 'success' : 'empty',
            'data' => $approvedAmount ? $this->payload($approvedAmount) : null,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'currency_id' => [
                'required',
                'integer',
                Rule::exists('currencies', 'id')->where(fn ($query) => $query
                    ->where('status', 'ACTIVE')
                    ->whereNull('deleted_at')),
            ],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'active' => ['required', 'boolean'],
            'observation' => ['nullable', 'string', 'max:2000'],
        ], [
            'company_id.required' => 'La empresa es obligatoria.',
            'currency_id.required' => 'La moneda es obligatoria.',
            'amount.required' => 'El monto aprobado es obligatorio.',
            'amount.min' => 'El monto aprobado debe ser mayor a cero.',
        ]);

        $approvedAmount = DB::transaction(function () use ($validated) {
            $approvedAmount = PettyCashApprovedAmount::withTrashed()
                ->where('company_id', $validated['company_id'])
                ->where('currency_id', $validated['currency_id'])
                ->lockForUpdate()
                ->first() ?? new PettyCashApprovedAmount([
                    'company_id' => $validated['company_id'],
                    'currency_id' => $validated['currency_id'],
                ]);
            $previousAmount = $approvedAmount->exists ? $approvedAmount->amount : null;
            $approvedAt = now();
            $approvedBy = Auth::id();

            $approvedAmount->fill([
                'amount' => round((float) $validated['amount'], 2),
                'approved_at' => $approvedAt,
                'approved_by_user_id' => $approvedBy,
                'active' => $validated['active'],
                'observation' => $validated['observation'] ?? null,
                'updated_by' => $approvedBy,
            ]);
            if (! $approvedAmount->exists) {
                $approvedAmount->created_by = $approvedBy;
            }
            if ($approvedAmount->trashed()) {
                $approvedAmount->restore();
            }
            $approvedAmount->save();
            $approvedAmount->approvedAmountHistories()->create([
                'previous_amount' => $previousAmount,
                'approved_amount' => $approvedAmount->amount,
                'currency' => $approvedAmount->currency()->value('code'),
                'approved_by_user_id' => $approvedBy,
                'approved_at' => $approvedAt,
                'notes' => $approvedAmount->observation,
            ]);

            return $approvedAmount;
        });
        $approvedAmount->load(['currency:id,code,symbol', 'approvedBy:id,name,lastname']);

        return response()->json([
            'message' => 'Monto aprobado actualizado correctamente.',
            'data' => $this->payload($approvedAmount),
        ]);
    }

    private function validateKey(Request $request): array
    {
        return $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
        ]);
    }

    private function payload(PettyCashApprovedAmount $approvedAmount): array
    {
        $symbol = $approvedAmount->currency?->symbol ?: $approvedAmount->currency?->code;

        return [
            'id' => $approvedAmount->id,
            'company_id' => $approvedAmount->company_id,
            'currency_id' => $approvedAmount->currency_id,
            'amount' => $approvedAmount->amount,
            'formatted_amount' => trim($symbol . ' ' . number_format((float) $approvedAmount->amount, 2)),
            'active' => $approvedAmount->active,
            'observation' => $approvedAmount->observation,
            'approved_at' => $approvedAmount->approved_at?->format('d/m/Y H:i'),
            'approved_by_user_id' => $approvedAmount->approved_by_user_id,
            'approved_by' => $approvedAmount->approvedBy
                ? trim($approvedAmount->approvedBy->name . ' ' . $approvedAmount->approvedBy->lastname)
                : null,
            'company' => $approvedAmount->relationLoaded('company') && $approvedAmount->company
                ? ($approvedAmount->company->trade_name ?: $approvedAmount->company->business_name)
                : null,
            'currency' => $approvedAmount->currency?->code,
            'history' => $approvedAmount->relationLoaded('approvedAmountHistories')
                ? $approvedAmount->approvedAmountHistories->map(fn ($history) => [
                    'id' => $history->id,
                    'previous_amount' => $history->previous_amount,
                    'approved_amount' => $history->approved_amount,
                    'currency' => $history->currency,
                    'approved_by' => $history->approvedBy
                        ? trim($history->approvedBy->name . ' ' . $history->approvedBy->lastname)
                        : 'Usuario no disponible',
                    'approved_at' => $history->approved_at?->format('d/m/Y H:i'),
                    'notes' => $history->notes,
                ])->values()
                : [],
        ];
    }
}
