<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Product;
use App\Models\User;

class Partner extends Model
{
    use SoftDeletes;

    protected $table = 'partners';

    protected $primaryKey = 'id_partners';

    protected $fillable = [
        'label_partners',
        'state',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'partner_users',
            'id_partners',
            'id_users'
        );
    }    
    
    public function products()
    {
        return $this->hasMany(Product::class, 'id_partners', 'id_partners');
    }

}