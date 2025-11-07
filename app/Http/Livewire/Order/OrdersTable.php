<?php

namespace App\Http\Livewire\Order;

use App\Services\Orders\OrderTableQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class OrdersTable extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';
    protected $queryString = ['page' => ['except' => 1]];

    /** @var array<string, mixed> */
    public array $filters = [];

    /** @var array<int, string>|Collection<int, string> */
    public Collection|array $testers = [];

    /** @var mixed */
    public $storages = [];

    /** @var mixed */
    public $colors = [];

    /** @var mixed */
    public $grades = [];

    /** @var mixed */
    public $orderStatuses = [];

    /** @var mixed */
    public $admins = [];

    /** @var mixed */
    public $currencies = [];

    public bool $readyToLoad = false;
    public int $perPage = 10;

    protected ?string $lastTesterInputId = null;
    protected ?string $lastImeiInputId = null;

    public function mount(
        array $filters = [],
        array $testers = [],
        array $storages = [],
        array $colors = [],
        array $grades = [],
        $admins = [],
        $currencies = [],
        array $orderStatuses = []
    ): void
    {
        $this->filters = $filters;
        $this->testers = $testers;
        $this->storages = $storages;
        $this->colors = $colors;
        $this->grades = $grades;
        $this->admins = $admins;
        $this->currencies = $currencies;
        $this->orderStatuses = $orderStatuses;
        $this->perPage = OrderTableQuery::perPage();
        $this->page = isset($filters['page']) && is_numeric($filters['page'])
            ? max(1, (int) $filters['page'])
            : 1;
    }

    public function loadOrders(): void
    {
        if (! $this->readyToLoad) {
            $this->readyToLoad = true;
        }
    }

    public function render()
    {
        $orders = $this->readyToLoad ? $this->fetchOrders() : $this->emptyPaginator();

        if ($this->readyToLoad) {
            $meta = $this->computeInputMeta($orders);
            $this->lastTesterInputId = $meta['lastTesterId'];
            $this->lastImeiInputId = $meta['lastImeiId'];

            $this->dispatchBrowserEvent('orders-table-updated', [
                'invoiceMode' => request()->filled('invoice'),
                'packingMode' => request()->filled('packing'),
                'lastTesterId' => $this->lastTesterInputId,
                'lastImeiId' => $this->lastImeiInputId,
            ]);
        }

        return view('livewire.order.orders-table', [
            'orders' => $orders,
            'readyToLoad' => $this->readyToLoad,
            'testers' => $this->testers,
            'total_items' => $this->readyToLoad
                ? $orders->sum(fn ($order) => $order->order_items->count())
                : 0,
            'storages' => $this->storages,
            'colors' => $this->colors,
            'grades' => $this->grades,
            'admins' => $this->admins,
            'currencies' => $this->currencies,
            'order_statuses' => $this->orderStatuses,
        ]);
    }

    protected function fetchOrders(): LengthAwarePaginator
    {
        $query = OrderTableQuery::build();

        $paginator = $query->paginate($this->perPage, ['*'], $this->pageName());

        if (method_exists($paginator, 'onEachSide')) {
            $paginator->onEachSide(5);
        }

        return $paginator;
    }

    protected function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $this->perPage, $this->page, [
            'path' => request()->url(),
            'pageName' => $this->pageName(),
        ]);
    }

    /**
     * @param  LengthAwarePaginator  $orders
     * @return array{testerCount:int, imeiCount:int, lastTesterId:?string, lastImeiId:?string}
     */
    protected function computeInputMeta(LengthAwarePaginator $orders): array
    {
        $testerCount = 0;
        $imeiCount = 0;
        $packingEnabled = request()->filled('packing');

        foreach ($orders as $order) {
            $items = $order->order_items;
            $itemCount = $items->count();

            foreach ($items as $index => $item) {
                if ($order->status == 3) {
                    continue;
                }

                if ($index !== 0) {
                    continue;
                }

                if ($itemCount < 2 && $item->quantity >= 2) {
                    for ($in = 1; $in <= $item->quantity; $in++) {
                        if (! $packingEnabled) {
                            $testerCount++;
                        }
                        $imeiCount++;
                    }
                    continue;
                }

                if ($itemCount >= 2) {
                    foreach ($items as $subItem) {
                        for ($in = 1; $in <= $subItem->quantity; $in++) {
                            if (! $packingEnabled) {
                                $testerCount++;
                            }
                            $imeiCount++;
                        }
                    }
                    continue;
                }

                if (! $packingEnabled) {
                    $testerCount++;
                }
                $imeiCount++;
            }
        }

        return [
            'testerCount' => $testerCount,
            'imeiCount' => $imeiCount,
            'lastTesterId' => $testerCount > 0 ? 'tester' . $testerCount : null,
            'lastImeiId' => $imeiCount > 0 ? 'imei' . $imeiCount : null,
        ];
    }
}
