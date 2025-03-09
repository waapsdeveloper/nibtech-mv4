<?php

namespace App\Exports;

use App\Models\Process_model;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RepairersheetExport implements FromCollection, WithHeadings
{

    public function __construct()
    {

    }

    public function collection()
    {
        $data = DB::table('process')
            ->leftJoin('process_stock as p_stock', 'process.id', '=', 'p_stock.process_id')
            ->leftJoin('admin', 'p_stock.admin_id', '=', 'admin.id')
            ->leftJoin('stock', 'p_stock.stock_id', '=', 'stock.id')
            ->leftJoin('orders', 'stock.order_id', '=', 'orders.id')
            ->leftJoin('customer', 'orders.customer_id', '=', 'customer.id')
            ->leftJoin('variation', 'stock.variation_id', '=', 'variation.id')
            ->leftJoin('products', 'variation.product_id', '=', 'products.id')
            ->leftJoin('color', 'variation.color', '=', 'color.id')
            ->leftJoin('storage', 'variation.storage', '=', 'storage.id')
            ->leftJoin('grade', 'variation.grade', '=', 'grade.id')
            ->leftJoin('order_items', function ($join) {
                $join->on('stock.id', '=', 'order_items.stock_id')
                    ->whereNull('order_items.deleted_at')
                    ->where('order_items.order_id', '=', DB::raw('stock.order_id'));
            })
            ->leftJoin('stock_operations', function ($join) {
                $join->on('stock.id', '=', 'stock_operations.stock_id')
                    ->whereNull('stock_operations.deleted_at')
                    ->whereRaw('stock_operations.id = (SELECT id FROM stock_operations WHERE stock_operations.stock_id = stock.id AND stock_operations.description NOT LIKE "%Cost Adjusted %" AND stock_operations.description NOT LIKE "%Grade changed for Bulksale%" AND stock_operations.description NOT LIKE "% |  | DrPhone%" AND stock_operations.description NOT LIKE "Battery | | DrPhone" ORDER BY stock_operations.id DESC LIMIT 1)');
            })
            ->leftJoin('admin as admin2', 'stock_operations.admin_id', '=', 'admin2.id')

            ->select(
                'products.model',
                'storage.name as storage',
                'color.name as color',
                'grade.name as grade_name',
                'orders.reference_id as po',
                'customer.company as customer',
                'process.reference_id as process_id',
                // 'stock.id as stock_id',
                'stock.imei as imei',
                'stock.serial_number as serial_number',
                DB::raw('TRIM(BOTH " " FROM UPPER(
                    TRIM(LEADING "Battery | " FROM TRIM(LEADING " | " FROM REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(
                                        REPLACE(stock_operations.description, "TG", ""),
                                    "Cover", ""),
                                "5D", ""),
                            "Dual-Esim", ""),
                        " | DrPhone", ""),
                    "BCC", "Battery Cycle Count")))
                )) as issue'),
                'admin2.first_name as admin_name',
                'p_stock.status as status',
                'p_stock.created_at as created_at',
                // 'p_stock.updated_at as updated_at',
                'order_items.price as price',
                DB::raw('order_items.price * process.exchange_rate as ex_price'),
            )
            ->where('process.customer_id', request('id'))
            ->where('process.status', 2)
            ->where('process.process_type_id', 9)
            ->where('p_stock.status', 1)
            ->whereNull('process.deleted_at')
            ->whereNull('stock.deleted_at')
            ->whereNull('p_stock.deleted_at')
            ->whereNull('orders.deleted_at')
            ->whereNull('order_items.deleted_at')
            ->whereNull('stock_operations.deleted_at')
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
            'Customer',
            'Process ID', // Corrected to generic 'Process ID' instead of trying to get from $repair_batches
            'IMEI',
            'Serial Number',
            'Issue',
            'Admin',
            'Status',
            'Created At',
            // 'Updated At',
            'Price',
            'Exchange Price',
        ];
    }
}
