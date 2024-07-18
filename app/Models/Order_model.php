<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;


class Order_model extends Model
{
    use HasFactory;

    use SoftDeletes;
    protected $table = 'orders';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'reference_id',
        'status',
        'currency',
        'processed_by',
        'order_type_id',
    ];

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
            $order->currency = $currency_codes[$orderObj->currency];
            $order->order_type_id = 3;
            $order->price = $orderObj->price;
            $order->delivery_note_url = $orderObj->delivery_note;
            if($order->label_url == null && $bm->getOrderLabel($orderObj->order_id) != null){
                if($bm->getOrderLabel($orderObj->order_id)->results != null){
                    $order->label_url = $bm->getOrderLabel($orderObj->order_id)->results[0]->labelUrl;
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
            $order->tracking_number = $orderObj->tracking_number;
            $order->created_at = Carbon::parse($orderObj->date_creation)->format('Y-m-d H:i:s');
            $order->updated_at = Carbon::parse($orderObj->date_modification)->format('Y-m-d H:i:s');
            // echo Carbon::parse($orderObj->date_creation)->format('Y-m-d H:i:s'). "       ";
            // ... other fields
            $order->save();

        }
        // print_r(Order_model::find($order->id));
        // echo "----------------------------------------";
    }

    private function mapStateToStatus($order) {
        $orderlines = $order->orderlines;
        // echo $order->state." ";

        // if the state of order or is 0 or 1, then the order status is 'Created'
        if ($order->state == 0 || $order->state == 1) return 1;

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
