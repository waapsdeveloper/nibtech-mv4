<?php

namespace App\Events\V2;

use App\Models\Order_model;
use Illuminate\Queue\SerializesModels;

/**
 * V2 Version of OrderCreated Event
 * Generic event for order creation across all marketplaces
 */
class OrderCreated
{
    use SerializesModels;
    
    public Order_model $order;
    public $orderItems;
    
    public function __construct(Order_model $order, $orderItems)
    {
        $this->order = $order;
        $this->orderItems = $orderItems;
    }
}

