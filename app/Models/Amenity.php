<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Amenity extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     * (Optional if the table name is 'amenities' by Laravel convention)
     */
    protected $table = 'amenities';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'icon_url',
    ];

    /**
     * The attributes that should be hidden for arrays (optional).
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
