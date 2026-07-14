<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $table = 'payment';
    protected $primaryKey = 'id_payment';

    protected $fillable = [
        'reference',
        'transaction',
        'type',
        'token',
        'response',
        'status_transaction',
        'status',
        'state',
        'id_orders',
        'created_by',
        'updated_by',
        'deleted_by',
    ];
}