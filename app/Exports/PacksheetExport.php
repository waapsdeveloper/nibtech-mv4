<?php

namespace App\Exports;


use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PacksheetExport implements FromCollection, WithHeadings
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
        ->leftJoin('stock_operations', function ($join) {
            $join->on('stock.id', '=', 'stock_operations.stock_id')
                 ->where('stock_operations.new_variation_id', '=', DB::raw('variation.id'))
                 ->whereRaw('stock_operations.id = (SELECT id FROM stock_operations WHERE stock_operations.stock_id = stock.id ORDER BY id DESC LIMIT 1)');
        })

        ->select(
            DB::raw('(@rn:=@rn + 1) AS row_number'),
            'products.model',
            'storage.name as storage',
            'color.name as color',
            'grade.name as grade_name',
            'stock.imei as imei',
            'stock.serial_number as serial_number',
            'stock_operations.description as issue',
            'order_items.price as price'
        )
        ->crossJoin(DB::raw('(SELECT @rn := 0) AS init'))
        ->where('orders.id', request('id'))
        ->where('orders.deleted_at',null)
        ->where('order_items.deleted_at', null)
        ->orderBy('products.model', 'ASC')
        ->get();

        return $data;
    }

    public function headings(): array
    {
        return [
            'No',
            'Model',
            'Storage',
            'Color',
            'Grade',
            'IMEI',
            'Serial Number',
            'Issue',
            'Price'
        ];
    }
}
