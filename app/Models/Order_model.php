<?php

namespace App\Models;

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
            $order = Order_model::firstOrNew(['reference_id' => $orderObj->order_id]);
            if($order->customer_id == null){
                $order->customer_id = $customer_model->updateCustomerInDB($orderObj, false, $currency_codes, $country_codes);
            }
            $order->status = $this->mapStateToStatus($orderObj);
            if($order->status == null){
                Log::info("Order status is null", $orderObj);
            }
            $order->currency = $currency_codes[$orderObj->currency];
            $order->order_type_id = 3;
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

        $currencyCode = $orderData['currency'] ?? 'EUR';
        $currencyId = $currency_codes[$currencyCode] ?? Currency_model::where('code', $currencyCode)->value('id');

        if (! $currencyId) {
            Log::warning('Refurbed order currency not found', ['currency' => $currencyCode, 'order' => $orderNumber]);
        }

        $countryCode = $orderData['country']
            ?? ($orderData['shipping_address']['country'] ?? null)
            ?? null;
        $countryId = $countryCode
            ? ($country_codes[$countryCode] ?? Country_model::where('code', $countryCode)->value('id'))
            : null;

        $order = Order_model::firstOrNew(['reference_id' => $orderNumber]);
        $order->marketplace_id = 4;
        $order->reference = $orderData['id'] ?? $order->reference;
        $order->status = $this->mapRefurbedOrderState($orderData['state'] ?? 'NEW');
        $order->currency = $currencyId ?? $order->currency;

        if ($countryId) {
            if (Schema::hasColumn($order->getTable(), 'country_id')) {
                $order->country_id = $countryId;
            } elseif (Schema::hasColumn($order->getTable(), 'country')) {
                $order->country = $countryCode;
            }
        } elseif ($countryCode && Schema::hasColumn($order->getTable(), 'country')) {
            $order->country = $countryCode;
        }

        $order->price = $this->extractNumeric($orderData['total_amount'] ?? $orderData['price'] ?? $order->price);
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
            $country_codes
        );

        $customerModel = new Customer_model();
        $customerId = $customerModel->updateCustomerInDB($legacyOrder, false, $currency_codes, $country_codes);
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

        return $order->fresh(['order_items', 'customer']);
    }

    protected function buildLegacyOrderObject(
        array $orderData,
        ?array $orderItems,
        string $orderNumber,
        string $currencyCode,
        array $country_codes
    ): object {
        $orderObj = new \stdClass();
        $orderObj->order_id = $orderNumber;
        $orderObj->currency = $currencyCode;
        $orderObj->price = $this->extractNumeric($orderData['total_amount'] ?? $orderData['price'] ?? null) ?? 0;
        $orderObj->delivery_note = $orderData['delivery_note'] ?? null;
        $orderObj->payment_method = $orderData['payment_method'] ?? null;
        $orderObj->tracking_number = $orderData['tracking_number'] ?? null;
        $orderObj->date_creation = $orderData['created_at'] ?? now()->toDateTimeString();
        $orderObj->date_modification = $orderData['updated_at'] ?? $orderObj->date_creation;
        $orderObj->date_shipping = $orderData['shipped_at'] ?? null;

        $billingSource = $this->normalizeRefurbedPayload($orderData['billing_address'] ?? $orderData['customer'] ?? []);
        $shippingSource = $this->normalizeRefurbedPayload($orderData['shipping_address'] ?? $orderData['delivery_address'] ?? $billingSource);

        $orderObj->billing_address = (object) $this->buildLegacyAddressArray($billingSource, $country_codes);
        $orderObj->shipping_address = (object) $this->buildLegacyAddressArray($shippingSource, $country_codes);

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

    protected function buildLegacyAddressArray(array $source, array $country_codes): array
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
            'email' => $source['email'] ?? 'refurbed-customer@example.com',
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
            'price' => $this->extractNumeric($item['price'] ?? $item['unit_price'] ?? null) ?? 0,
            'state' => $this->mapRefurbedOrderItemState($item['state'] ?? 'NEW'),
            'imei' => $item['imei'] ?? null,
            'serial_number' => $item['serial_number'] ?? ($item['serial'] ?? null),
            'title' => $item['title'] ?? null,
        ];

    }

    protected function mapRefurbedOrderState(string $state): int
    {
        return match (strtoupper($state)) {
            'NEW', 'PENDING' => 1,
            'ACCEPTED', 'CONFIRMED' => 2,
            'SHIPPED', 'IN_TRANSIT' => 3,
            // 'DELIVERED', 'COMPLETED' => 4,
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
