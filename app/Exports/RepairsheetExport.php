<?php

namespace App\Exports;


use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RepairsheetExport implements FromCollection, WithHeadings
{

    public function collection()
    {

        $data = DB::table('process')
        ->leftJoin('process_stock', 'process.id', '=', 'process_stock.order_id')
        ->leftJoin('admin', 'process_stock.admin_id', '=', 'admin.id')
        ->leftJoin('stock', 'process_stock.stock_id', '=', 'stock.id')
        ->leftJoin('variation', 'stock.variation_id', '=', 'variation.id')
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
            'products.model',
            'storage.name as storage',
            'color.name as color',
            'grade.name as grade_name',
            'stock.imei as imei',
            'stock.serial_number as serial_number',
            'stock_operations.description as issue',
            'process_stock.price as price'
        )
        ->where('process.id', request('id'))
        ->where('process.deleted_at',null)
        ->where('process_stock.deleted_at', null)
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
            'IMEI',
            'Serial Number',
            'Issue',
            'Price'
        ];
    }
}
