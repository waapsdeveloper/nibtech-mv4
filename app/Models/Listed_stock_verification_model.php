<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Listed_stock_verification_model extends Model
{
    use HasFactory;
    // use softDeletes;
    protected $table = 'listed_stock_verification';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'process_id',
        'variation_id',
        'pending_orders',
        'qty_from',
        'qty_change',
        'qty_to',
        'admin_id',
    ];
    public function process()
    {
        return $this->belongsTo(Process_model::class, 'process_id', 'id');
    }
    public function variation()
    {
        return $this->hasOne(Variation_model::class, 'id', 'variation_id');
    }
    public function admin()
    {
        return $this->hasOne(Admin_model::class, 'id', 'admin_id');
    }
}
