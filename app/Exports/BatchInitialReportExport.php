<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BatchInitialReportExport implements FromCollection, WithHeadings, WithMapping
{
    protected $orderId;
    protected $grades;
    protected $vendorData = [];
    protected $notesList = [];
    protected $regionData = [];

    // Constructor to accept order_id
    public function __construct($orderId)
    {
        $this->orderId = $orderId;
        $this->grades = DB::table('grade')->pluck('name')->toArray();
    }

    // Collection method to fetch the data
    public function collection()
    {
        $data = DB::table('stock')
            ->leftJoin(
                DB::raw("(SELECT * FROM stock_operations WHERE id IN (
                    SELECT MIN(id) FROM stock_operations GROUP BY stock_id
                )) as operation"),
                'stock.id',
                '=',
                'operation.stock_id'
            )
            // ->leftJoin('stock_operations as operation', function ($join) {
            //     $join->on('stock.id', '=', 'operation.stock_id')
            //         ->orderBy('operation.id', 'asc')->limit(1);
            // })
            ->leftJoin('region', 'stock.region_id', '=', 'region.id')
            ->leftJoin('variation', 'operation.new_variation_id', '=', 'variation.id')
            ->leftJoin('order_items as purchase_item', function ($join) {
                $join->on('stock.id', '=', 'purchase_item.stock_id')
                    ->whereRaw('purchase_item.order_id = stock.order_id');
            })
            ->leftJoin('vendor_grade', 'purchase_item.reference_id', '=', 'vendor_grade.id')
            ->leftJoin('grade', 'variation.grade', '=', 'grade.id')
            ->select(
                'vendor_grade.name as v_grade',
                'grade.name as grade_name',
                'order_items.reference as notes',
                'region.name as region_name',
                DB::raw('COUNT(*) as quantity')
            )
            ->where('stock.order_id', $this->orderId)
            ->whereNull('stock.deleted_at')
            ->groupBy('vendor_grade.name', 'grade.name')
            ->orderBy('vendor_grade.name')
            ->orderBy('grade.name')
            ->get();
            foreach ($data as $row) {
                // Initialize nested arrays if not set
                if (!isset($this->vendorData[$row->v_grade][$row->region_name][$row->notes])) {
                $this->vendorData[$row->v_grade][$row->region_name][$row->notes] = array_fill_keys($this->grades, 0);
                }
                // Set the quantity for the specific grade
                $this->vendorData[$row->v_grade][$row->region_name][$row->notes][$row->grade_name] = $row->quantity;
            }

            $finalData = [];
            foreach ($this->vendorData as $vendorGrade => $regions) {
                foreach ($regions as $regionName => $notesArr) {
                foreach ($notesArr as $notes => $gradesArr) {
                    $row = [
                    'vendor_grade' => $vendorGrade,
                    'region_name' => $regionName,
                    'notes' => $notes,
                    ];
                    $totalQuantity = 0;
                    foreach ($this->grades as $grade) {
                    $quantity = $gradesArr[$grade] ?? 0;
                    $row[$grade] = $quantity;
                    $totalQuantity += $quantity;
                    }
                    $row['total_quantity'] = $totalQuantity;
                    $finalData[] = $row;
                }
                }
            }

        return collect($finalData);
    }

    // Method to specify the headings
    public function headings(): array
    {
        // Static heading for the first column
        $staticHeadings = ['Vendor Grade', 'Region Name', 'Notes'];

        // Merge static heading with dynamic grade names as headings
        return array_merge($staticHeadings, ['Total Quantity'], $this->grades);
    }

    // Method to map data for each row
    public function map($row): array
    {
        // Initialize the row data with the vendor grade
        $rowData = [$row['vendor_grade'], $row['region_name'], $row['notes']];

        // Append total quantity
        $rowData[] = $row['total_quantity'];

        // Append quantities for each grade
        foreach ($this->grades as $grade) {
            $rowData[] = $row[$grade];
        }

        return $rowData;
    }
}
