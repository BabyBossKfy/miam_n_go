<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Product;

class Category extends Model
{
    use SoftDeletes;

    protected $table = 'category';
    protected $primaryKey = 'id_category';

    protected $fillable = [
        'label_category',
        'state',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'id_category', 'id_category');
    }
}