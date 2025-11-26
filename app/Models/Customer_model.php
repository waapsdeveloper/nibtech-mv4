<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
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

    public function addresses(){
        return $this->hasMany(Address_model::class, 'customer_id', 'id');
    }

    public function shipping_address()
    {
        return $this->hasOne(Address_model::class, 'customer_id', 'id')->where('type', 27)->orderByDesc('id');
    }

    public function billing_address()
    {
        return $this->hasOne(Address_model::class, 'customer_id', 'id')->where('type', 28)->orderByDesc('id');
    }

    public function updateCustomerInDB(
        $orderObj,
        $is_vendor = false,
        $currency_codes,
        $country_codes,
        ?int $orderId = null,
        ?string $customerEmail = null,
        string $marketplaceReference = 'BackMarket'
    )
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

        $customerEmail = $customerEmail ?? ($orderObj->customer_email ?? null);

        $reference = $marketplaceReference ?: 'BackMarket';

        $customer = Customer_model::firstOrNew([
            'company' => $customerObj->company,
            'first_name' => $customerObj->first_name,
            'last_name' => $customerObj->last_name,
            'phone' => $phone,
            'reference' => $reference,
        ]);
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
        if(!empty($customerObj->email) && str_contains($customerObj->email, 'testinvoice')){
            $email = str_replace('testinvoice', 'invoice', $customerObj->email);
        }else{
            $email = $customerObj->email ?? $customerEmail;
        }
        $customer->email = $email;
        // $customer->email = $customerObj->email;
        if($is_vendor == true){
            $customer->is_vendor = 1;
            $customer->type = 1;
        }
        $customer->reference = $reference;
        // ... other fields
        $customer->save();

        $this->storeCustomerAddress($customer, $customerObj, 28, $country_codes, $email ?? $customerEmail, $orderId, $customerEmail);

        if (! empty($orderObj->shipping_address)) {
            $shippingAddress = $orderObj->shipping_address;
            $shippingEmail = $shippingAddress->email ?? $email ?? $customerEmail;
            $this->storeCustomerAddress($customer, $shippingAddress, 27, $country_codes, $shippingEmail, $orderId, $customerEmail);
        }
        // echo "----------------------------------------";
        return $customer->id;
    }

    private function storeCustomerAddress(
        Customer_model $customer,
        $addressObj,
        int $type,
        array|Collection $country_codes,
        ?string $fallbackEmail = null,
        ?int $orderId = null,
        ?string $customerEmail = null
    ): void
    {
        if (! $addressObj) {
            return;
        }

        $countryCodes = $country_codes instanceof Collection
            ? $country_codes->toArray()
            : $country_codes;

        $countryCode = $addressObj->country ?? null;
        $lookup = [
            'customer_id' => $customer->id,
            'type' => $type,
        ];

        if ($orderId) {
            $lookup['order_id'] = $orderId;
        }

        $address = Address_model::firstOrNew($lookup);

        $address->street = $addressObj->street ?? '';
        $address->street2 = $addressObj->street2 ?? '';
        $address->postal_code = $addressObj->postal_code ?? '';
        $address->country = $countryCode && isset($countryCodes[$countryCode])
            ? $countryCodes[$countryCode]
            : ($address->country ?? null);
        $address->city = $addressObj->city ?? '';
        $address->phone = $this->normalizePhoneValue($addressObj->phone ?? null);
        $effectiveEmail = $addressObj->email
            ?? $fallbackEmail
            ?? $customerEmail
            ?? $address->email;
        if ($effectiveEmail) {
            $address->email = $effectiveEmail;
        }
        if ($orderId) {
            $address->order_id = $orderId;
        }
        $address->status = $address->status ?? 1;
        $address->type = $type;

        $address->save();
    }

    private function normalizePhoneValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $number = preg_replace('/\s+/', '', (string) $value);

        return $number === '' ? null : substr($number, 0, 30);
    }
}
