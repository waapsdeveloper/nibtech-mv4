<?php

namespace App\Http\Livewire;

use App\Models\Order_model;
use App\Models\Stock_model;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

class PackingDesk extends Component
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    public string $scanInput = '';
    public string $stage = 'sku';
    public ?string $currentSku = null;
    public array $orders = [];
    public array $scannedImeis = [];
    public ?int $activeOrderId = null;
    public ?string $statusMessage = null;
    public ?string $errorMessage = null;
    public array $completedOrders = [];
    public string $filterReference = '';
    public string $filterCustomer = '';
    public string $filterTracking = '';
    public $filterLimit = 20;
    public array $dispatchBasket = [];
    public array $picklist = [];

    protected $listeners = ['packingReset' => 'resetSession'];

    public function mount(): void
    {
        if (! session('user_id')) {
            redirect('index')->send();
            return;
        }

        $this->statusMessage = 'Scan a SKU to load pending orders.';
        $this->dispatchBrowserEvent('packing-focus-input');
    }

    public function render()
    {
        return view('livewire.packing_desk');
    }

    public function applyFilters(): void
    {
        if (! $this->currentSku) {
            $this->errorMessage = 'Scan a SKU before applying filters.';
            $this->dispatchBrowserEvent('packing-focus-input');
            return;
        }

        $this->loadOrdersForSku($this->currentSku, true);
        if (! empty($this->orders)) {
            $this->statusMessage = 'Filters applied to current queue.';
        }
        $this->dispatchBrowserEvent('packing-focus-input');
    }

    public function clearFilters(): void
    {
        $this->filterReference = '';
        $this->filterCustomer = '';
        $this->filterTracking = '';
        $this->filterLimit = self::DEFAULT_LIMIT;

        if ($this->currentSku) {
            $this->loadOrdersForSku($this->currentSku, true);
            if (! empty($this->orders)) {
                $this->statusMessage = 'Filters cleared for the active SKU.';
            }
        }

        $this->dispatchBrowserEvent('packing-focus-input');
    }

    public function processScan(): void
    {
        $value = trim($this->scanInput);
        $this->scanInput = '';
        $this->errorMessage = null;

        if ($value === '') {
            $this->dispatchBrowserEvent('packing-focus-input');
            return;
        }

        if (Str::upper($value) === 'RESET') {
            $this->resetSession();
            return;
        }

        if ($this->stage === 'sku') {
            $this->handleSkuScan($value);
        } elseif ($this->stage === 'imei') {
            $this->handleImeiScan($value);
        } else {
            $this->handleTrackingScan($value);
        }

        $this->dispatchBrowserEvent('packing-focus-input');
    }

    public function resetSession(): void
    {
        $this->scanInput = '';
        $this->stage = 'sku';
        $this->currentSku = null;
        $this->orders = [];
        $this->scannedImeis = [];
        $this->activeOrderId = null;
        $this->dispatchBasket = [];
        $this->picklist = [];
        $this->filterReference = '';
        $this->filterCustomer = '';
        $this->filterTracking = '';
        $this->filterLimit = self::DEFAULT_LIMIT;
        $this->statusMessage = 'Session reset. Scan a SKU to begin.';
        $this->errorMessage = null;
        $this->dispatchBrowserEvent('packing-focus-input');
    }

    private function handleSkuScan(string $value): void
    {
        $sku = Str::upper($value);
        $this->loadOrdersForSku($sku);
    }

    private function handleImeiScan(string $value): void
    {
        if (empty($this->orders)) {
            $this->errorMessage = 'No orders loaded. Scan a SKU first.';
            return;
        }

        $normalized = Str::upper(trim($value));

        if ($this->imeiAlreadyCaptured($normalized)) {
            $this->errorMessage = 'This IMEI/Serial has already been scanned in this session.';
            return;
        }

        if ($this->imeiAlreadyLinked($normalized)) {
            $this->errorMessage = 'This IMEI/Serial is already assigned to one of the queued orders.';
            return;
        }

        $orderId = $this->determineTargetOrderId();
        if (! $orderId) {
            $this->errorMessage = 'All loaded orders are complete. Scan tracking or reset.';
            return;
        }

        $orderIndex = $this->getOrderIndex($orderId);
        if ($orderIndex === null) {
            $this->errorMessage = 'Order could not be found in the active queue.';
            return;
        }

        if ($this->orderHasExistingImei($orderIndex, $normalized)) {
            $this->errorMessage = 'This IMEI/Serial is already attached to the order.';
            return;
        }

        if (! $this->stockExists($normalized)) {
            $this->errorMessage = 'IMEI/Serial not found in stock records.';
            return;
        }

        $this->scannedImeis[$orderId] = Arr::wrap($this->scannedImeis[$orderId] ?? []);
        $this->scannedImeis[$orderId][] = $normalized;

        $this->orders[$orderIndex]['remaining'] = max($this->orders[$orderIndex]['remaining'] - 1, 0);
        $this->orders[$orderIndex]['scanned'] = ($this->orders[$orderIndex]['initial_remaining'] - $this->orders[$orderIndex]['remaining']);

        $orderRef = $this->orders[$orderIndex]['reference'];
        $this->statusMessage = "IMEI assigned to order {$orderRef}. Remaining: " . $this->orders[$orderIndex]['remaining'];

        if ($this->orders[$orderIndex]['remaining'] === 0) {
            $this->activeOrderId = $orderId;
            $this->stage = 'tracking';
            $this->statusMessage = "Order {$orderRef} ready. Scan tracking barcode.";
        }
    }

    private function handleTrackingScan(string $value): void
    {
        if (! $this->activeOrderId) {
            $this->errorMessage = 'No active order awaiting tracking scan.';
            return;
        }

        $orderIndex = $this->getOrderIndex($this->activeOrderId);
        if ($orderIndex === null) {
            $this->errorMessage = 'Active order missing. Reset session to continue.';
            return;
        }

        $expectedTracking = $this->orders[$orderIndex]['tracking_number'];
        $normalizedTracking = $this->normalizeTracking($value);

        if (! $expectedTracking) {
            $this->errorMessage = 'Order tracking number is missing. Resolve before dispatch.';
            return;
        }

        if ($normalizedTracking !== $expectedTracking) {
            $this->errorMessage = 'Tracking number does not match order record.';
            return;
        }

        $imeis = $this->scannedImeis[$this->activeOrderId] ?? [];
        if (empty($imeis)) {
            $this->errorMessage = 'No IMEIs recorded for this order. Scan devices first.';
            return;
        }

        $this->triggerDispatch($this->activeOrderId, $imeis, $normalizedTracking);
        $this->markOrderComplete($this->activeOrderId, $imeis, $normalizedTracking);
        $this->postDispatchReset();
    }

    public function toggleDispatchBasket(int $orderId): void
    {
        if (isset($this->dispatchBasket[$orderId])) {
            $this->removeFromDispatchBasket($orderId);
            return;
        }

        $this->addToDispatchBasket($orderId);
        $this->dispatchBrowserEvent('packing-focus-input');
    }

    public function removeFromDispatchBasket(int $orderId): void
    {
        if (isset($this->dispatchBasket[$orderId])) {
            unset($this->dispatchBasket[$orderId]);
            $this->statusMessage = 'Order removed from the dispatch basket.';
            if ($this->activeOrderId === $orderId) {
                $this->activeOrderId = $this->determineTargetOrderId();
            }
            $this->rebuildPicklistIfNeeded();
        }

        $this->dispatchBrowserEvent('packing-focus-input');
    }

    public function activateBasketOrder(int $orderId): void
    {
        $orderIndex = $this->getOrderIndex($orderId);
        if ($orderIndex === null) {
            $this->errorMessage = 'Selected order is no longer in the active queue.';
            $this->dispatchBrowserEvent('packing-focus-input');
            return;
        }

        if (($this->orders[$orderIndex]['remaining'] ?? 0) <= 0) {
            $this->errorMessage = 'Selected order is already complete.';
            $this->dispatchBrowserEvent('packing-focus-input');
            return;
        }

        $this->activeOrderId = $orderId;
        $this->stage = 'imei';
        $this->statusMessage = 'Focused on order ' . $this->orders[$orderIndex]['reference'] . '. Continue scanning IMEIs.';
        $this->dispatchBrowserEvent('packing-focus-input');
    }

    public function buildPicklist(): void
    {
        $this->generatePicklist(true);
        $this->dispatchBrowserEvent('packing-focus-input');
    }

    public function clearPicklist(): void
    {
        $this->picklist = [];
        $this->statusMessage = 'Picklist cleared.';
        $this->dispatchBrowserEvent('packing-focus-input');
    }

    private function triggerDispatch(int $orderId, array $imeis, string $tracking): void
    {
        $testerName = $this->resolveTesterName();
        $testerPayload = array_fill(0, count($imeis), $testerName);
        $skuPayload = array_fill(0, count($imeis), $this->currentSku ?? '');

        // Leverage existing order/dispatch route so printing and BackMarket sync stay unified.
        $this->dispatchBrowserEvent('packing-dispatch', [
            'orderId' => $orderId,
            'sku' => $skuPayload,
            'imei' => $imeis,
            'tester' => $testerPayload,
            'tracking' => $tracking,
        ]);
    }

    private function markOrderComplete(int $orderId, array $imeis, string $tracking): void
    {
        $orderIndex = $this->getOrderIndex($orderId);
        if ($orderIndex === null) {
            return;
        }

        $completed = [
            'reference' => $this->orders[$orderIndex]['reference'],
            'sku' => $this->currentSku,
            'imei_count' => count($imeis),
            'tracking' => $tracking,
            'timestamp' => Carbon::now()->format('H:i:s'),
        ];

        $this->completedOrders[] = $completed;

        unset($this->orders[$orderIndex]);
        $this->orders = array_values($this->orders);
        unset($this->scannedImeis[$orderId]);

        if (isset($this->dispatchBasket[$orderId])) {
            unset($this->dispatchBasket[$orderId]);
        }

        $this->rebuildPicklistIfNeeded();
    }

    private function postDispatchReset(): void
    {
        $this->errorMessage = null;

        $nextOrderId = $this->determineTargetOrderId();
        $this->activeOrderId = null;

        if ($nextOrderId !== null) {
            $this->activeOrderId = $nextOrderId;
            $this->stage = 'imei';
            $this->statusMessage = 'Continue scanning IMEIs for the next order.';
            return;
        }

        if (! empty($this->orders)) {
            $this->activeOrderId = $this->orders[0]['id'];
            $this->stage = 'imei';
            $this->statusMessage = 'Continue scanning IMEIs for the next order.';
            return;
        }

        $this->statusMessage = 'All orders for this SKU completed. Scan a new SKU to continue.';
        $this->stage = 'sku';
        $this->currentSku = null;
    }

    private function loadOrdersForSku(string $sku, bool $preserveBasket = false): void
    {
        $sku = Str::upper(trim($sku));
        $this->currentSku = $sku;

        $referenceFilter = trim($this->filterReference);
        $customerFilter = trim($this->filterCustomer);
        $trackingFilter = trim($this->filterTracking);
        $limit = $this->resolvePageLimit();

        $orders = Order_model::query()
            ->where('order_type_id', 3)
            ->where('status', 2)
            ->whereHas('order_items', function ($query) use ($sku) {
                $query->whereHas('variation', function ($variation) use ($sku) {
                    $variation->where('sku', $sku);
                });
            })
            ->when($referenceFilter !== '', function ($query) use ($referenceFilter) {
                $query->where('reference_id', 'LIKE', '%' . $referenceFilter . '%');
            })
            ->when($customerFilter !== '', function ($query) use ($customerFilter) {
                $query->whereHas('customer', function ($customer) use ($customerFilter) {
                    $customer->where(function ($inner) use ($customerFilter) {
                        $inner->where('company', 'LIKE', '%' . $customerFilter . '%')
                            ->orWhere('first_name', 'LIKE', '%' . $customerFilter . '%')
                            ->orWhere('last_name', 'LIKE', '%' . $customerFilter . '%');
                    });
                });
            })
            ->when($trackingFilter !== '', function ($query) use ($trackingFilter) {
                $tracking = $this->normalizeTracking($trackingFilter);
                $query->where('tracking_number', 'LIKE', '%' . $tracking . '%');
            })
            ->with(['order_items' => function ($query) use ($sku) {
                $query->whereHas('variation', function ($variation) use ($sku) {
                    $variation->where('sku', $sku);
                })->with(['stock:id,imei,serial_number']);
            }, 'customer:id,first_name,last_name,company'])
            ->orderBy('reference_id')
            ->limit($limit)
            ->get();

        $transformed = $this->transformOrders($orders);

        if ($transformed->isEmpty()) {
            if (! $preserveBasket) {
                $this->dispatchBasket = [];
                $this->picklist = [];
            }

            $this->orders = [];
            $this->scannedImeis = [];
            $this->activeOrderId = null;
            $this->stage = 'sku';
            $this->statusMessage = null;
            $this->errorMessage = $this->filtersActive()
                ? 'No pending orders match the current filters.'
                : 'No pending orders found for this SKU.';
            $this->rebuildPicklistIfNeeded();
            return;
        }

        $this->orders = $transformed->values()->all();
        $this->scannedImeis = [];
        $this->stage = 'imei';
        $this->errorMessage = null;

        if (! $preserveBasket) {
            $this->dispatchBasket = [];
            $this->picklist = [];
        } else {
            $this->synchroniseBasket();
        }

        $this->activeOrderId = null;
        $nextOrderId = $this->determineTargetOrderId();
        if ($nextOrderId !== null) {
            $this->activeOrderId = $nextOrderId;
        } else {
            $this->activeOrderId = $this->orders[0]['id'];
        }

        $orderCount = count($this->orders);
        $unitsPending = array_sum(array_map(function ($order) {
            return $order['remaining'] ?? 0;
        }, $this->orders));

        $filterNote = $this->filtersActive() ? ' with filters applied' : '';
        $this->statusMessage = "Orders loaded{$filterNote}: {$orderCount} order(s), {$unitsPending} unit(s) pending.";

        $this->rebuildPicklistIfNeeded();
    }

    private function transformOrders(Collection $orders): Collection
    {
        return $orders->map(function ($order) {
            $required = 0;
            $assigned = 0;
            $existing = [];

            foreach ($order->order_items as $item) {
                $quantity = (int) ($item->quantity ?? 1);
                $required += $quantity;

                if ($item->stock) {
                    $assigned += 1;
                    $existing[] = Str::upper((string) ($item->stock->imei ?? $item->stock->serial_number));
                }
            }

            $pending = max($required - $assigned, 0);
            if ($pending === 0) {
                return null;
            }

            $customerName = optional($order->customer)->company;
            if (! $customerName) {
                $customerName = trim(implode(' ', array_filter([
                    optional($order->customer)->first_name,
                    optional($order->customer)->last_name,
                ])));
            }

            return [
                'id' => $order->id,
                'reference' => $order->reference_id,
                'customer' => $customerName ?: '—',
                'tracking_number' => $order->tracking_number,
                'required' => $required,
                'assigned' => $assigned,
                'remaining' => $pending,
                'initial_remaining' => $pending,
                'scanned' => 0,
                'existing_imeis' => array_values(array_filter($existing)),
            ];
        })->filter();
    }

    private function determineTargetOrderId(): ?int
    {
        if ($this->activeOrderId) {
            $index = $this->getOrderIndex($this->activeOrderId);
            if ($index !== null && ($this->orders[$index]['remaining'] ?? 0) > 0) {
                return $this->activeOrderId;
            }
        }

        foreach (array_keys($this->dispatchBasket) as $basketOrderId) {
            $index = $this->getOrderIndex($basketOrderId);
            if ($index !== null && ($this->orders[$index]['remaining'] ?? 0) > 0) {
                return $basketOrderId;
            }
        }

        foreach ($this->orders as $order) {
            if (($order['remaining'] ?? 0) > 0) {
                return $order['id'];
            }
        }

        return null;
    }

    private function getOrderIndex(int $orderId): ?int
    {
        foreach ($this->orders as $index => $order) {
            if ($order['id'] === $orderId) {
                return $index;
            }
        }

        return null;
    }

    private function imeiAlreadyCaptured(string $imei): bool
    {
        $sets = array_values($this->scannedImeis ?: []);
        $scanned = $sets ? array_merge(...$sets) : [];

        return in_array($imei, $scanned, true);
    }

    private function orderHasExistingImei(int $orderIndex, string $imei): bool
    {
        $existing = $this->orders[$orderIndex]['existing_imeis'] ?? [];
        return in_array($imei, $existing, true);
    }

    private function imeiAlreadyLinked(string $imei): bool
    {
        foreach ($this->orders as $order) {
            if (in_array($imei, $order['existing_imeis'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    private function addToDispatchBasket(int $orderId): void
    {
        if (isset($this->dispatchBasket[$orderId])) {
            return;
        }

        $orderIndex = $this->getOrderIndex($orderId);
        if ($orderIndex === null) {
            $this->errorMessage = 'Order is not available in the current queue.';
            return;
        }

        if (($this->orders[$orderIndex]['remaining'] ?? 0) <= 0) {
            $this->errorMessage = 'Order already fulfilled; cannot add to basket.';
            return;
        }

        $this->dispatchBasket[$orderId] = $this->orders[$orderIndex];
        $this->statusMessage = 'Order added to dispatch basket.';
        $this->rebuildPicklistIfNeeded();
    }

    private function generatePicklist(bool $announce): void
    {
        $source = $this->picklistSource();

        if (empty($source)) {
            $this->picklist = [];
            if ($announce) {
                $this->errorMessage = 'No orders available to build a picklist.';
            }
            return;
        }

        $this->picklist = array_map(function (array $order) {
            return [
                'order_id' => $order['id'] ?? null,
                'reference' => $order['reference'] ?? '—',
                'customer' => $order['customer'] ?? '—',
                'units_required' => $order['required'] ?? 0,
                'units_remaining' => $order['remaining'] ?? 0,
                'tracking' => $order['tracking_number'] ?? '—',
            ];
        }, array_values($source));

        if ($announce) {
            $this->statusMessage = 'Picklist generated for the active queue.';
        }
    }

    private function picklistSource(): array
    {
        if (! empty($this->dispatchBasket)) {
            return $this->dispatchBasket;
        }

        return $this->orders;
    }

    private function rebuildPicklistIfNeeded(): void
    {
        if (! empty($this->picklist)) {
            $this->generatePicklist(false);
        }
    }

    private function filtersActive(): bool
    {
        $hasTextFilter = trim($this->filterReference) !== ''
            || trim($this->filterCustomer) !== ''
            || trim($this->filterTracking) !== '';

        $limit = (int) $this->filterLimit;

        return $hasTextFilter || ($limit > 0 && $limit !== self::DEFAULT_LIMIT);
    }

    private function resolvePageLimit(): int
    {
        $limit = (int) $this->filterLimit;

        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $limit = min($limit, self::MAX_LIMIT);
        $this->filterLimit = $limit;

        return $limit;
    }

    private function synchroniseBasket(): void
    {
        if (empty($this->dispatchBasket)) {
            return;
        }

        $indexedOrders = [];
        foreach ($this->orders as $order) {
            $indexedOrders[$order['id']] = $order;
        }

        foreach (array_keys($this->dispatchBasket) as $orderId) {
            if (isset($indexedOrders[$orderId])) {
                $this->dispatchBasket[$orderId] = $indexedOrders[$orderId];
                continue;
            }

            unset($this->dispatchBasket[$orderId]);
        }
    }

    private function stockExists(string $identifier): bool
    {
        if (ctype_digit($identifier)) {
            return Stock_model::where('imei', $identifier)->exists();
        }

        return Stock_model::where('serial_number', $identifier)->exists();
    }

    private function normalizeTracking(string $value): string
    {
        $trimmed = trim($value);
        if (strlen($trimmed) === 21) {
            return substr($trimmed, 1);
        }

        return $trimmed;
    }

    private function resolveTesterName(): string
    {
        if (session()->has('fname')) {
            return (string) session('fname');
        }

        $user = session('user');
        if ($user && isset($user->first_name)) {
            return (string) $user->first_name;
        }

        return 'Packing';
    }
}
