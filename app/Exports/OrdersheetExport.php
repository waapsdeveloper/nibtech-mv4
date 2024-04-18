<?php

namespace App\Exports;


use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OrdersheetExport implements FromCollection, WithHeadings
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
            'orders.created_at as date'
        )
        ->where('orders.status', 3)
        ->when(request('start_date') != '', function ($q) {
            return $q->where('orders.created_at', '>=', request('start_date', 0));
        })
        ->when(request('end_date') != '', function ($q) {
            return $q->where('orders.created_at', '<=', request('end_date', 0) . " 23:59:59");
        })
        ->when(request('order_id') != '', function ($q) {
            return $q->where('orders.reference_id', 'LIKE', request('order_id') . '%');
        })
        ->when(request('last_order') != '', function ($q) {
            return $q->where('orders.reference_id', '>', request('last_order'));
        })
        ->when(request('sku') != '', function ($q) {
            return $q->whereHas('order_items.variation', function ($q) {
                $q->where('sku', 'LIKE', '%' . request('sku') . '%');
            });
        })
        ->when(request('adm') != '', function ($q) {
            if(request('adm') == 0){
                return $q->where('orders.processed_by', null);
            }
            return $q->where('orders.processed_by', request('adm'));
        })
        ->when(request('imei') != '', function ($q) {
            return $q->whereHas('order_items.stock', function ($q) {
                $q->where('imei', 'LIKE', '%' . request('imei') . '%');
            });
        })
        ->orderBy('orders.reference_id', 'DESC')
        ->get();

        return $data;
    }

    public function headings(): array
    {
        return [
            'Order ID',
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
            'Date'
        ];
    }
}
