<?php

namespace App\Exports;


use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OrderReportExport implements FromCollection, WithHeadings
{

    public function collection()
    {

        $data = DB::table('orders')
        ->leftJoin('admin', 'orders.processed_by', '=', 'admin.id')
        ->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id')
        ->leftJoin('stock', 'order_items.stock_id', '=', 'stock.id')
        ->leftJoin('variation', 'order_items.variation_id', '=', 'variation.id')
        ->leftJoin('products', 'variation.product_id', '=', 'products.id')
        ->leftJoin('color', 'variation.color', '=', 'color.id')
        ->leftJoin('storage', 'variation.storage', '=', 'storage.id')
        ->leftJoin('grade', 'variation.grade', '=', 'grade.id')
        ->leftJoin('order_items as purchase_item', function ($join) {
            $join->on('stock.id', '=', 'purchase_item.stock_id')
                ->whereRaw('purchase_item.order_id = stock.order_id');
        })
        ->leftJoin('currency', 'orders.currency', '=', 'currency.id')
        ->select(
            'orders.reference_id',
            'variation.sku',
            'order_items.quantity',
            'products.model',
            'color.name as color',
            'storage.name as storage',
            'grade.name as grade_name',
            'stock.imei as imei',
            'stock.serial_number as serial_number',
            'stock.tester as tester',
            'admin.first_name as invoice',
            'orders.processed_at as date',
            'order_items.price as price',
            'purchase_item.price as cost',
            'currency.code as currency'
        )
        ->whereIn('orders.status', [3,6])
        ->whereIn('order_items.status', [3,6])
        ->where('orders.order_type_id', 3)
        ->where('orders.deleted_at',null)
        ->Where('order_items.deleted_at',null)
        ->Where('stock.deleted_at',null)
        ->when(request('start_date') != '', function ($q) {
            return $q->where('orders.processed_at', '>=', request('start_date', 0));
        })
        ->when(request('end_date') != '', function ($q) {
            return $q->where('orders.processed_at', '<=', request('end_date', 0) . " 23:59:59");
        })
        ->orderBy('orders.reference_id', 'DESC')
        ->get();

        return $data;
    }

    public function headings(): array
    {
        return [
            'Order Number',
            'SKU',
            'Quantity',
            'Model',
            'Color',
            'Storage',
            'Grade',
            'IMEI',
            'Serial Number',
            'Tester',
            'Invoice',
            'Date',
            'Price',
            'Cost',
            'Currency'
        ];
    }
}
