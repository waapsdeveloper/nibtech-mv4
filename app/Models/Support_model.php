<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;


class Support_model extends Model
{
    use HasFactory;

    use SoftDeletes;
    protected $table = 'supports';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'reference_id',
        'order_item_id',
        'initiator',
        'closed_at',
        'last_message_at',
    ];

    public function order_item()
    {
        return $this->hasOne(Order_item_model::class, 'id', 'order_item_id');
    }
    public function support_messages()
    {
        return $this->hasMany(Support_message_model::class, 'support_id', 'id');
    }
    public function initiator_name()
    {
        if($this->initiator == 1){
            return "BackMarket";
        }elseif($this->initiator == 2){
            return "Merchant";
        }elseif($this->initiator == 3){
            return "Customer";
        }
    }

    public function new_support($reference_id, $order_item_id, $initiator, $closed_at = null, $messages = [])
    {
        $find = $this->firstOrNew(['reference_id'=> $reference_id, 'order_item_id' => $order_item_id, 'initiator' => $initiator]);
        $find->closed_at = $closed_at;
        $find->save();


    }
}
