<?php

// app/Exports/InvoiceExport.php
namespace App\Exports;

use App\models\Order_model;
use App\models\Customer_model;
use App\models\Order_item_model;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Facades\Excel;

class InvoiceExport implements FromView
{
    protected $order;
    protected $customer;
    protected $orderItems;

    public function __construct($orderId)
    {
        $this->order = Order_model::with('customer', 'order_items')->find($orderId);
        $this->customer = $this->order->customer;
        $this->orderItems = $this->order->order_items;
    }

    public function view(): View
    {
        return view('export.invoice', [
            'customer' => $this->customer,
            'order' => $this->order,
            'orderItems' => $this->orderItems,
        ]);
    }

    public function headings(): array
    {
        return [
            'Content-Type' => 'application/pdf',
        ];
    }

    public function sheets(): array
    {
        $sheets = [];

        // Include the external PDF (delivery note) as a separate sheet
        if (!empty($this->order->delivery_note_url)) {
            $sheets[] = new class($this->order->delivery_note_url) implements FromView {
                private $deliveryNoteUrl;

                public function __construct($deliveryNoteUrl)
                {
                    $this->deliveryNoteUrl = $deliveryNoteUrl;
                }

                public function view(): View
                {
                    return view('export.delivery_note', [
                        'deliveryNoteUrl' => $this->deliveryNoteUrl,
                    ]);
                }
            };
        }

        // Include the invoice as a separate sheet
        $sheets[] = $this;

        return $sheets;
    }
}
