<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;


class Customer_model extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'customer';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'company',
        'first_name',
        'is_vendor',
    ];
    public function country_id()
    {
        return $this->hasOne(Country_model::class, 'id', 'country');
    }
    public function orders()
    {
        return $this->hasMany(Order_model::class, 'customer_id', 'id');
    }

    public function updateCustomerInDB($orderObj, $is_vendor = false, $currency_codes, $country_codes)
    {
        // Your implementation here using Eloquent ORM
        // Example:
        // $orderObj = (object) $orderObj[0];
        // print_r($orderObj);
        $customerObj = $orderObj->billing_address;

        if((int) $customerObj->phone > 0){
            $numberWithoutSpaces = str_replace(' ', '', strval($customerObj->phone));
            $phone =  $numberWithoutSpaces;
        }else{
            $numberWithoutSpaces = str_replace(' ', '', strval($orderObj->shipping_address->phone));
            $phone =  $numberWithoutSpaces;
        }

        $customer = Customer_model::firstOrNew(['company' => $customerObj->company,'first_name' => $customerObj->first_name,'last_name' => $customerObj->last_name,'phone' => $phone,]);
        $customer->company = $customerObj->company;
        $customer->first_name = $customerObj->first_name;
        $customer->last_name = $customerObj->last_name;
        $customer->street = $customerObj->street;
        $customer->street2 = $customerObj->street2;
        $customer->postal_code = $customerObj->postal_code;
        // echo $customerObj->country." ";
        // if(Country_model::where('code', $customerObj->country)->first()  == null){
            // dd($country_codes);
        // }
        $customer->country = $country_codes[$customerObj->country];
        $customer->city = $customerObj->city;
        $customer->phone =  $phone;
        $customer->email = $customerObj->email;
        if($is_vendor == true){
            $customer->is_vendor = 1;
        }
        $customer->reference = "BackMarket";
        // ... other fields
        $customer->save();
        // echo "----------------------------------------";
        return $customer->id;
    }
}
