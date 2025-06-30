<?php
declare(strict_types=1);

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * App\Models\Room
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string|null $img
 * @property bool|null $active
 * @property int|null $status
 * @property bool|null $cleaner_id
 * @property int|null $type
 * @property int|null $group
 * @property int|null $floor
 * @property int|null $bed_option
 * @property int|null $max_adult_child
 * @property int|null $max_adult
 * @property int|null $min_adult
 * @property int|null $max_child
 * @property int|null $min_child
 * @property bool|null $private_bathroom
 * @property bool|null $safe
 * @property bool|null $wifi
 * @property bool|null $balcony
 * @property bool|null $hair_dryer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder|self filter(array $filter)
 * @method static Builder|self newModelQuery()
 * @method static Builder|self newQuery()
 * @method static Builder|self query()
 * @method static Builder|self whereId($value)
 * @mixin Eloquent
 */
class Room extends Model
{
    public $guarded     = ['id'];
    public $timestamps  = false;
    public $casts = [
        'parent_id'        => 'int',
        'img'              => 'string',
        'active'           => 'bool',
        'status'           => 'int',
        'cleaner_id'       => 'bool',
        'type'             => 'int',
        'group'            => 'int',
        'floor'            => 'int',
        'bed_option'       => 'int',
        'max_adult_child'  => 'int',
        'max_adult'        => 'int',
        'min_adult'        => 'int',
        'max_child'        => 'int',
        'min_child'        => 'int',
        'private_bathroom' => 'bool',
        'safe'             => 'bool',
        'wifi'             => 'bool',
        'balcony'          => 'bool',
        'hair_dryer'       => 'bool',
    ];

    const STATUS_NEW           = 'new';
    const STATUS_READY         = 'ready';
    const STATUS_CLEANING      = 'in_cleaning';
    const STATUS_NOT_AVAILABLE = 'not_available';

    public const STATUSES = [
        self::STATUS_NEW           => self::STATUS_NEW,
        self::STATUS_READY         => self::STATUS_READY,
        self::STATUS_CLEANING      => self::STATUS_CLEANING,
        self::STATUS_NOT_AVAILABLE => self::STATUS_NOT_AVAILABLE,
    ];

    public function price(): HasOne
    {
        return $this->hasOne(RoomPrice::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(RoomPrice::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(RoomTranslation::class);
    }

    public function translation(): HasOne
    {
        return $this->hasOne(RoomTranslation::class);
    }

    public function scopeFilter($query, array $filter): void
    {
        $query
            ->when(request()->is('api/v1/rest/*') && request('lang'), function ($q) {
                $q->whereHas('translation', fn($query) => $query->where(function ($q) {

                    

                    $q->where('locale', request('lang'));

                }));
            })
            ->when(isset($filter['active']),           fn($q) => $q->where('active',           $filter['active']))
            ->when(isset($filter['parent_id']),        fn($q) => $q->where('parent_id',        $filter['parent_id']))
            ->when(isset($filter['status']),           fn($q) => $q->where('status',           $filter['status']))
            ->when(isset($filter['cleaner_id']),       fn($q) => $q->where('cleaner_id',       $filter['cleaner_id']))
            ->when(isset($filter['type']),             fn($q) => $q->where('type',             $filter['type']))
            ->when(isset($filter['group']),            fn($q) => $q->where('group',            $filter['group']))
            ->when(isset($filter['floor']),            fn($q) => $q->where('floor',            $filter['floor']))
            ->when(isset($filter['bed_option']),       fn($q) => $q->where('bed_option',       $filter['bed_option']))
            ->when(isset($filter['max_adult_child']),  fn($q) => $q->where('max_adult_child',  $filter['max_adult_child']))
            ->when(isset($filter['max_adult']),        fn($q) => $q->where('max_adult',        $filter['max_adult']))
            ->when(isset($filter['min_adult']),        fn($q) => $q->where('min_adult',        $filter['min_adult']))
            ->when(isset($filter['max_child']),        fn($q) => $q->where('max_child',        $filter['max_child']))
            ->when(isset($filter['min_child']),        fn($q) => $q->where('min_child',        $filter['min_child']))
            ->when(isset($filter['private_bathroom']), fn($q) => $q->where('private_bathroom', $filter['private_bathroom']))
            ->when(isset($filter['safe']),             fn($q) => $q->where('safe',             $filter['safe']))
            ->when(isset($filter['wifi']),             fn($q) => $q->where('wifi',             $filter['wifi']))
            ->when(isset($filter['balcony']),          fn($q) => $q->where('balcony',          $filter['balcony']))
            ->when(isset($filter['hair_dryer']),       fn($q) => $q->where('hair_dryer',       $filter['hair_dryer']));
    }
}
