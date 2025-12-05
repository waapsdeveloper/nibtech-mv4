<?php

namespace App\Models;

use App\Http\Controllers\ListingController;
use App\Http\Controllers\RefurbedAPIController;
use App\Services\RefurbedShippingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Order_model extends Model
{
    use HasFactory;

    use SoftDeletes;
    protected $table = 'orders';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'reference_id',
        'marketplace_id',
        'status',
        'currency',
        'processed_by',
        'order_type_id',
        'scanned',
    ];

    public const REFURBED_STOCK_SYNCED_REFERENCE = 'REFURBED_STOCK_SYNCED';

    private const BMPRO_MARKETPLACE_BY_CURRENCY = [
        'EUR' => 2,
        'GBP' => 3,
    ];

    public function transactions()
    {
        return $this->hasMany(Account_transaction_model::class, 'order_id', 'id');
    }
    public function transaction()
    {
        return $this->hasOne(Account_transaction_model::class, 'order_id', 'id');
    }
    public function order_charges()
    {
        return $this->hasMany(Order_charge_model::class, 'order_id', 'id');
    }
    public function merge_transaction_charge()
    {
        $message = "";
        $change = false;
        $add = false;
        $transactions = $this->transactions->where('status',null);
        $charges_sum = $this->order_charges->sum('amount');
        if($transactions->count() > 0){
            $order_charges = $this->order_charges;
            foreach($transactions as $transaction){
                $latest_transaction_ref = Account_transaction_model::where('reference_id', '!=', null)
                    ->whereRaw('reference_id REGEXP "^[0-9]+$"')
                    ->orderByDesc('reference_id')
                    ->first()
                    ->reference_id;
                $description = trim($transaction->description);
                $message .= "Order Transaction name: ".$description . "\n";
                foreach($order_charges as $order_charge){
                    $charge_name = trim($order_charge->charge->name);

                    if($description == 'sales'){
                        $transaction->reference_id = $latest_transaction_ref+1;
                        $transaction->status = 1;
                        $transaction->save();
                        $add = true;
                    }elseif($charge_name == $description){
                        $amount = $transaction->amount;
                        if($amount < 0){
                            $amount = $amount * -1;
                        }
                        $order_charge->transaction_id = $transaction->id;
                        $order_charge->amount = $amount;
                        $order_charge->save();
                        $transaction->reference_id = $latest_transaction_ref+1;
                        $transaction->status = 1;
                        $transaction->save();
                        $change = true;
                        $message .= "Transaction charge merged for order ".$this->reference_id." and transaction ".$transaction->reference_id;
                        $add = true;
                    }
                }
                if($add == false){
                    if($description == 'refunds' && -$transaction->amount != $this->price){
                        $amount = $transaction->amount;
                        if($amount < 0){
                            $amount = $amount * -1;
                        }
                        $charge = Charge_model::where(['order_type_id'=>3,'status'=>1, 'name'=>'refunds'])->first();
                        $order_charge = Order_charge_model::firstOrNew(['order_id'=>$this->id,'charge_value_id'=>$charge->current_value->id]);
                        $order_charge->transaction_id = $transaction->id;
                        $order_charge->amount = $amount;
                        $order_charge->save();
                        $transaction->reference_id = $latest_transaction_ref+1;
                        $transaction->status = 1;
                        $transaction->save();
                        $change = true;
                        $message .= "Transaction charge merged for order ".$this->reference_id." and transaction ".$transaction->reference_id;
                        $add = true;
                    }elseif($description == 'refunds' && -$transaction->amount == $this->price){
                        $transaction->reference_id = $latest_transaction_ref+1;
                        $transaction->status = 1;
                        $transaction->save();
                        $add = true;
                    }elseif($description == 'avoir_sales_fees'){
                        $amount = $transaction->amount;
                        $amount = $amount * -1;

                        $charge = Charge_model::where(['order_type_id'=>3,'status'=>1, 'name'=>'avoir_sales_fees'])->first();
                        $order_charge = Order_charge_model::firstOrNew(['order_id'=>$this->id,'charge_value_id'=>$charge->current_value->id]);
                        $order_charge->transaction_id = $transaction->id;
                        $order_charge->amount = $amount;
                        $order_charge->save();
                        $transaction->reference_id = $latest_transaction_ref+1;
                        $transaction->status = 1;
                        $transaction->save();
                        $change = true;
                        $message .= "Transaction charge merged for order ".$this->reference_id." and transaction ".$transaction->reference_id;
                        $add = true;
                    }
                }

            }
            if($change == true){
                $charges_sum = Order_charge_model::where('order_id',$this->id)->sum('amount');
                $this->charges = $charges_sum;
                $this->save();
            }
            $message .= "<br>";
        }
        if($this->charges != $charges_sum){
            $this->charges = $charges_sum;
            $this->save();
        }

        return $message;

    }
    public function charge_values()
    {
        return $this->hasManyThrough(Charge_value_model::class, Order_charge_model::class, 'order_id', 'id', 'id', 'charge_value_id');
    }

    public function currency_id()
    {
        return $this->hasOne(Currency_model::class, 'id', 'currency');
    }
    public function customer()
    {
        return $this->belongsTo(Customer_model::class, 'customer_id', 'id');
    }
    public function order_status()
    {
        return $this->hasOne(Order_status_model::class, 'id', 'status');
    }
    public function order_type()
    {
        return $this->hasOne(Multi_type_model::class, 'id', 'order_type_id');
    }
    public function last_update()
    {
        return $this->hasOne(Stock_model::class, 'order_id', 'id')->orderBy('updated_at','desc');
    }
    public function order_items()
    {
        return $this->hasMany(Order_item_model::class, 'order_id', 'id');
    }
    public function order_items_available()
    {
        return $this->hasMany(Order_item_model::class, 'order_id', 'id')->whereHas('stock', function ($q) {
            $q->where('status',1);
        });
    }
    public function stocks()
    {
        return $this->hasMany(Stock_model::class, 'order_id', 'id');
    }
    public function available_stocks()
    {
        return $this->hasMany(Stock_model::class, 'order_id', 'id')->where('status',1);
    }
    public function sold_stocks()
    {
        return $this->hasMany(Stock_model::class, 'order_id', 'id')->where('status',2);
    }
    public function exchange_items()
    {
        return $this->hasMany(Order_item_model::class, 'reference_id', 'reference_id')->whereHas('order', function ($q) {
            $q->where('order_type_id',5);
        });
    }
    public function order_issues()
    {
        return $this->hasMany(Order_issue_model::class, 'order_id', 'id');
    }
    public function marketplace()
    {
        return $this->hasOne(Marketplace_model::class, 'id', 'marketplace_id');
    }
    public function process()
    {
        return $this->hasMany(Process_model::class, 'order_id', 'id');
    }
    // Define a method to get variations associated with order items
    public function variation()
    {
        return $this->hasManyThrough(Variation_model::class, Order_item_model::class, 'order_id', 'id', 'id', 'variation_id');
    }
    public function admin()
    {
        return $this->hasOne(Admin_model::class, 'id', 'processed_by');
    }
    public function payment_method()
    {
        return $this->hasOne(Payment_method_model::class, 'id', 'payment_method_id');
    }

    public function addresses(){
        return $this->hasMany(Address_model::class, 'order_id', 'id');
    }

    public function shipping_address()
    {
        return $this->hasOne(Address_model::class, 'order_id', 'id')->where('type', 27)->orderByDesc('id');
    }

    public function billing_address()
    {
        return $this->hasOne(Address_model::class, 'order_id', 'id')->where('type', 28)->orderByDesc('id');
    }


    public function updateOrderInDB($orderObj, $invoice = false, $bm, $currency_codes, $country_codes)
    {
        // Your implementation here using Eloquent ORM
        // Example:
        // $orderObj = (object) $orderObj[0];
        if(isset($orderObj->order_id)){
            $customer_model = new Customer_model();
            $marketplaceId = (int) ($orderObj->marketplace_id ?? 1);
            $order = Order_model::firstOrNew([
                'reference_id' => $orderObj->order_id,
                'marketplace_id' => $marketplaceId,
            ]);
            if($order->customer_id == null){
                $order->customer_id = $customer_model->updateCustomerInDB($orderObj, false, $currency_codes, $country_codes);
            }
            $order->status = $this->mapStateToStatus($orderObj);
            if($order->status == null){
                Log::info("Order status is null", $orderObj);
            }
            $order->currency = $currency_codes[$orderObj->currency];
            $order->order_type_id = 3;
            $order->marketplace_id = $marketplaceId;
            $order->price = $orderObj->price;
            $order->delivery_note_url = $orderObj->delivery_note;
            if($order->label_url == null){
                $label = $bm->getOrderLabel($orderObj->order_id);
                if($label != null && $label->results != null){
                    $order->label_url = $label->results[0]->labelUrl;
                }
            }
            if($orderObj->payment_method != null){
                $payment_method = Payment_method_model::firstOrNew(['name'=>$orderObj->payment_method]);
                $payment_method->save();
                $order->payment_method_id = $payment_method->id;
            }
            if($invoice == true){
                $order->processed_by = session('user_id');
                $order->processed_at = now()->format('Y-m-d H:i:s');
            }
            if($invoice == false && $order->processed_by == null && $orderObj->date_shipping != null){
                $order->processed_at = Carbon::parse($orderObj->date_shipping)->format('Y-m-d H:i:s');
            }

            if($order->tracking_number == null){
                $order->tracking_number = $orderObj->tracking_number;
            }

            $order->created_at = Carbon::parse($orderObj->date_creation)->format('Y-m-d H:i:s');
            $order->updated_at = Carbon::parse($orderObj->date_modification)->format('Y-m-d H:i:s');

            // echo Carbon::parse($orderObj->date_creation)->format('Y-m-d H:i:s'). "       ";
            // ... other fields
            $order->save();

        }
        // print_r(Order_model::find($order->id));
        // echo "----------------------------------------";
    }

    /**
     * Store a Refurbed order (including customer + line items) that was fetched
     * through the Refurbed Orders API.
     */
    public function storeRefurbedOrderInDB(
        $orderPayload,
        ?array $orderItems = null,
        array $currency_codes = [],
        array $country_codes = [],
        $bm = null,
        bool $care = false
    ): ?self {
        $orderData = $this->normalizeRefurbedPayload($orderPayload);

        $orderNumber = $orderData['order_number']
            ?? $orderData['reference']
            ?? $orderData['id']
            ?? null;

        if (! $orderNumber) {
            Log::warning('Refurbed order missing reference/order_number', ['payload' => $orderData]);
            return null;
        }

        if (empty($currency_codes)) {
            $currency_codes = Currency_model::pluck('id', 'code')->map(fn ($id) => (int) $id)->toArray();
        }

        if (empty($country_codes)) {
            $country_codes = Country_model::pluck('id', 'code')->map(fn ($id) => (int) $id)->toArray();
        }

        $countryCode = $orderData['country']
            ?? ($orderData['shipping_address']['country'] ?? null)
            ?? null;
        $countryId = $countryCode
            ? ($country_codes[$countryCode] ?? Country_model::where('code', $countryCode)->value('id'))
            : null;

        $currencyCode = $orderData['settlement_currency_code']
            ?? $orderData['currency']
            ?? $orderData['currency_code']
            ?? 'EUR';
        $currencyMissingFromLookup = ! isset($currency_codes[$currencyCode]);
        $currencyId = $this->resolveCurrencyIdForOrder($currencyCode, $currency_codes, $countryId);

        if ($currencyMissingFromLookup) {
            Log::warning('Refurbed order currency not found', [
                'currency' => $currencyCode,
                'order' => $orderNumber,
                'resolved_currency_id' => $currencyId,
            ]);
        }

        $marketplaceId = 4;
        $order = Order_model::firstOrNew([
            'reference_id' => $orderNumber,
            'marketplace_id' => $marketplaceId,
        ]);
        $order->marketplace_id = $marketplaceId;
        if (! $order->reference) {
            $order->reference = $orderData['id'] ?? null;
        }
        $order->status = $this->mapRefurbedOrderState($orderData['state'] ?? 'NEW');
        $order->currency = $currencyId ?? $order->currency;

        if (Schema::hasColumn($order->getTable(), 'order_type_id')) {
            $order->order_type_id = $care ? 5 : 3;
        }

        if ($countryId) {
            if (Schema::hasColumn($order->getTable(), 'country_id')) {
                $order->country_id = $countryId;
            } elseif (Schema::hasColumn($order->getTable(), 'country')) {
                $order->country = $countryCode;
            }
        } elseif ($countryCode && Schema::hasColumn($order->getTable(), 'country')) {
            $order->country = $countryCode;
        }

        $order->price = $this->extractNumeric(
            $orderData['settlement_total_paid']
            ?? $orderData['total_amount']
            ?? $orderData['total_paid']
            ?? $orderData['price']
            ?? $order->price
        );
        $order->delivery_note_url = $orderData['delivery_note'] ?? $order->delivery_note_url;

        if (! empty($orderData['created_at'])) {
            $order->created_at = Carbon::parse($orderData['created_at'])->format('Y-m-d H:i:s');
        }

        if (! empty($orderData['updated_at'])) {
            $order->updated_at = Carbon::parse($orderData['updated_at'])->format('Y-m-d H:i:s');
        }

        $order->save();
        $legacyOrder = $this->buildLegacyOrderObject(
            $orderData,
            $orderItems,
            $orderNumber,
            $currencyCode,
            $country_codes,
            $orderData['customer_email'] ?? ($orderData['customer']['email'] ?? null)
        );

        $customerModel = new Customer_model();
        $customerId = $customerModel->updateCustomerInDB(
            $legacyOrder,
            false,
            $currency_codes,
            $country_codes,
            $order->id,
            $legacyOrder->customer_email ?? null,
            'Refurbed'
        );
        if ($customerId) {
            $order->customer_id = $customerId;
            $order->save();
        }

        if (! empty($legacyOrder->orderlines)) {
            $orderItemModel = new Order_item_model();
            $orderItemModel->updateOrderItemsInDB($legacyOrder, null, $bm, $care);
        } else {
            Log::info('Refurbed order has no items array', ['order' => $orderNumber]);
        }

        $this->syncBackMarketStockForRefurbedOrder($order, $legacyOrder);

        $this->maybeAutoCreateRefurbedLabel($order);

        return $order->fresh(['order_items', 'customer']);
    }

    protected function extractBmproOrderItems(array $orderData): array
    {
        $candidates = ['items', 'order_items', 'order_lines', 'lines'];

        foreach ($candidates as $key) {
            $value = $orderData[$key] ?? null;
            if (is_array($value)) {
                return $value;
            }
        }

        return [];
    }

    protected function buildLegacyBmproOrderObject(
        array $orderData,
        ?array $orderItems,
        string $orderNumber,
        string $currencyCode,
        array $country_codes
    ): object {
        $orderObj = new \stdClass();
        $orderObj->order_id = $orderNumber;
        $orderObj->currency = $currencyCode;
        $orderObj->price = $this->extractNumeric(
            $orderData['total_price']
            ?? data_get($orderData, 'financials.total_paid')
            ?? data_get($orderData, 'order_value.amount')
            ?? data_get($orderData, 'totals.grand_total')
            ?? 0
        ) ?? 0;
        $orderObj->delivery_note = data_get($orderData, 'documents.invoice');
        $orderObj->payment_method = $orderData['payment_method'] ?? data_get($orderData, 'financials.payment_method');
        $orderObj->tracking_number = data_get($orderData, 'tracking.number') ?? $orderData['tracking_number'] ?? null;
        $orderObj->date_creation = $orderData['created_at'] ?? $orderData['order_date'] ?? now()->toDateTimeString();
        $orderObj->date_modification = $orderData['updated_at'] ?? $orderObj->date_creation;
        $orderObj->date_shipping = $orderData['shipped_at'] ?? null;
        $orderObj->customer_email = $orderData['customer']['email'] ?? $orderData['buyer']['email'] ?? null;
        $orderObj->state = $orderData['fulfillment_status'] ?? $orderData['state'] ?? null;

        $billingSource = $this->normalizeBmproAddress(
            $orderData['billing_address']
            ?? $orderData['invoice_address']
            ?? $orderData['customer']
            ?? []
        );

        $shippingSource = $this->normalizeBmproAddress(
            $orderData['shipping_address']
            ?? $orderData['delivery_address']
            ?? $orderData['shipping']
            ?? $billingSource
        );

        $orderObj->billing_address = (object) $this->buildLegacyAddressArray($billingSource, $country_codes, $orderObj->customer_email);
        $orderObj->shipping_address = (object) $this->buildLegacyAddressArray($shippingSource, $country_codes, $orderObj->customer_email);

        $orderObj->orderlines = [];
        foreach ($orderItems ?? [] as $itemPayload) {
            $line = $this->buildLegacyBmproOrderLine($itemPayload, $orderObj->state);
            if ($line) {
                $orderObj->orderlines[] = (object) $line;
            }
        }

        return $orderObj;
    }

    protected function normalizeBmproAddress($source): array
    {
        if (is_object($source)) {
            $source = json_decode(json_encode($source), true) ?? [];
        }

        if (! is_array($source)) {
            return [];
        }

        $line1 = trim(($source['street'] ?? $source['street1'] ?? $source['address_line1'] ?? $source['address1'] ?? '') . ' ' . ($source['house_number'] ?? ''));
        $street2 = $source['street2'] ?? $source['address_line2'] ?? $source['address2'] ?? '';

        return [
            'company' => $source['company'] ?? $source['business_name'] ?? 'BM Pro Customer',
            'first_name' => $source['first_name'] ?? $source['given_name'] ?? ($source['name'] ?? 'Buyer'),
            'last_name' => $source['last_name'] ?? $source['family_name'] ?? ($source['surname'] ?? 'Customer'),
            'street' => $line1 ?: ($source['street'] ?? ''),
            'street2' => $street2,
            'postal_code' => $source['postal_code'] ?? $source['zip'] ?? $source['postcode'] ?? '',
            'country' => strtoupper($source['country'] ?? $source['country_code'] ?? 'GB'),
            'city' => $source['city'] ?? $source['town'] ?? '',
            'phone' => $source['phone'] ?? $source['phone_number'] ?? $source['mobile'] ?? '',
            'email' => $source['email'] ?? null,
        ];
    }

    protected function buildLegacyBmproOrderLine($itemPayload, $fallbackState = null): ?array
    {
        $item = $this->normalizeRefurbedPayload($itemPayload);

        $referenceId = $item['id']
            ?? $item['order_line_id']
            ?? $item['order_item_id']
            ?? $item['line_id']
            ?? (string) Str::uuid();

        $sku = $item['sku'] ?? $item['seller_sku'] ?? $item['merchant_sku'] ?? null;
        $listingId = $item['listing_id'] ?? $item['listingId'] ?? null;

        $quantity = (int) ($item['quantity'] ?? $item['qty'] ?? 1);
        if ($quantity <= 0) {
            $quantity = 1;
        }

        $price = $this->resolveRefurbedItemPrice($item);
        if ($price === null) {
            $price = $this->extractNumeric($item['unit_price'] ?? $item['line_price'] ?? null);
        }

        $state = $item['fulfillment_status'] ?? $item['state'] ?? $fallbackState ?? 'PENDING';

        return [
            'id' => $referenceId,
            'listing_id' => $listingId,
            'sku' => $sku,
            'quantity' => $quantity,
            'price' => $price ?? 0,
            'state' => $this->mapBmproOrderItemState($state),
            'imei' => $item['imei'] ?? null,
            'serial_number' => $item['serial_number'] ?? $item['serial'] ?? null,
            'title' => $item['title'] ?? $item['product_title'] ?? null,
        ];
    }

    protected function mapBmproOrderState(?string $state): int
    {
        $state = strtoupper(trim((string) $state));

        return match ($state) {
            'READY_TO_SHIP', 'PROCESSING', 'ACCEPTED', 'ACKNOWLEDGED' => 2,
            'FULFILLED', 'SHIPPED', 'DELIVERED', 'COMPLETED' => 3,
            'CANCELLED', 'VOID' => 4,
            'RETURNED', 'REFUNDED' => 6,
            default => 1,
        };
    }

    protected function mapBmproOrderItemState(?string $state): int
    {
        $state = strtoupper(trim((string) $state));

        return match ($state) {
            'READY_TO_SHIP', 'PROCESSING', 'ACCEPTED', 'ACKNOWLEDGED' => 2,
            'FULFILLED', 'SHIPPED', 'DELIVERED', 'COMPLETED' => 3,
            'CANCELLED', 'VOID' => 5,
            'RETURNED', 'REFUNDED' => 6,
            default => 1,
        };
    }

    protected function resolveBmproMarketplaceId(?string $currencyCode, ?int $marketplaceId): int
    {
        if ($marketplaceId) {
            return $marketplaceId;
        }

        $currencyCode = strtoupper($currencyCode ?? 'EUR');

        return self::BMPRO_MARKETPLACE_BY_CURRENCY[$currencyCode]
            ?? self::BMPRO_MARKETPLACE_BY_CURRENCY['EUR'];
    }
    public function storeBMProOrderInDB(
        $orderPayload,
        ?array $orderItems = null,
        array $currency_codes = [],
        array $country_codes = [],
        ?int $marketplaceId = null,
        ?string $currencyHint = null
    ): ?self {
        $orderData = $this->normalizeRefurbedPayload($orderPayload);

        $orderNumber = $orderData['id']
            ?? $orderData['order_number']
            ?? $orderData['reference']
            ?? $orderData['external_reference']
            ?? null;

        if (! $orderNumber) {
            Log::warning('BMPRO order missing identifier', ['payload' => $orderData]);
            return null;
        }

        if ($orderItems === null) {
            $orderItems = $this->extractBmproOrderItems($orderData);
        }

        if (empty($currency_codes)) {
            $currency_codes = Currency_model::pluck('id', 'code')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        }

        if (empty($country_codes)) {
            $country_codes = Country_model::pluck('id', 'code')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        }

        $currencyCode = strtoupper(
            $orderData['currency']
            ?? data_get($orderData, 'financials.currency')
            ?? data_get($orderData, 'order_value.currency')
            ?? $currencyHint
            ?? 'EUR'
        );

        $currencyId = $this->resolveCurrencyIdForOrder($currencyCode, $currency_codes, null);
        $marketplaceId = $this->resolveBmproMarketplaceId($currencyCode, $marketplaceId);

        $order = Order_model::firstOrNew([
            'reference_id' => $orderNumber,
            'marketplace_id' => $marketplaceId,
        ]);
        $order->marketplace_id = $marketplaceId;
        $order->currency = $currencyId ?? $order->currency;
        $order->status = $this->mapBmproOrderState($orderData['fulfillment_status'] ?? $orderData['state'] ?? null);
        if (! $order->reference) {
            $order->reference = $orderData['reference']
                ?? $orderData['external_reference']
                ?? $orderData['id']
                ?? null;
        }

        $order->price = $this->extractNumeric(
            $orderData['total_price']
            ?? data_get($orderData, 'financials.total_paid')
            ?? data_get($orderData, 'financials.total_amount')
            ?? data_get($orderData, 'order_value.amount')
            ?? data_get($orderData, 'totals.grand_total')
            ?? $order->price
        );

        if (! empty($orderData['tracking_number']) && empty($order->tracking_number)) {
            $order->tracking_number = $orderData['tracking_number'];
        }

        $createdAt = $orderData['created_at']
            ?? $orderData['order_date']
            ?? $orderData['placed_at']
            ?? null;
        if ($createdAt) {
            $order->created_at = Carbon::parse($createdAt)->format('Y-m-d H:i:s');
        }

        $updatedAt = $orderData['updated_at']
            ?? $orderData['modified_at']
            ?? $createdAt;
        if ($updatedAt) {
            $order->updated_at = Carbon::parse($updatedAt)->format('Y-m-d H:i:s');
        }

        $order->save();

        $legacyOrder = $this->buildLegacyBmproOrderObject(
            $orderData,
            $orderItems,
            $orderNumber,
            $currencyCode,
            $country_codes
        );

        $customerModel = new Customer_model();
        $customerId = $customerModel->updateCustomerInDB(
            $legacyOrder,
            false,
            $currency_codes,
            $country_codes,
            $order->id,
            $legacyOrder->customer_email ?? null,
            'BMPRO'
        );

        if ($customerId) {
            $order->customer_id = $customerId;
            $order->save();
        }

        if (! empty($legacyOrder->orderlines)) {
            $orderItemModel = new Order_item_model();
            $orderItemModel->updateOrderItemsInDB($legacyOrder);
        }

        return $order->fresh(['order_items', 'customer']);
    }

    protected function maybeAutoCreateRefurbedLabel(self $order): void
    {
        if (! $this->shouldAutoCreateRefurbedLabel($order)) {
            return;
        }

        if ((int) ($order->status ?? 0) !== 2) {
            return;
        }

        if (! empty($order->label_url) && ! empty($order->tracking_number)) {
            return;
        }

        $order->loadMissing('order_items');

        if ($order->order_items->isEmpty()) {
            return;
        }

        try {
            $refurbedApi = new RefurbedAPIController();
        } catch (\Throwable $e) {
            Log::warning('Refurbed: Unable to initialize API client for auto label', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $service = app(RefurbedShippingService::class);

        $result = $service->createLabel($order, $refurbedApi, [
            'mark_shipped' => false,
            'skip_if_exists' => true,
            'sync_identifiers' => false,
        ]);

        if (is_string($result)) {
            Log::info('Refurbed: auto label not created', [
                'order_id' => $order->id,
                'reason' => $result,
            ]);
        }
    }

    protected function shouldAutoCreateRefurbedLabel(self $order): bool
    {
        if ((int) ($order->marketplace_id ?? 0) !== 4) {
            return false;
        }

        $order->loadMissing('marketplace');
        $marketplace = $order->marketplace;

        if (! $marketplace) {
            return false;
        }

        $autoToggle = data_get($marketplace, 'auto_label_on_accept');

        if ($autoToggle === null) {
            $autoToggle = data_get($marketplace, 'refurbed_auto_label_on_accept');
        }

        return $autoToggle === null ? true : (bool) $autoToggle;
    }

    protected function buildLegacyOrderObject(
        array $orderData,
        ?array $orderItems,
        string $orderNumber,
        string $currencyCode,
        array $country_codes,
        ?string $customerEmail = null
    ): object {
        $orderObj = new \stdClass();
        $orderObj->order_id = $orderNumber;
        $orderObj->currency = $currencyCode;
        $orderObj->price = $this->extractNumeric(
            $orderData['settlement_total_paid']
        ) ?? 0;
        $orderObj->delivery_note = $orderData['delivery_note'] ?? null;
        $orderObj->payment_method = $orderData['payment_method'] ?? null;
        $orderObj->tracking_number = $orderData['tracking_number'] ?? null;
        $orderObj->date_creation = $orderData['created_at'] ?? now()->toDateTimeString();
        $orderObj->date_modification = $orderData['updated_at'] ?? $orderObj->date_creation;
        $orderObj->date_shipping = $orderData['shipped_at'] ?? null;
        $orderObj->customer_email = $customerEmail;

        $billingSource = $this->normalizeRefurbedPayload($orderData['billing_address'] ?? $orderData['customer'] ?? []);
        $shippingSource = $this->normalizeRefurbedPayload($orderData['shipping_address'] ?? $orderData['delivery_address'] ?? $billingSource);

        $orderObj->billing_address = (object) $this->buildLegacyAddressArray($billingSource, $country_codes, $customerEmail);
        $orderObj->shipping_address = (object) $this->buildLegacyAddressArray($shippingSource, $country_codes, $customerEmail);

        $orderObj->orderlines = [];
        $items = $orderItems ?? $orderData['items'] ?? $orderData['order_items'] ?? [];
        foreach ($items as $itemPayload) {
            $line = $this->buildLegacyOrderLine($itemPayload);
            if ($line) {
                $orderObj->orderlines[] = (object) $line;
            }
        }

        return $orderObj;
    }

    protected function buildLegacyAddressArray(array $source, array $country_codes, ?string $customerEmail = null): array
    {
        $defaultCountry = array_key_first($country_codes) ?: 'DE';

        return [
            'company' => $source['company'] ?? 'Refurbed Customer',
            'first_name' => $source['first_name'] ?? $source['firstname'] ?? 'Refurbed',
            'last_name' => $source['last_name'] ?? $source['lastname'] ?? 'Customer',
            'street' => $source['street'] ?? ($source['address_line1'] ?? ''),
            'street2' => $source['street2'] ?? ($source['address_line2'] ?? ''),
            'postal_code' => $source['postal_code'] ?? ($source['zip'] ?? ''),
            'country' => $source['country'] ?? $defaultCountry,
            'city' => $source['city'] ?? '',
            'phone' => $source['phone'] ?? $source['telephone'] ?? '',
            'email' => $source['email'] ?? $customerEmail,
        ];
    }

    protected function buildLegacyOrderLine($itemPayload): ?array
    {
        $item = $this->normalizeRefurbedPayload($itemPayload);

        $sku = $item['sku'] ?? $item['merchant_sku'] ?? null;
        $listingId = $item['listing_id'] ?? null;

        if (! $listingId && $sku) {
            $listingId = Variation_model::where('sku', $sku)->value('reference_id');
        }

        $referenceId = $item['id'] ?? $item['order_item_id'] ?? (string) Str::uuid();

        if (! $listingId && ! $sku) {
            Log::warning('Refurbed order line missing identifiers', ['payload' => $item]);
        }

        return [
            'id' => $referenceId,
            'listing_id' => $listingId,
            'sku' => $sku,
            'quantity' => $item['quantity'] ?? 1,
            'price' => $this->resolveRefurbedItemPrice($item) ?? 0,
            'state' => $this->mapRefurbedOrderItemState($item['state'] ?? 'NEW'),
            'imei' => $item['imei'] ?? null,
            'serial_number' => $item['serial_number'] ?? ($item['serial'] ?? null),
            'title' => $item['title'] ?? null,
        ];

    }

    protected function resolveRefurbedItemPrice(array $item): ?float
    {
        $candidates = [
            'settlement_total_paid' => $item['settlement_total_paid'] ?? null,
            'unit_price' => $item['unit_price'] ?? null,
            'price' => $item['price'] ?? null,
            'settlement_unit_price' => $item['settlement_unit_price'] ?? null,
            'settlement_price' => $item['settlement_price'] ?? null,
            'total_price' => $item['total_price'] ?? null,
            'price_total' => $item['price_total'] ?? null,
            'gross_price' => $item['gross_price'] ?? null,
            'net_price' => $item['net_price'] ?? null,
        ];

        foreach ($candidates as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $numeric = $this->extractNumeric($value);
            if ($numeric === null) {
                continue;
            }

            $isAggregate = in_array($label, ['total_price', 'price_total', 'settlement_total_paid'], true);
            if ($isAggregate && ! empty($item['quantity'])) {
                $quantity = (float) $item['quantity'];
                if ($quantity > 0) {
                    return round($numeric / $quantity, 2);
                }
            }

            return $numeric;
        }

        if (! empty($item['quantity'])) {
            $quantity = (float) $item['quantity'];
            if ($quantity > 0 && isset($item['total_price'])) {
                $total = $this->extractNumeric($item['total_price']);
                if ($total !== null) {
                    return round($total / $quantity, 2);
                }
            }
        }

        return null;
    }

    protected function mapRefurbedOrderState(string $state): int
    {
        return match (strtoupper($state)) {
            'NEW', 'PENDING' => 1,
            'ACCEPTED', 'CONFIRMED' => 2,
            'SHIPPED', 'IN_TRANSIT' => 3,
            'DELIVERED', 'COMPLETED' => 3,
            'CANCELLED' => 4,
            'RETURNED' => 6,
            default => 1,
        };
    }

    protected function mapRefurbedOrderItemState(string $state): int
    {
        return match (strtoupper($state)) {
            'NEW', 'PENDING' => 1,
            'ACCEPTED', 'CONFIRMED' => 2,
            'SHIPPED' => 3,
            'DELIVERED' => 4,
            'CANCELLED' => 5,
            'RETURNED' => 6,
            default => 1,
        };
    }

    protected function normalizeRefurbedPayload($payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_object($payload)) {
            return json_decode(json_encode($payload), true) ?? [];
        }

        return [];
    }

    protected function extractNumeric($value): ?float
    {
        if (is_array($value)) {
            if (isset($value['amount'])) {
                $value = $value['amount'];
            } elseif (isset($value['value'])) {
                $value = $value['value'];
            }
        }

        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    protected function resolveCurrencyIdForOrder(?string $currencyCode, array $currencyCodes, ?int $countryId): ?int
    {
        $currencyCode = $currencyCode ?: 'EUR';

        if (isset($currencyCodes[$currencyCode])) {
            return (int) $currencyCodes[$currencyCode];
        }

        $currencyId = Currency_model::where('code', $currencyCode)->value('id');
        if ($currencyId) {
            return (int) $currencyId;
        }

        $currencyId = $this->createCurrencyIfMissing($currencyCode, $countryId);
        if ($currencyId) {
            return $currencyId;
        }

        return $this->defaultCurrencyId();
    }

    protected function createCurrencyIfMissing(string $currencyCode, ?int $countryId): ?int
    {
        try {
            $currency = Currency_model::firstOrNew(['code' => $currencyCode]);

            if (! $currency->exists) {
                $currency->name = $currencyCode;
                $currency->sign = $currencyCode;
                $currency->country_id = $countryId
                    ?? Country_model::where('code', 'DE')->value('id')
                    ?? Country_model::value('id');
                $currency->save();
            }

            return (int) $currency->id;
        } catch (\Throwable $e) {
            Log::error('Refurbed: failed to create currency record', [
                'currency_code' => $currencyCode,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function defaultCurrencyId(): ?int
    {
        static $defaultId;

        if ($defaultId !== null) {
            return $defaultId;
        }

        $defaultId = Currency_model::where('code', 'EUR')->value('id')
            ?? Currency_model::value('id');

        return $defaultId ? (int) $defaultId : null;
    }

    protected function syncBackMarketStockForRefurbedOrder(self $order, object $legacyOrder): void
    {
        if ($order->marketplace_id !== 4) {
            return;
        }

        if (($legacyOrder->orderlines ?? []) === []) {
            return;
        }

        if ($order->reference === self::REFURBED_STOCK_SYNCED_REFERENCE) {
            return;
        }

        $variationQuantities = [];

        foreach ($legacyOrder->orderlines as $line) {
            $variationId = $this->resolveVariationIdFromOrderLine($line);

            if (! $variationId) {
                continue;
            }

            $quantity = max(1, (int) ($line->quantity ?? 1));

            $variationQuantities[$variationId] = ($variationQuantities[$variationId] ?? 0) + $quantity;
        }

        if (empty($variationQuantities)) {
            return;
        }

        /** @var ListingController $listingController */
        $listingController = app(ListingController::class);
        $hasFailure = false;

        foreach ($variationQuantities as $variationId => $quantity) {
            try {
                $listingController->add_quantity($variationId, -1 * $quantity);
                usleep(500000); // 0.5 second delay to avoid rate limiting
            } catch (\Throwable $e) {
                $hasFailure = true;
                Log::error('Refurbed: failed to adjust BackMarket listing quantity', [
                    'order_reference_id' => $order->reference_id,
                    'variation_id' => $variationId,
                    'quantity' => $quantity,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $hasFailure) {
            $order->reference = self::REFURBED_STOCK_SYNCED_REFERENCE;
            $order->save();
        }
    }

    protected function resolveVariationIdFromOrderLine($orderLine): ?int
    {
        $listingId = $orderLine->listing_id ?? null;

        if ($listingId) {
            $variationId = Variation_model::where('reference_id', $listingId)->value('id');
            if ($variationId) {
                return (int) $variationId;
            }
        }

        $sku = $orderLine->sku ?? null;

        if ($sku) {
            $variationId = Variation_model::where('sku', $sku)->value('id');
            if ($variationId) {
                return (int) $variationId;
            }
        }

        return null;
    }

    private function mapStateToStatus($order) {
        $orderlines = $order->orderlines;
        // echo $order->state." ";

        // if the state of order or is 0 or 1, then the order status is 'Created'
        if ($order->state == null || $order->state == 0 || $order->state == 1) return 1;

        if ($order->state == 3) {
        foreach($orderlines as $key => $value) {
            // in case there are some of the orderlines not being validated, then the status is still 'Created'
            if ($orderlines[$key]->state == 0 || $orderlines[$key]->state == 1) return 1;
            else if ($orderlines[$key]->state == 2) return 2;
            else continue;
        }
        // if all the states of orderlines are 2, the order status should be 'Validated'
        // return 3;
        }

        if ($order->state == 8) return 4;

        if ($order->state == 9) {
        // if any one of the states of orderlines is 6, the order status should be 'Returned'
        foreach($orderlines as $key => $value) {
            if ($orderlines[$key]->state == 6) return 6;
        }

        // if any one of the states of orderlines is 4 or 5
        foreach($orderlines as $key => $value) {
            if ($orderlines[$key]->state == 4 || $orderlines[$key]->state == 5) return 5;
        }

        // if any one of the states of orderlines is 3, the order status should be 'Shipped'
        foreach($orderlines as $key => $value) {
            if ($orderlines[$key]->state == 3) return 3;
        }
        }
    }

}
