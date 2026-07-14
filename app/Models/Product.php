<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Category;
use App\Models\Partner;
use App\Models\OrderDetail;

class Product extends Model
{
    use SoftDeletes;

    protected $table = 'products';

    protected $primaryKey = 'id_products';

    protected $fillable = [
        'label_products',
        'price',
        'state',
        'id_partners',
        'id_category',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'id_category', 'id_category');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'id_partners', 'id_partners');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'id_products', 'id_products');
    }
}