<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ElectronicInvoiceApiLog;
use Yajra\DataTables\Facades\DataTables;

class ElectronicInvoiceApiLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.electronic-invoices.index')->only(['list', 'show']);
    }

    public function list()
    {
        return DataTables::of(ElectronicInvoiceApiLog::query()->with('invoice:id,full_number', 'executor:id,name')->orderByDesc('id'))
            ->addColumn('invoice_number', fn (ElectronicInvoiceApiLog $log) => $log->invoice?->full_number ?? '-')
            ->addColumn('executor_name', fn (ElectronicInvoiceApiLog $log) => $log->executor?->name ?? '-')
            ->editColumn('success', fn (ElectronicInvoiceApiLog $log) =>
                $log->success
                    ? '<span class="badge badge-success">OK</span>'
                    : '<span class="badge badge-danger">No ejecutado</span>')
            ->rawColumns(['success'])
            ->make(true);
    }

    public function show(ElectronicInvoiceApiLog $electronicInvoiceApiLog)
    {
        return response()->json([
            'status' => 'success',
            'data' => $electronicInvoiceApiLog->load('invoice', 'executor'),
        ]);
    }
}
