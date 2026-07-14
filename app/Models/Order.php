<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $table = 'orders';

    protected $primaryKey = 'id_orders';

    protected $fillable = [
        'reference',
        'price',
        'status_order',
        'status_delivery',
        'status_payment',
        'state',
        'id_customers',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'id_customers', 'id_customers');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'id_orders', 'id_orders');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'id_orders', 'id_orders');
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'id_orders', 'id_orders');
    }
}