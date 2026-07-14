<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderDetail extends Model
{
    use SoftDeletes;

    protected $table = 'order_details';

    protected $primaryKey = 'id_order_details';

    protected $fillable = [
        'id_orders',
        'id_products',
        'quantity',
        'unit_price',
        'total_price',
        'status_order',
        'status_delivery',
        'status_payment',
        'state',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'id_orders', 'id_orders');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_products', 'id_products');
    }
}