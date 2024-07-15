<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BatchReportExport implements FromCollection, WithHeadings, WithMapping
{
    protected $orderId;

    // Constructor to accept order_id
    public function __construct($orderId)
    {
        $this->orderId = $orderId;
    }

    // Collection method to fetch the data
    public function collection()
    {
        return DB::table('stock')
            ->leftJoin('variation', 'stock.variation_id', '=', 'variation.id')
            ->leftJoin('order_items as purchase_item', function ($join) {
                $join->on('stock.id', '=', 'purchase_item.stock_id')
                    ->whereRaw('purchase_item.order_id = stock.order_id');
            })
            ->leftJoin('vendor_grade', 'purchase_item.reference_id', '=', 'vendor_grade.id')
            ->leftJoin('grades', 'variation.grade_id', '=', 'grades.id') // Joining the grades table
            ->select(
                'variation.grade as grade',
                'vendor_grade.name as v_grade',
                DB::raw('COUNT(*) as quantity')
            )
            ->where('stock.order_id', $this->orderId)
            ->whereNull('stock.deleted_at')
            ->groupBy('variation.grade', 'purchase_item.reference_id', 'vendor_grade.name')
            ->get();
    }

    // Method to specify the headings
    public function headings(): array
    {
        // Fetch dynamic grade headings
        $gradeHeadings = DB::table('grades')
            ->select('name')
            ->pluck('name')
            ->toArray();

        // Static headings
        $staticHeadings = ['Grade', 'Vendor Grade', 'Quantity'];

        // Merge static and dynamic headings
        return array_merge($staticHeadings, $gradeHeadings);
    }

    // Method to map data for each row (if needed)
    public function map($row): array
    {
        return [
            $row->grade,
            $row->v_grade,
            $row->quantity,
        ];
    }
}
