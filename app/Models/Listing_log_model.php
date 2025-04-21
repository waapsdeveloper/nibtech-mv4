<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Listing_log_model extends Model
{
    use HasFactory;
    // use softDeletes;
    protected $table = 'listing_logs';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'listing_id',
        'type_id',
        'price_from',
        'price_to',
        'pending_orders',
        'admin_id',
    ];
    public function listing()
    {
        return $this->belongsTo(Listing_model::class, 'listing_id', 'id');
    }
    public function type()
    {
        return $this->hasOne(Multi_type_model::class, 'id', 'type_id');
    }
    public function admin()
    {
        return $this->hasOne(Admin_model::class, 'id', 'admin_id');
    }
}
