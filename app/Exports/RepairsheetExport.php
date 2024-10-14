<?php

namespace App\Exports;

use App\Models\Process_model;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RepairsheetExport implements FromCollection, WithHeadings
{
    protected $repair_batches;

    public function __construct()
    {
        // Storing reference_id as key and id as value for repair_batches
        $this->repair_batches = Process_model::where('process_type_id', 9)
            ->whereNot('id', request('id'))
            ->pluck('reference_id', 'id')
            ->toArray();
    }

    public function collection()
    {
        $repair_batches = $this->repair_batches;
        $data = DB::table('process')
            ->leftJoin('process_stock as p_stock', 'process.id', '=', 'p_stock.process_id')
            ->leftJoin('admin', 'p_stock.admin_id', '=', 'admin.id')
            ->leftJoin('stock', 'p_stock.stock_id', '=', 'stock.id')
            ->leftJoin('orders', 'stock.order_id', '=', 'orders.id')
            ->leftJoin('variation', 'stock.variation_id', '=', 'variation.id')
            ->leftJoin('products', 'variation.product_id', '=', 'products.id')
            ->leftJoin('color', 'variation.color', '=', 'color.id')
            ->leftJoin('process_stock', function ($join) use ($repair_batches) {
                $join->on('stock.id', '=', 'process_stock.stock_id')
                    ->whereNull('process_stock.deleted_at')
                    ->whereIn('process_stock.process_id', array_keys($repair_batches))
                    ->whereRaw('process_stock.id = (SELECT id FROM process_stock WHERE process_stock.stock_id = stock.id ORDER BY id DESC LIMIT 1)');
            })
            ->leftJoin('process as pro', 'process_stock.process_id', '=', 'pro.id')
            ->leftJoin('storage', 'variation.storage', '=', 'storage.id')
            ->leftJoin('grade', 'variation.grade', '=', 'grade.id')
            ->leftJoin('order_items', function ($join) {
                $join->on('stock.id', '=', 'order_items.stock_id')
                    ->where('order_items.order_id', '=', DB::raw('stock.order_id'));
            })
            ->leftJoin('stock_operations', function ($join) {
                $join->on('stock.id', '=', 'stock_operations.stock_id')
                    ->whereRaw('stock_operations.id = (SELECT id FROM stock_operations WHERE stock_operations.stock_id = stock.id ORDER BY id DESC LIMIT 1)');
            })
            ->leftJoin('admin as admin2', 'stock_operations.admin_id', '=', 'admin2.id')

            ->select(
                'products.model',
                'storage.name as storage',
                'color.name as color',
                'grade.name as grade_name',
                'orders.reference_id as po',
                'pro.reference_id as process_id',
                'stock.imei as imei',
                'stock.serial_number as serial_number',
                'stock_operations.description as issue', // Corrected duplicated issue field
                'admin2.first_name as admin_name',
                'order_items.price as price'
            )
            ->where('process.id', request('id'))
            ->whereNull('process.deleted_at')
            ->whereNull('process_stock.deleted_at')
            ->orderBy('products.model', 'ASC')
            ->get();

        return $data;
    }

    public function headings(): array
    {
        return [
            'Model',
            'Storage',
            'Color',
            'Grade',
            'PO',
            'Process ID', // Corrected to generic 'Process ID' instead of trying to get from $repair_batches
            'IMEI',
            'Serial Number',
            'Issue',
            'Admin',
            'Price',
        ];
    }
}
