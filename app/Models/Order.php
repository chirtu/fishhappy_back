<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @mixin \Eloquent */
class Order extends Model
{
    protected $fillable = ['status', 'customer_id', 'shipping_id', 'confirmed_at', 'delivered_at', 'description'];

    protected $casts = [
        'id' => 'integer',
        'customer_id' => 'integer',
        'shipping_id' => 'integer',
        'confirmed_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'order_items')->withPivot('quantity');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
