<?php

namespace App\Http\Livewire\Order;

use App\Models\Marketplace_model;
use App\Models\Order_model;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class OrderRow extends Component
{
    public int $orderId;

    public int $rowNumber;

    /** @var array<string, mixed> */
    public array $rowCounter = [];

    /** @var array<int, string> */
    public array $storages = [];

    /** @var array<int, string> */
    public array $colors = [];

    /** @var array<int, string> */
    public array $grades = [];

    /** @var array<int, array<int, string>> */
    public array $admins = [];

    /** @var array<string, mixed> */
    public array $currencies = [];

    /** @var array<int, string> */
    public array $orderStatuses = [];

    public ?Order_model $order = null;

    public bool $ready = false;

    protected string $inputAnchor;

    /** @var array<string, mixed> */
    public array $refurbedShippingDefaults = [];

    public function mount(
        int $orderId,
        int $rowNumber,
        array $rowCounter,
        array $storages,
        array $colors,
        array $grades,
        array $admins,
        array $currencies,
        array $orderStatuses
    ): void {
        $this->orderId = $orderId;
        $this->rowNumber = $rowNumber;
        $this->rowCounter = $rowCounter;
        $this->storages = $storages;
        $this->colors = $colors;
        $this->grades = $grades;
        $this->admins = $admins;
        $this->currencies = $currencies;
        $this->orderStatuses = $orderStatuses;
        $this->inputAnchor = 'order-' . $orderId;
        $this->refurbedShippingDefaults = $this->resolveRefurbedShippingDefaults();
    }

    public function loadRow(): void
    {
        if ($this->ready) {
            return;
        }

        $this->order = Order_model::query()
            ->with([
                'customer',
                'customer.orders.order_items.variation.product',
                'customer.orders.order_items.variation.grade_id',
                'customer.orders.order_items.stock',
                'order_items.variation.product',
                'order_items.variation.product.category_id',
                'order_items.variation.grade_id',
                'order_items.stock',
                'order_items.replacement.variation.product',
                'order_items.replacement.variation.grade_id',
                'order_items.replacement.stock',
                'order_items.replacement.replacement',
                'exchange_items.variation.product',
                'exchange_items.variation.grade_id',
                'exchange_items.stock',
                'transactions.transaction_type',
                'order_status',
                'payment_method',
            ])
            ->find($this->orderId);

        $this->ready = $this->order !== null;

        $this->dispatchBrowserEvent('orders-table-updated');
    }

    public function render(): View
    {
        return view('livewire.order.order-row', [
            'inputAnchor' => $this->inputAnchor,
            'refurbedShippingDefaults' => $this->refurbedShippingDefaults,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveRefurbedShippingDefaults(): array
    {
        static $cached;

        if ($cached !== null) {
            return $cached;
        }

        $defaults = [];
        $marketplace = Marketplace_model::query()->find(4);

        if ($marketplace) {
            $merchantAddress = data_get($marketplace, 'shipping_id');
            if (! empty($merchantAddress)) {
                $defaults['default_merchant_address_id'] = trim($merchantAddress);
            }

            $fallbackCarrier = data_get($marketplace, 'default_shipping_carrier');
            if (! empty($fallbackCarrier)) {
                $defaults['default_carrier'] = trim($fallbackCarrier);
            }
        }

        return $cached = $defaults;
    }
}
