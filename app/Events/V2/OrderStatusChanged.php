<?php

namespace App\Events\V2;

use App\Models\Order_model;
use Illuminate\Queue\SerializesModels;

/**
 * V2 Version of OrderStatusChanged Event
 * Generic event for order status changes across all marketplaces
 */
class OrderStatusChanged
{
    use SerializesModels;
    
    public Order_model $order;
    public int $oldStatus;
    public int $newStatus;
    public $orderItems;
    
    public function __construct(Order_model $order, int $oldStatus, int $newStatus, $orderItems)
    {
        $this->order = $order;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->orderItems = $orderItems;
    }
}

