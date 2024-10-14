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
        // Storing reference_id as key and id as value for repair_batches (excluding current process id)
        $this->repair_batches = Process_model::where('process_type_id', 9)
            ->whereNot('id', request('id'))
            ->pluck('reference_id', 'id')
            ->toArray();
    }

    public function collection()
    {
        $repair_batches = $this->repair_batches;

        $data = DB::table('process as p') // Alias to avoid confusion with other joins
            ->leftJoin('process_stock as p_stock', 'p.id', '=', 'p_stock.process_id')
            ->leftJoin('admin', 'p_stock.admin_id', '=', 'admin.id')
            ->leftJoin('stock', 'p_stock.stock_id', '=', 'stock.id')
            ->leftJoin('orders', 'stock.order_id', '=', 'orders.id')
            ->leftJoin('variation', 'stock.variation_id', '=', 'variation.id')
            ->leftJoin('products', 'variation.product_id', '=', 'products.id')
            ->leftJoin('color', 'variation.color', '=', 'color.id')
            ->leftJoin('process_stock as ps2', function ($join) use ($repair_batches) {
                $join->on('stock.id', '=', 'ps2.stock_id')
                    ->whereNull('ps2.deleted_at')
                    ->whereIn('ps2.process_id', array_keys($repair_batches))
                    ->whereRaw('ps2.id = (SELECT id FROM process_stock WHERE process_stock.stock_id = stock.id ORDER BY id DESC LIMIT 1)');
            })
            ->leftJoin('process as process2', 'ps2.process_id', '=', 'process2.id')
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
                'ps2.process_id as proces', // Use ps2 process_id to avoid conflict
                'process2.reference_id as process_id', // Use process2 reference_id to avoid conflict
                'stock.imei as imei',
                'stock.serial_number as serial_number',
                'stock_operations.description as issue', // Corrected duplicated issue field
                'admin2.first_name as admin_name',
                'order_items.price as price'
            )
            ->where('p.id', request('id')) // Alias 'p' used to refer to the main process
            ->whereNull('p.deleted_at')
            ->whereNull('p_stock.deleted_at')
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
            'Process',
            'Process ID', // Generic 'Process ID' for better clarity
            'IMEI',
            'Serial Number',
            'Issue',
            'Admin',
            'Price',
        ];
    }
}

