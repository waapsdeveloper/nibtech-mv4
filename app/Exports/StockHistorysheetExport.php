<?php

namespace App\Exports;

use App\Models\Process_model;
use App\Models\Stock_model;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockHistorysheetExport implements FromCollection, WithHeadings, WithMapping
{
    protected $stock_ids;

    public function __construct()
    {
        // Storing reference_id as key and id as value for repair_batches
        $this->stock_ids = request('stock_ids');
    }

    public function collection()
    {
        $stock_ids = explode(',', $this->stock_ids);
        $stocks = Stock_model::whereIn('id',$stock_ids)->with(['order_items','stock_operations'])->get();
        return $stocks;
        // $rows = [];

        // foreach ($stocks as $stock) {
        //     // External Movements
        //     foreach ($stock->order_items as $item) {
        //         $order = $item->order;
        //         $rows[] = [
        //             'stock_id' => $stock->id,
        //             'movement_type' => 'External Movement',
        //             'order_id_or_old_variation' => $order->reference_id,
        //             'customer_or_new_variation' => ($order->customer->first_name ?? null) . ' ' . ($order->customer->last_name ?? 'N/A'),
        //             'type_or_reason' => $order->order_type->name ?? 'N/A',
        //             'quantity' => $order->quantity ?? 'N/A',
        //             'added_by' => $order->admin->first_name ?? 'N/A',
        //             'datetime' => $order->created_at,
        //         ];
        //     }

        //     // Internal Movements
        //     foreach ($stock->stock_operations as $operation) {
        //         $rows[] = [
        //             'stock_id' => $stock->id,
        //             'movement_type' => 'Internal Movement',
        //             'order_id_or_old_variation' => $operation->old_variation->sku ?? 'N/A',
        //             'customer_or_new_variation' => $operation->new_variation->sku ?? 'N/A',
        //             'type_or_reason' => $operation->description ?? 'N/A',
        //             'quantity' => 'N/A',
        //             'added_by' => $operation->admin->first_name ?? 'N/A',
        //             'datetime' => $operation->created_at,
        //         ];
        //     }
        // }

        // return collect($rows);
    }

    // Define the headings for the Excel file
    public function headings(): array
    {
        return [
            'Stock ID',
            'Movement Type', // External or Internal
            'Order ID / Old Variation',
            'Customer/Vendor / New Variation',
            'Type / Reason',
            'Quantity',
            'Added By',
            'DateTime',
        ];
    }

    // Map each movement to a row in the Excel file
    public function map($stock): array
    {
        $rows = [];

        // External Movements
        foreach ($stock['order_items'] as $item) {
            $order = $item->order;
            $rows[] = [
                $stock->id,
                'External Movement',
                $order->reference_id,
                ($order->customer->first_name ?? null) . ' ' . ($order->customer->last_name ?? 'N/A'),
                $order->order_type->name ?? 'N/A',
                $order->quantity ?? 'N/A',
                $order->admin->first_name ?? 'N/A',
                $order->created_at,
            ];
        }

        // Internal Movements
        foreach ($stock['stock_operations'] as $operation) {
            $rows[] = [
                $stock->id,
                'Internal Movement',
                $operation->old_variation->sku ?? 'N/A',
                $operation->new_variation->sku ?? 'N/A',
                $operation->description ?? 'N/A',
                'N/A', // Quantity is not applicable for internal movement
                $operation->admin->first_name ?? 'N/A',
                $operation->created_at,
            ];
        }

        return $rows;
    }
}
