<?php
declare(strict_types=1);

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\RoomPrice
 *
 * @property int $id
 * @property int $room_id
 * @property string $price
 * @property string $discount
 * @property string $day
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder|self newModelQuery()
 * @method static Builder|self newQuery()
 * @method static Builder|self query()
 * @method static Builder|self whereId($value)
 * @method static Builder|self whereRoomId($value)
 * @method static Builder|self whereTitle($value)
 * @mixin Eloquent
 */
class RoomPrice extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
