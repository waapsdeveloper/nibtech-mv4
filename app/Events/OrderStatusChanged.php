<?php

namespace App\Events;

use App\Models\Order_model;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged
{
    use SerializesModels;
    
    public $order;
    public $oldStatus;
    public $newStatus;
    public $orderItems;
    
    public function __construct(Order_model $order, $oldStatus, $newStatus, $orderItems)
    {
        $this->order = $order;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->orderItems = $orderItems;
    }
}
