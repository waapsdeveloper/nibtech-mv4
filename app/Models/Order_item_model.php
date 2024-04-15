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
        'care_id',
    ];
    public function variation()
    {
        return $this->hasOne(Variation_model::class, 'id', 'variation_id');
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
        return $this->belongsTo(Process_batch_model::class, 'linked_id');
    }
    public function childs()
    {
        return $this->hasMany(Process_batch_model::class, 'linked_id');
    }

    public function updateOrderItemsInDB($orderObj, $tester = null, $bm)
    {
        // Your implementation here

        foreach ($orderObj->orderlines as $itemObj) {
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
                // ... other fields
                $variation->save();
                // dd($orderObj);
            }
            if($orderItem->stock_id == null){
                if($itemObj->imei != null || $itemObj->serial_number != null){
                    if($itemObj->imei != null){
                        $stock = Stock_model::firstOrNew(['imei' => $itemObj->imei]);
                        $stock->imei = $itemObj->imei;
                        if($stock->id != null){
                            $stock->status = 2;
                            foreach($stock->order_item as $item){
                                if($item->order_id == $stock->order_id){
                                    $orderItem->linked_id = $item->id;
                                    break;
                                }
                            }
                        }
                    }
                    if($itemObj->serial_number != null){
                        $stock = Stock_model::firstOrNew(['serial_number' => $itemObj->serial_number,]);
                        if(strlen($itemObj->serial_number) > 20){
                            continue;
                        }
                        $stock->serial_number = $itemObj->serial_number;
                        if($stock->id != null){
                            $stock->status = 2;
                            foreach($stock->order_item as $item){
                                if($item->order_id == $stock->order_id){
                                    $orderItem->linked_id = $item->id;
                                    break;
                                }
                            }
                        }
                    }

                    $stock->variation_id = $variation->id;
                    $stock->created_at = Carbon::parse($itemObj->date_creation)->format('Y-m-d H:i:s');
                    $stock->save();
                    $orderItem->stock_id = $stock->id;

                }
            }
            $orderItem->order_id = Order_model::where(['reference_id' => $orderObj->order_id])->first()->id;
            $orderItem->created_at = Carbon::parse($itemObj->date_creation)->format('Y-m-d H:i:s');
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
                case 7: $state = 0; break;
            }
            $orderItem->status = $state;
            // ... other fields
            $orderItem->save();
            // echo "----------------------------------------";
        }


    }
}
