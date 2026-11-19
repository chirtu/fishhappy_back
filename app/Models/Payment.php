<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @mixin \Eloquent */
class Payment extends Model
{
    protected $fillable = ['customer_id', 'order_id', 'transaction_id', 'tracking_id', 'status', 'payment_method', 'amount', 'redirect_url'];

    protected $casts = [
        'id' => 'integer',
        'customer_id' => 'integer',
        'order_id' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
