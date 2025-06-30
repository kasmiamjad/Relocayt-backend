<?php
declare(strict_types=1);

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\RoomTranslation
 *
 * @property int $id
 * @property int $room_id
 * @property string $locale
 * @property string $title
 * @property string $location
 * @property string $view
 * @property string $description
 * @property-read Room|null $room
 * @method static Builder|self newModelQuery()
 * @method static Builder|self newQuery()
 * @method static Builder|self query()
 * @method static Builder|self whereId($value)
 * @method static Builder|self whereRoomId($value)
 * @method static Builder|self whereTitle($value)
 * @mixin Eloquent
 */
class RoomTranslation extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
