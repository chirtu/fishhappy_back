<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @mixin \Eloquent */
class ProductCategory extends Model
{
    protected $fillable = ['name', 'description'];

    protected $casts = [
        'id' => 'integer',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
