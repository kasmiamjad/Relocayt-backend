<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\User;


class Property extends Model
{
    /**
     * Explicitly define the table name since it's not the default plural.
     */
    protected $table = 'property';

    protected $fillable = [
        'master_id', 'host_id', 'title', 'description', 'property_type',
        'room_type', 'accommodates', 'bedrooms', 'beds', 'bathrooms',
        'address_line', 'city', 'state', 'country', 'zipcode',
        'latitude', 'longitude', 'price_per_night', 'currency',
        'min_nights', 'max_nights', 'check_in_time', 'check_out_time',
        'instant_bookable', 'status', 'slug', 'uuid', 'user_id',
        'tax', 'percentage', 'lat_long', 'phone', 'open',
        'visibility', 'background_img', 'logo_img', 'min_amount',
        'status_note', 'delivery_time', 'type', 'verify',
        'r_count', 'r_avg', 'r_sum', 'o_count', 'od_count',
        'delivery_type', 'min_price', 'max_price', 'service_min_price',
        'service_max_price', 'b_count', 'b_sum', 'email_statuses'
    ];

    public function master(): BelongsTo
    {
        return $this->belongsTo(User::class, 'master_id');
    }
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'host_id', 'id');
    }

    public function galleries(): MorphMany
    {
        return $this->morphMany(Gallery::class, 'loadable');
    }



}
