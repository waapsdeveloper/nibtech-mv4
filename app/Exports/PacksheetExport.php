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
        ->leftJoin('customer', 'p_orders.customer_id', '=', 'customer.id')
        ->leftJoin('variation', 'order_items.variation_id', '=', 'variation.id')
        ->leftJoin('products', 'variation.product_id', '=', 'products.id')
        ->leftJoin('color', 'variation.color', '=', 'color.id')
        ->leftJoin('storage', 'variation.storage', '=', 'storage.id')
        ->leftJoin('grade', 'variation.grade', '=', 'grade.id')
        ->leftJoin('stock_operations', function ($join) {
            $join->on('stock.id', '=', 'stock_operations.stock_id')
                 ->where('stock_operations.new_variation_id', '=', DB::raw('variation.id'))
                 ->whereRaw('stock_operations.id = (SELECT id FROM stock_operations WHERE stock_operations.stock_id = stock.id ORDER BY id DESC LIMIT 1)');
        })
        ->leftJoin('admin', 'stock_operations.admin_id', '=', 'admin.id')


        ->select(
            'products.model',
            'storage.name as storage',
            'color.name as color',
            'grade.name as grade_name',
            'stock.imei as imei',
            'stock.serial_number as serial_number',
            'p_orders.reference_id as po',
            'p_orders.created_at as po_date',
            'customer.first_name as vendor',
            'stock_operations.description as issue',
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
            'Model',
            'Storage',
            'Color',
            'Grade',
            'IMEI',
            'Serial Number',
            'PO',
            'PO Date',
            'Vendor',
            'Issue',
            'Admin',
            // 'Price'
            $this->invoice == 1 ? 'Price (Multiplied by Exchange Rate)' : 'Price'
        ];
    }
}
