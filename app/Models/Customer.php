<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Order;

class Customer extends Model
{
    use SoftDeletes;

    protected $table = 'customers';

    protected $primaryKey = 'id_customers';

    protected $fillable = [
        'id_users',
        'first_name_customers',
        'last_name_customers',
        'phone_customers',
        'mail_customers',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_users', 'id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'id_customers', 'id_customers');
    }
}