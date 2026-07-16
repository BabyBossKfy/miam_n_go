<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryDetail extends Model
{
    use SoftDeletes;

    protected $table = 'delivery_details';

    protected $primaryKey = 'id_delivery_details';

    protected $fillable = [
        'product',
        'status',
        'state',
        'id_delivery',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function delivery()
    {
        return $this->belongsTo(Delivery::class, 'id_delivery', 'id_delivery');
    }
}