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
        ->leftJoin('order_items', 'stock.id', '=', 'order_items.stock_id')
        ->where('order_items.order_id','stock.order_id')

        ->select(
            'products.model',
            'color.name as color',
            'storage.name as storage',
            'grade.name as grade_name',
            'stock.imei as imei',
            'stock.serial_number as serial_number',
            'customer.first_name as vendor',
            'orders.reference_id as reference_id',
            'order_items.price as cost'
        )
        ->where('stock.status', 1)
        ->where('orders.deleted_at',null)
        ->where('order_items.deleted_at',null)
        ->where('stock.deleted_at',null)

        ->when(request('storage') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('storage', request('storage'));
            });
        })
        ->when(request('category') != '', function ($q) {
            return $q->whereHas('variation.product', function ($q) {
                $q->where('category', request('category'));
            });
        })
        ->when(request('brand') != '', function ($q) {
            return $q->whereHas('variation.product', function ($q) {
                $q->where('brand', request('brand'));
            });
        })
        ->when(request('product') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('product_id', request('product'));
            });
        })
        ->when(request('grade') != '', function ($q) {
            return $q->whereHas('variation', function ($q) {
                $q->where('grade', request('grade'));
            });
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
            'Cost'
        ];
    }
}
