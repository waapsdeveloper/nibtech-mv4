<?php

namespace App\Models;

use App\Http\Livewire\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Category_model extends Model
{
    use HasFactory;
    protected $table = 'category';
    protected $primaryKey = 'id';
    public $timestamps = FALSE;
    protected $fillable = [
        // other fields...
        // 'reference_id',
    ];
    public function products()
    {
        return $this->hasMany(Products_model::class, 'category', 'id');
    }

    public function salesData($start_date, $end_date)
    {
        return $this->products()
                    ->with(['variations.order_items' => function ($query) use ($start_date, $end_date) {
                        $query->whereHas('order', function ($query) use ($start_date, $end_date) {
                            $query->whereBetween('processed_at', [$start_date, $end_date])
                                  ->where('order_type_id', 3);
                        });
                    }])
                    ->withCount([
                        'variations.order_items as orders_qty' => function ($query) use ($start_date, $end_date) {
                            $query->whereHas('order', function ($query) use ($start_date, $end_date) {
                                $query->whereBetween('processed_at', [$start_date, $end_date])
                                      ->where('order_type_id', 3);
                            });
                        },
                        'variations.order_items as approved_orders_qty' => function ($query) use ($start_date, $end_date) {
                            $query->whereHas('order', function ($query) use ($start_date, $end_date) {
                                $query->whereBetween('processed_at', [$start_date, $end_date])
                                      ->where('order_type_id', 3)
                                      ->where('status', 3);
                            });
                        }
                    ])
                    ->withSum([
                        'variations.order_items as eur_items_sum' => function ($query) use ($start_date, $end_date) {
                            $query->whereHas('order', function ($query) use ($start_date, $end_date) {
                                $query->whereBetween('processed_at', [$start_date, $end_date])
                                      ->where('order_type_id', 3)
                                      ->where('currency', 4);
                            });
                        },
                        'variations.order_items as eur_approved_items_sum' => function ($query) use ($start_date, $end_date) {
                            $query->whereHas('order', function ($query) use ($start_date, $end_date) {
                                $query->whereBetween('processed_at', [$start_date, $end_date])
                                      ->where('order_type_id', 3)
                                      ->where('status', 3)
                                      ->where('currency', 4);
                            });
                        },
                        'variations.order_items as gbp_items_sum' => function ($query) use ($start_date, $end_date) {
                            $query->whereHas('order', function ($query) use ($start_date, $end_date) {
                                $query->whereBetween('processed_at', [$start_date, $end_date])
                                      ->where('order_type_id', 3)
                                      ->where('currency', 5);
                            });
                        },
                        'variations.order_items as gbp_approved_items_sum' => function ($query) use ($start_date, $end_date) {
                            $query->whereHas('order', function ($query) use ($start_date, $end_date) {
                                $query->whereBetween('processed_at', [$start_date, $end_date])
                                      ->where('order_type_id', 3)
                                      ->where('status', 3)
                                      ->where('currency', 5);
                            });
                        }
                    ], 'price')
                    ->get();
    }

}
