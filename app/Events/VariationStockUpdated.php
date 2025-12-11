<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VariationStockUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $variationId;
    public $oldStock;
    public $newStock;
    public $stockChange;
    public $adminId;

    /**
     * Create a new event instance.
     *
     * @param int $variationId
     * @param int $oldStock
     * @param int $newStock
     * @param int $stockChange The increment/decrement amount
     * @param int|null $adminId
     */
    public function __construct($variationId, $oldStock, $newStock, $stockChange, $adminId = null)
    {
        $this->variationId = $variationId;
        $this->oldStock = $oldStock;
        $this->newStock = $newStock;
        $this->stockChange = $stockChange;
        $this->adminId = $adminId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('variation-stock-updates');
    }
}
