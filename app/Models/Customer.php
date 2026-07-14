<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $table = 'customers';
    protected $primaryKey = 'id_customers';

    protected $fillable = [
        'first_name_customers',
        'last_name_customers',
        'phone_customers',
        'mail_customers',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];
}