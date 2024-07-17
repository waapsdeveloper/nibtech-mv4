<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BatchReportExport implements FromCollection, WithHeadings, WithMapping
{
    protected $orderId;
    protected $grades;

    // Constructor to accept order_id
    public function __construct($orderId)
    {
        $this->orderId = $orderId;
        $this->grades = DB::table('grade')->pluck('name')->toArray();
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
            ->leftJoin('grade', 'variation.grade', '=', 'grade.id') // Joining the grade table
            ->select(
                'variation.grade as grade',
                'vendor_grade.name as v_grade',
                DB::raw('grade.name as grade_name'),
                DB::raw('COUNT(*) as quantity')
            )
            ->where('stock.order_id', $this->orderId)
            ->whereNull('stock.deleted_at')
            ->groupBy('variation.grade', 'purchase_item.reference_id', 'vendor_grade.name', 'grade.name')
            ->get();
    }

    // Method to specify the headings
    public function headings(): array
    {
        // Static headings
        $staticHeadings = ['Grade'];

        // Merge static and dynamic headings
        return array_merge($staticHeadings, $this->grades);
    }

    // Method to map data for each row
    public function map($row): array
    {
        // Initialize the row data with static columns
        $rowData = [
            // $row->grade,
            $row->v_grade,
        ];

        // Add quantities for each grade dynamically
        foreach ($this->grades as $grade) {
            if ($row->grade_name === $grade) {
                $rowData[] = $row->quantity;
            } else {
                $rowData[] = 0;
            }
        }

        return $rowData;
    }
}
