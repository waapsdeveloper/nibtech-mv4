<?php

namespace App\Exports;


use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InventorysheetExport implements FromCollection, WithHeadings
{

    public function collection()
    {

        $data = DB::table('stock')
        ->leftJoin('variation', 'stock.variation_id', '=', 'variation.id')
        ->leftJoin('products', 'variation.product_id', '=', 'products.id')
        ->leftJoin('color', 'variation.color', '=', 'color.id')
        ->leftJoin('storage', 'variation.storage', '=', 'storage.id')
        ->leftJoin('grade', 'variation.grade', '=', 'grade.id')
        ->leftJoin('orders', 'stock.order_id', '=', 'orders.id')
        ->leftJoin('customer', 'orders.customer_id', '=', 'customer.id')
        ->leftJoin('order_items', function($join) {
            $join->on('stock.id', '=', 'order_items.stock_id')
                 ->whereColumn('order_items.order_id', 'stock.order_id');
        })
        ->leftJoin('stock_operations', function($join) {
            $join->on('stock.id', '=', 'stock_operations.stock_id')
                 ->whereColumn('stock_operations.new_variation_id', 'stock.variation_id')
                 ->orderBy('id','desc');
        })

        ->select(
            'products.model',
            'color.name as color',
            'storage.name as storage',
            'grade.name as grade_name',
            'stock.imei as imei',
            'stock.serial_number as serial_number',
            'customer.first_name as vendor',
            'orders.reference_id as reference_id',
            'order_items.price as cost',
            'stock_operations.description as reason'
        )
        ->where('stock.status', 1)
        ->where('orders.deleted_at',null)
        ->where('order_items.deleted_at',null)
        ->where('stock.deleted_at',null)

        ->when(request('storage') != '', function ($q) {
            $q->where('variation.storage', request('storage'));
        })
        ->when(request('category') != '', function ($q) {
            $q->where('products.category', request('category'));
        })
        ->when(request('brand') != '', function ($q) {
            $q->where('products.brand', request('brand'));
        })
        ->when(request('product') != '', function ($q) {
            $q->where('variation.product_id', request('product'));
        })
        ->when(request('grade') != '', function ($q) {
            $q->where('variation.grade', request('grade'));
        })
        ->get();

        return $data;
    }

    public function headings(): array
    {
        return [
            'Model',
            'Color',
            'Storage',
            'Grade',
            'IMEI',
            'Serial Number',
            'Vendor',
            'Reference',
            'Cost',
            'CHange Grade Reason'
        ];
    }
}
