<?php

namespace App\Services\Orders;

use App\Models\Currency_model;
use App\Models\ExchangeRate;
use App\Models\Listed_stock_verification_model;
use App\Models\Order_item_model;
use App\Models\Order_model;
use App\Models\Process_model;
use App\Models\Process_stock_model;
use App\Models\Variation_model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderTableQuery
{
    public static function perPage(?Request $request = null): int
    {
        $request = $request ?: request();
        $perPage = $request->input('per_page');

        if (! is_numeric($perPage)) {
            return 10;
        }

        $perPage = (int) $perPage;

        return $perPage > 0 ? $perPage : 10;
    }

    /**
     * Build the base orders query used by the Orders table / exports.
     */
    public static function build(?Request $request = null)
    {
        $request = $request ?: request();

        $excludeTopup = $request->input('exclude_topup', []);
        $differenceVariations = [];

        if (is_array($excludeTopup) && count(array_filter($excludeTopup))) {
            $listedStockVerification = Listed_stock_verification_model::whereIn('process_id', $excludeTopup)->get();

            $variations = Variation_model::whereIn('id', $listedStockVerification->pluck('variation_id'))
                ->get()
                ->keyBy('id');

            foreach ($variations as $variation) {
                $difference = $listedStockVerification->where('variation_id', $variation->id)->sum('qty_change') - $variation->listed_stock;
                if ($difference > 0) {
                    $differenceVariations[$variation->id] = $difference;
                }
            }
        }

        $startDate = null;
        if ($request->filled('start_date') && $request->filled('start_time')) {
            $startDate = $request->input('start_date') . ' ' . $request->input('start_time');
        } elseif ($request->filled('start_date')) {
            $startDate = $request->input('start_date');
        }

        $endDate = null;
        if ($request->filled('end_date') && $request->filled('end_time')) {
            $endDate = $request->input('end_date') . ' ' . $request->input('end_time');
        } elseif ($request->filled('end_date')) {
            $endDate = $request->input('end_date') . ' 23:59:59';
        }

        $dataFilter = (int) $request->input('data');

        $variationIds = [];
        if ($dataFilter === 1) {
            $variationIds = Variation_model::withoutGlobalScope('Status_not_3_scope')
                ->select('id')
                ->when($request->filled('product'), function ($query) use ($request) {
                    $query->where('product_id', '=', $request->input('product'));
                })
                ->when($request->filled('sku'), function ($query) use ($request) {
                    $query->where('sku', 'LIKE', '%' . $request->input('sku') . '%');
                })
                ->when($request->filled('category'), function ($query) use ($request) {
                    $query->whereHas('product', function ($productQuery) use ($request) {
                        $productQuery->where('category', '=', $request->input('category'));
                    });
                })
                ->when($request->filled('brand'), function ($query) use ($request) {
                    $query->whereHas('product', function ($productQuery) use ($request) {
                        $productQuery->where('brand', '=', $request->input('brand'));
                    });
                })
                ->when($request->filled('storage'), function ($query) use ($request) {
                    $query->where('variation.storage', 'LIKE', $request->input('storage') . '%');
                })
                ->when($request->filled('color'), function ($query) use ($request) {
                    $query->where('variation.color', 'LIKE', $request->input('color') . '%');
                })
                ->when($request->filled('grade'), function ($query) use ($request) {
                    $query->where('variation.grade', 'LIKE', $request->input('grade') . '%');
                })
                ->pluck('id')
                ->toArray();
        }

        $orders = Order_model::with([
                'customer',
                'customer.orders',
                'order_items',
                'order_items.variation',
                'order_items.variation.product',
                'order_items.variation.grade_id',
                'order_items.stock',
                'order_items.replacement',
                'transactions',
                'order_charges',
            ])
            ->when($request->input('type') === null || $request->input('type') === '', function ($query) {
                $query->where('orders.order_type_id', 3);
            })
            ->when($request->boolean('items'), function ($query) {
                $query->whereHas('order_items', operator: '>', count: 1);
            })
            ->when($startDate, function ($query) use ($startDate, $request) {
                if ((int) $request->input('adm') > 0) {
                    $query->where('orders.processed_at', '>=', $startDate);
                } else {
                    $query->where('orders.created_at', '>=', $startDate);
                }
            })
            ->when($endDate, function ($query) use ($endDate, $request) {
                if ((int) $request->input('adm') > 0) {
                    $query->where('orders.processed_at', '<=', $endDate)->orderBy('orders.processed_at', 'desc');
                } else {
                    $query->where('orders.created_at', '<=', $endDate);
                }
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('orders.status', $request->input('status'));
            })
            ->when($request->filled('adm'), function ($query) use ($request) {
                if ((int) $request->input('adm') === 0) {
                    $query->where('orders.processed_by', null);
                } else {
                    $query->where('orders.processed_by', $request->input('adm'));
                }
            })
            ->when($request->filled('care'), function ($query) {
                $query->whereHas('order_items', function ($subQuery) {
                    $subQuery->whereNotNull('care_id');
                });
            })
            ->when($request->input('missing') === 'reimburse', function ($query) {
                $query->whereHas('order_items.linked_child', function ($childQuery) {
                    $childQuery->whereHas('order', function ($orderQuery) {
                        $orderQuery->where('orders.status', '!=', 1);
                    });
                })->where('status', 3)->orderBy('orders.updated_at', 'desc');
            })
            ->when($request->input('missing') === 'refund', function ($query) {
                $query->whereDoesntHave('order_items.linked_child')
                    ->whereHas('order_items.stock', function ($stockQuery) {
                        $stockQuery->whereNotNull('status');
                    })
                    ->where('status', 6)
                    ->orderBy('orders.updated_at', 'desc');
            })
            ->when($request->input('missing') === 'charge', function ($query) {
                $query->where('status', '!=', 2)
                    ->whereNull('charges')
                    ->where('processed_at', '<=', now()->subHours(12));
            })
            ->when($request->input('missing') === 'scan', function ($query) {
                $query->whereIn('status', [3, 6])
                    ->whereNull('scanned')
                    ->where('processed_at', '<=', now()->subHours(24));
            })
            ->when($request->input('missing') === 'purchase', function ($query) {
                $query->whereHas('order_items.stock', function ($stockQuery) {
                    $stockQuery->whereNull('status');
                });
            })
            ->when($request->input('missing') === 'processed_at', function ($query) {
                $query->whereIn('status', [3, 6])
                    ->whereNull('processed_at');
            })
            ->when((int) $request->input('transaction') === 1, function ($query) {
                $query->whereHas('transactions', function ($transactionQuery) {
                    $transactionQuery->whereNull('status');
                });
            })
            ->when($request->filled('order_id'), function ($query) use ($request) {
                $orderId = $request->input('order_id');

                if (str_contains($orderId, '<=')) {
                    $value = str_replace('<=', '', $orderId);
                    $query->where('orders.reference_id', '<=', $value);
                } elseif (str_contains($orderId, '>=')) {
                    $value = str_replace('>=', '', $orderId);
                    $query->where('orders.reference_id', '>=', $value);
                } elseif (str_contains($orderId, '<')) {
                    $value = str_replace('<', '', $orderId);
                    $query->where('orders.reference_id', '<', $value);
                } elseif (str_contains($orderId, '>')) {
                    $value = str_replace('>', '', $orderId);
                    $query->where('orders.reference_id', '>', $value);
                } elseif (str_contains($orderId, '-')) {
                    $range = explode('-', $orderId);
                    $query->whereBetween('orders.reference_id', $range);
                } elseif (str_contains($orderId, ',')) {
                    $values = array_map('trim', explode(',', $orderId));
                    $query->whereIn('orders.reference_id', $values);
                } elseif (str_contains($orderId, ' ')) {
                    $values = array_map('trim', explode(' ', $orderId));
                    $query->whereIn('orders.reference_id', $values);
                } else {
                    $query->where('orders.reference_id', 'LIKE', $orderId . '%');
                }
            })
            ->when($request->filled('sku'), function ($query) use ($request, $dataFilter, $variationIds) {
                $query->whereHas('order_items.variation', function ($variationQuery) use ($request, $dataFilter, $variationIds) {
                    $variationQuery->where('sku', 'LIKE', '%' . $request->input('sku') . '%');
                    if ($dataFilter === 1) {
                        $variationQuery->whereIn('variation_id', $variationIds);
                    }
                });
            })
            ->when($request->filled('imei'), function ($query) use ($request) {
                $imeiValue = $request->input('imei');
                if (str_contains($imeiValue, ' ')) {
                    $imeis = array_map('trim', explode(' ', $imeiValue));
                    $query->whereHas('order_items.stock', function ($stockQuery) use ($imeis) {
                        $stockQuery->whereIn('imei', $imeis);
                    });
                } else {
                    $query->whereHas('order_items.stock', function ($stockQuery) use ($imeiValue) {
                        $stockQuery->where('imei', 'LIKE', '%' . $imeiValue . '%');
                    });
                }
            })
            ->when($request->filled('currency'), function ($query) use ($request) {
                $query->where('currency', $request->input('currency'));
            })
            ->when($request->filled('tracking_number'), function ($query) use ($request) {
                $trackingNumber = $request->input('tracking_number');
                if (strlen($trackingNumber) === 21) {
                    $trackingNumber = substr($trackingNumber, 1);
                }
                $query->where('tracking_number', 'LIKE', '%' . $trackingNumber . '%');
            })
            ->when((int) $request->input('with_stock') === 2, function ($query) {
                $query->whereHas('order_items', function ($itemsQuery) {
                    $itemsQuery->where('stock_id', 0);
                });
            })
            ->when((int) $request->input('with_stock') === 1, function ($query) {
                $query->whereHas('order_items', function ($itemsQuery) {
                    $itemsQuery->where('stock_id', '>', 0);
                });
            })
            ->when((int) $request->input('sort') === 4, function ($query) {
                $query->join('order_items', 'order_items.order_id', '=', 'orders.id')
                    ->join('variation', 'order_items.variation_id', '=', 'variation.id')
                    ->join('products', 'variation.product_id', '=', 'products.id')
                    ->where([
                        'orders.deleted_at' => null,
                        'order_items.deleted_at' => null,
                        'variation.deleted_at' => null,
                        'products.deleted_at' => null,
                    ])
                    ->orderBy('products.model', 'ASC')
                    ->orderBy('variation.storage', 'ASC')
                    ->orderBy('variation.color', 'ASC')
                    ->orderBy('variation.grade', 'ASC')
                    ->orderBy('variation.sku', 'ASC')
                    ->select(
                        'orders.id',
                        'orders.reference_id',
                        'orders.customer_id',
                        'orders.delivery_note_url',
                        'orders.label_url',
                        'orders.tracking_number',
                        'orders.status',
                        'orders.processed_by',
                        'orders.created_at',
                        'orders.processed_at'
                    );
            })
            ->when((int) $request->input('adm') > 0, function ($query) {
                $query->orderBy('orders.processed_at', 'desc');
            })
            ->orderBy('orders.reference_id', 'desc');

        if (! empty($differenceVariations) && is_array($excludeTopup) && count(array_filter($excludeTopup))) {
            $ordersClone = $orders->clone()
                ->whereHas('order_items', function ($itemsQuery) use ($differenceVariations) {
                    $itemsQuery->whereIn('variation_id', array_keys($differenceVariations));
                })
                ->get();

            $idsToExclude = [];

            foreach ($ordersClone as $order) {
                foreach ($order->order_items as $item) {
                    if (isset($differenceVariations[$item->variation_id]) && $differenceVariations[$item->variation_id] > 0) {
                        if (! in_array($item->order_id, $idsToExclude, true)) {
                            $idsToExclude[] = $item->order_id;
                        }
                        $differenceVariations[$item->variation_id] -= 1;
                    }
                }
            }

            if (! empty($idsToExclude)) {
                $orders->whereNotIn('orders.id', $idsToExclude);
            }
        }

        return $orders;
    }
}
