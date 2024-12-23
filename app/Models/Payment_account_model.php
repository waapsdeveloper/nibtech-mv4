<?php

namespace App\Models;

use App\Http\Livewire\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment_account_model extends Model
{
    use HasFactory;
    protected $table = 'payment_accounts';
    protected $primaryKey = 'id';
    protected $fillable = [
        // other fields...
        'bank',
        'account_title',
        'account_number',
        'sort_code',
        'iban',
        'swift_code',
        'note',
        'currency_id',
    ];
    public function payments()
    {
        return $this->hasMany(Payment_model::class, 'payment_account_id', 'id');
    }
    public function currency()
    {
        return $this->hasOne(Currency_model::class, 'id', 'currency_id');
    }
    public function admin()
    {
        return $this->hasOne(Admin_model::class, 'id', 'admin_id');
    }
}
