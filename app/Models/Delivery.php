<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delivery extends Model
{
    use SoftDeletes;

    protected $table = 'delivery';
    protected $primaryKey = 'id_delivery';

    protected $fillable = [
        'reference',
        'area_delivery',
        'status',
        'state',
        'id_orders',
        'created_by',
        'updated_by',
        'deleted_by',
    ];
}