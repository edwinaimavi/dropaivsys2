<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SunatCatalogItem;
use Yajra\DataTables\Facades\DataTables;

class ElectronicInvoiceCatalogController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.sunat-catalogs.index')->only(['index', 'list']);
    }

    public function index()
    {
        return view('admin.sunat-catalogs.index');
    }

    public function list()
    {
        return DataTables::of(SunatCatalogItem::query()->orderBy('catalog_code')->orderBy('item_code'))
            ->editColumn('status', fn (SunatCatalogItem $item) =>
                $item->status === 'ACTIVE'
                    ? '<span class="badge badge-success">Activo</span>'
                    : '<span class="badge badge-secondary">Inactivo</span>')
            ->rawColumns(['status'])
            ->make(true);
    }
}
