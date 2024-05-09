<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Process_stock_model extends Model
{
    use HasFactory;
    use softDeletes;
    protected $table = 'process_stock';
    protected $primaryKey = 'id';
    // public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        'stock_id',
        'process_batch_id',
    ];
    public function stock()
    {
        return $this->hasOne(Stock_model::class, 'id', 'stock_id');
    }
    public function process()
    {
        return $this->belongsTo(Process_model::class, 'process_batch_id', 'id');
    }
    public function admin()
    {
        return $this->hasOne(Admin_model::class, 'id', 'admin_id');
    }
    public function status_id()
    {
        return $this->hasOne(Multi_status_model::class, 'id', 'status');
    }
}
