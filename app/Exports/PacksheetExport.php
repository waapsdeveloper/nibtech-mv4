<?php

namespace App\Exports;


use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PacksheetExport implements FromCollection, WithHeadings
{
    protected $invoice = null;

    // Constructor to accept invoice flag
    public function __construct($invoice = null)
    {
        $this->invoice = $invoice;
    }

    public function collection()
    {

        $data = DB::table('orders')
        ->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id')
        ->leftJoin('stock', 'order_items.stock_id', '=', 'stock.id')
        ->leftJoin('orders as p_orders', 'stock.order_id', '=', 'p_orders.id')
        ->leftJoin('order_items as p_item', function($join) {
            $join->on('stock.id', '=', 'p_item.stock_id')
                 ->whereColumn('p_item.order_id', 'stock.order_id');
        })
        ->leftJoin('vendor_grade', function($join) {
            $join->on(DB::raw('COALESCE(p_item.reference_id, 0)'), '=', 'vendor_grade.id');
        })
        ->leftJoin('customer', 'p_orders.customer_id', '=', 'customer.id')
        ->leftJoin('variation', 'order_items.variation_id', '=', 'variation.id')
        ->leftJoin('products', 'variation.product_id', '=', 'products.id')
        ->leftJoin('color', 'variation.color', '=', 'color.id')
        ->leftJoin('storage', 'variation.storage', '=', 'storage.id')
        ->leftJoin('grade', 'variation.grade', '=', 'grade.id')
        ->leftJoin('grade as sub', 'variation.sub_grade', '=', 'sub.id')
        ->leftJoin('stock_operations', function ($join) {
            $join->on('stock.id', '=', 'stock_operations.stock_id')
                 ->where('stock_operations.new_variation_id', '=', DB::raw('variation.id'))
                //  ->whereNot('stock_operations.description', 'LIKE', '%Cost Adjusted %')
                 ->whereRaw('stock_operations.id = (SELECT id FROM stock_operations WHERE stock_operations.stock_id = stock.id AND stock_operations.description NOT LIKE "%Cost Adjusted %" AND stock_operations.description NOT LIKE "%Grade changed for Bulksale%" AND stock_operations.description NOT LIKE "% |  | DrPhone%" AND stock_operations.description NOT LIKE "%Repaired Externally%" AND stock_operations.description NOT LIKE "Battery | | DrPhone" ORDER BY id DESC LIMIT 1)');
        })
        ->leftJoin('stock_operations as old_operations', function ($join) {
            $join->on('stock.id', '=', 'old_operations.stock_id')
                //  ->whereNot('old_operations.description', 'LIKE', '%Cost Adjusted %')
                 ->whereRaw('old_operations.id = (SELECT id FROM stock_operations WHERE stock_operations.stock_id = stock.id AND stock_operations.description NOT LIKE "%Cost Adjusted %" AND stock_operations.description NOT LIKE "%Grade changed for Bulksale%" AND stock_operations.description NOT LIKE "% |  | DrPhone%" AND stock_operations.description NOT LIKE "%Repaired Externally%" AND stock_operations.description NOT LIKE "Battery | | DrPhone" ORDER BY id DESC LIMIT 1)');
        })
        ->leftJoin('admin', 'stock_operations.admin_id', '=', 'admin.id')


        ->select(
            DB::raw('CONCAT(products.model, " ", storage.name) as model_storage'),
            // 'products.model',
            // 'storage.name as storage',
            'color.name as color',
            'grade.name as grade_name',
            'sub.name as sub_grade',
            'stock.imei as imei',
            'stock.serial_number as serial_number',
            'p_orders.reference_id as po',
            'p_orders.created_at as po_date',
            'customer.first_name as vendor',
            'vendor_grade.name as vendor_grade',
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
            DB::raw('TRIM(BOTH " " FROM UPPER(
                TRIM(LEADING "Battery | " FROM TRIM(LEADING " | " FROM REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(old_operations.description, "TG", ""),
                                "Cover", ""),
                            "5D", ""),
                        "Dual-Esim", ""),
                    " | DrPhone", ""),
                "BCC", "Battery Cycle Count")))
            )) as old_issue'),
            // 'stock_operations.description as issue',
            // 'old_operations.description as old_issue',
            'admin.first_name as admin',
            // 'order_items.price as price'
            // Conditional price based on invoice flag
            $this->invoice == 1
                ? DB::raw('order_items.price * orders.exchange_rate as price') // Use exchange rate if invoice = 1
                : 'order_items.price as price'
        )
        ->where('orders.id', request('id'))
        ->where('orders.deleted_at',null)
        ->where('order_items.deleted_at', null)
        ->orderBy('products.model', 'ASC')
        ->orderBy('storage.name', 'ASC')
        ->get();

        return $data;
    }

    public function headings(): array
    {
        return [
            'Name',
            // 'Storage',
            'Color',
            'Grade',
            'Sub Grade',
            'IMEI',
            'Serial Number',
            'PO',
            'PO Date',
            'Vendor',
            'Vendor Grade',
            'Issue',
            'Old Issue',
            'Admin',
            // 'Price'
            $this->invoice == 1 ? 'Price (Multiplied by Exchange Rate)' : 'Price'
        ];
    }
}
