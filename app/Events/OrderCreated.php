<?php

namespace App\Events;

use App\Models\Order_model;
use Illuminate\Queue\SerializesModels;

class OrderCreated
{
    use SerializesModels;
    
    public $order;
    public $orderItems;
    
    public function __construct(Order_model $order, $orderItems)
    {
        $this->order = $order;
        $this->orderItems = $orderItems;
    }
}
