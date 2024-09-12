<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;


class Order_item_model extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'order_items';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'reference_id',
        'order_id',
        'variation_id',
        'stock_id',
        'care_id',
        'currency',
        'price',
        'quantity',
        'status',
        'linked_id',
        'admin_id'
    ];
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->attributes['admin_id'] = session('user_id');
    }
    public function variation()
    {
        return $this->hasOne(Variation_model::class, 'id', 'variation_id');
    }
    public function currency_id()
    {
        return $this->hasOne(Currency_model::class, 'id', 'currency');
    }

    public function admin()
    {
        return $this->hasOne(Admin_model::class, 'id', 'admin_id');
    }

    public function sale_order()
    {
        return $this->hasOne(Order_model::class, 'id', 'order_id')->where('order_type_id',3);
    }

    public function order()
    {
        return $this->belongsTo(Order_model::class, 'order_id', 'id');
    }
    public function stock()
    {
        return $this->belongsTo(Stock_model::class, 'stock_id', 'id');
    }

    public function linked()
    {
        return $this->belongsTo(Order_item_model::class, 'linked_id');
    }
    public function childs()
    {
        return $this->hasMany(Order_item_model::class, 'linked_id');
    }
    public function purchase_grade()
    {
        return $this->hasOne(Purchase_grade_model::class, 'order_item_id', 'id');
    }
    public function check_return()
    {
        return $this->HasOne(Order_item_model::class, 'linked_id','id')->where('reference_id',$this->order->reference_id);
    }
    public function refund_order()
    {
        return $this->HasOne(Order_model::class, 'reference_id', 'reference_id')->where('processed_at','>=',Carbon::parse($this->created_at)->subDays(30));
    }

    public function replacement()
    {
        return $this->hasOne(Order_item_model::class, 'care_id', 'id');
    }
    public function check_replacement()
    {
        $item = $this;
        while($item->replacement != null){
            $item = $item->replacement;
        }
        return $item;
    }

    public function get_latest_care($bm)
    {

        $last_id = Order_item_model::where('care_id','!=',null)->where('created_at','>=',Carbon::now()->subDays(4))->whereHas('sale_order')->orderBy('reference_id','asc')->first()->care_id;
        echo $last_id;
        $care = $bm->getAllCare(false, ['last_id'=>$last_id,'page-size'=>50]);
        // $care = $bm->getAllCare(false, ['page-size'=>50]);
        // print_r($care);
        // die;
        $care_line = collect($care)->pluck('id','orderline')->toArray();
        $care_keys = array_keys($care_line);


        // Assuming $care_line is already defined from the previous code
        $careLineKeys = array_keys($care_line);

        // Construct the raw SQL expression for the CASE statement
        // $caseExpression = "CASE ";
        foreach ($care_line as $id => $care) {
            // $caseExpression .= "WHEN reference_id = $id THEN $care ";
            $order = Order_item_model::where('reference_id',$id)->update(['care_id' => $care]);
            if($order != 0){
                print_r($order);
            }

        }
    }
    public function get_all_cares($bm)
    {

        $care = $bm->getAllCare(false, ['page-size'=>50]);
        // $care = $bm->getAllCare(false, ['page-size'=>50]);
        // print_r($care);
        $care_line = collect($care)->pluck('id','orderline')->toArray();
        $care_keys = array_keys($care_line);


        // Assuming $care_line is already defined from the previous code
        $careLineKeys = array_keys($care_line);

        // Construct the raw SQL expression for the CASE statement
        // $caseExpression = "CASE ";
        foreach ($care_line as $id => $care) {
            // $caseExpression .= "WHEN reference_id = $id THEN $care ";
            $order = Order_item_model::where('reference_id',$id)->update(['care_id' => $care]);
            if($order != 0){
                print_r($order);
            }

        }
    }

    public function updateOrderItemsInDB($orderObj, $tester = null, $bm)
    {
        // Your implementation here

        foreach ($orderObj->orderlines as $itemObj) {
            // $care = $bm->getAllCare(false, ['orderline'=>$itemObj->id]);
            // // $care = $bm->getAllCare(false, ['page-size'=>50]);
            // // print_r($care);
            // $care_line = collect($care)->pluck('id','orderline')->toArray();

            // // Construct the raw SQL expression for the CASE statement
            // // $caseExpression = "CASE ";
            // foreach ($care_line as $id => $care) {
            //     // $caseExpression .= "WHEN reference_id = $id THEN $care ";
            //     $order = Order_item_model::where('reference_id',$id)->update(['care_id' => $care]);

            // }
            // Your implementation here using Eloquent ORM
            // Example:
            // print_r($orderObj);
            // echo "<br>";
            $orderItem = Order_item_model::firstOrNew(['reference_id' => $itemObj->id]);
            $variation = Variation_model::where(['reference_id' => $itemObj->listing_id])->first();
            if($variation == null){
                // $this->updateBMOrdersAll();
                $list = $bm->getOneListing($itemObj->listing_id);
                $variation = Variation_model::firstOrNew(['reference_id' => $list->listing_id]);
                $variation->name = $list->title;
                $variation->sku = $list->sku;
                $variation->grade = $list->state+1;
                $variation->status = 1;
                $variation->state = $list->publication_state;
                $variation->listed_stock = $list->quantity;
                // ... other fields
                // dd($orderObj);
            }elseif($orderItem->id == null){
                $variation->listed_stock -= $itemObj->quantity;
            }
                $variation->save();

            if($orderItem->stock_id == null && $orderItem->stock_id != 0){
                if($itemObj->imei != null || $itemObj->serial_number != null){
                    if($itemObj->imei != null){
                        $stock = Stock_model::withTrashed()->firstOrNew(['imei' => $itemObj->imei]);
                        $stock->imei = $itemObj->imei;
                        if($stock->id != null){
                            $stock->status = 2;
                            $last_item = $stock->last_item();
                            if($last_item != null){
                                $orderItem->linked_id = $last_item->id;
                            }
                        }
                    }
                    if($itemObj->serial_number != null){
                        $stock = Stock_model::withTrashed()->firstOrNew(['serial_number' => $itemObj->serial_number,]);
                        if(strlen($itemObj->serial_number) > 20){
                            continue;
                        }
                        $stock->serial_number = $itemObj->serial_number;
                        if($stock->id != null){
                            $stock->status = 2;
                            $last_item = $stock->last_item();
                            $orderItem->linked_id = $last_item->id;
                        }
                    }

                    $stock->variation_id = $variation->id;
                    $stock->save();
                    $orderItem->stock_id = $stock->id;

                }
            }
            $orderItem->order_id = Order_model::where(['reference_id' => $orderObj->order_id])->first()->id;
            $orderItem->variation_id = $variation->id;
            $orderItem->reference_id = $itemObj->id;
            if($orderItem->price == null){
                $orderItem->price = $itemObj->price;
            }

            $orderItem->quantity = $itemObj->quantity;
            switch ($itemObj->state){
                case 0: $state = 0; break;
                case 8: $state = 0; break;
                case 1: $state = 1; break;
                case 2: $state = 2; break;
                case 3: $state = 3; break;
                case 4: $state = 4; break;
                case 5: $state = 5; break;
                case 6: $state = 6; break;
                default: $state = 0; break;
            }
            $orderItem->status = $state;
            // ... other fields
            $orderItem->save();
            // echo "----------------------------------------";
        }


    }
}
