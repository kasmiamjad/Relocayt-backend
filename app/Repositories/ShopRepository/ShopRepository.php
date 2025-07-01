<?php
declare(strict_types=1);

namespace App\Repositories\ShopRepository;

use App\Helpers\Utility;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopTag;
use App\Models\Stock;
use App\Repositories\CoreRepository;
use App\Traits\ByLocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class  ShopRepository extends CoreRepository
{
    use ByLocation;

    protected function getModelClass(): string
    {
        return Shop::class;
    }

    private function with(): array
    {
        

        $with = [
            'translation' => fn($query) => $query
                ->where('locale', $this->language),
            'services' => fn($q) => $q, // ->take(3)
            'services.translation' => fn($query) => $query
                ->where('locale', $this->language),
            'services.serviceExtras.translation' => fn($query) => $query
                ->where('locale', $this->language)
                ->select('id', 'service_extra_id', 'title', 'locale'),
            'workingDays',
            'closedDates',
            'bonus' => fn($q) => $q->where('expired_at', '>=', now())
                ->select([
                    'stock_id',
                    'bonus_quantity',
                    'bonus_stock_id',
                    'expired_at',
                    'value',
                    'type',
                ]),
            'bonus.stock.product' => fn($q) => $q->select('id', 'uuid'),
            'bonus.stock.product.translation' => fn($q) => $q
                ->where('locale', $this->language)
                ->select('id', 'locale', 'title', 'product_id'),
            'discounts' => fn($q) => $q->where('end', '>=', now())->where('active', 1)
                ->select('id', 'shop_id', 'end', 'active'),
            'shopPayments:id,payment_id,shop_id,status,client_id,secret_id',
            'shopPayments.payment:id,tag,input,sandbox,active',
            'socials',
            'memberShips',
            'locations' => fn($q) => $q->with($this->getWith())
        ];

        if (!request()->is('api/v1/rest/*')) {
            $with[] = 'documents';
        }

        return $with;
    }
    /**
     * Get one Shop by UUID
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function shopsPaginate(array $filter): LengthAwarePaginator
    {
        DB::listen(function ($query) {
            Log::info('📥 Executed SQL:', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time_ms' => $query->time,
            ]);
        });
        
        Log::info('🧾 shopsPaginate filter:', $filter);
        /** @var Shop $shop */
        $shop      = $this->model();
        $latitude  = data_get($filter, 'address.latitude');
        $longitude = data_get($filter, 'address.longitude');

        return $shop
        ->with([
            'translation' => fn($q) => $q->where('locale', $this->language),
            'services' => fn($q) => $q->select('*')->from('services')->whereRaw('services.shop_id = id')->take(3),
            'services.translation' => fn($q) => $q->where('locale', $this->language),
            'services.serviceExtras.translation' => fn($q) => $q->where('locale', $this->language)->select('id', 'service_extra_id', 'title', 'locale'),
            'closedDates',
            'workingDays',
        ])
        ->select([
            'id',
            'uuid',
            'slug',
            'logo_img',
            'background_img',
            'status',
            'type',
            'delivery_time',
            'delivery_type',
            'open',
            'visibility',
            'verify',
            'r_count',
            'r_avg',
            'min_price',
            'max_price',
            'service_min_price',
            'service_max_price',
            'latitude',
            'longitude',
        ])
        ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * Get one Shop by UUID
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function selectPaginate(array $filter): LengthAwarePaginator
    {
        /** @var Shop $shop */
        $shop = $this->model();
        
        $latitude   = data_get($filter, 'address.latitude');
        $longitude  = data_get($filter, 'address.longitude');

        return $shop
            ->filter($filter)
            ->with([
                'translation' => fn($q) => $q
                    ->where('locale', $this->language)
                    ->select('id', 'locale', 'title', 'shop_id'),
            ])
            ->whereHas(
                'translation',
                fn($query) => $query->where('locale', $this->language)
            )
            ->select([
                'id',
                'uuid',
                'slug',
                'logo_img'
            ])
            ->when(!empty($latitude) && !empty($longitude), function (Builder $query) use ($latitude, $longitude, $filter) {
                $query
                    ->select('*')
//                    ->where('latitude', '>', 0)
//                    ->where('longitude', '>', 0)
                    ->addSelect([
                        'latitude',
                        'longitude',
                        DB::raw("round(ST_Distance_Sphere(point(`longitude`, `latitude`), point($longitude, $latitude)) / 1000, 1) AS distance"),
                        //(6371 * acos(cos(radians($latitude)) * cos(radians(latitude)) * cos(radians(longitude) - radians($longitude)) + sin(radians($latitude)) * sin(radians(latitude)))) AS distance
                    ])
                    ->when(data_get($filter, 'column') === 'distance', function ($q) use ($filter) {
                        $q->orderBy('distance', $filter['sort'] ?? 'desc');
                    });
            })
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param string $uuid
     * @param array|null $filter
     * @return Model|Builder|null
     */
    public function shopDetails(string $uuid, ?array $filter = []): Model|Builder|null
    {
        $latitude       = data_get($filter, 'address.latitude');
        $longitude      = data_get($filter, 'address.longitude');
        $locationExists = !empty($latitude) && !empty($longitude);

        $shop = Shop::where('uuid', $uuid)
            ->select('shops.*')
            ->when($locationExists, function (Builder $query) use ($latitude, $longitude) {
                $query->addSelect([
                    DB::raw("round(ST_Distance_Sphere(point(`shops`.`longitude`, `shops`.`latitude`), point($longitude, $latitude)) / 1000, 1) AS distance"),
                ]);
            })
            ->first();

        if (empty($shop) || $shop->uuid !== $uuid) {
            $shop = Shop::where('id', (int)$uuid)
                ->select('shops.*')
                ->when($locationExists, function (Builder $query) use ($latitude, $longitude) {
                    $query->addSelect([
                        DB::raw("round(ST_Distance_Sphere(point(`shops`.`longitude`, `shops`.`latitude`), point($longitude, $latitude)) / 1000, 1) AS distance"),
                    ]);
                })
                ->first();
        }

        if (!$shop) {
            return null;
        }

        // 👇 Fetch related property data manually
        $property = DB::table('property')
            ->where('host_id', $shop->id)
            ->select([
                'id', 'title', 'property_type', 'room_type', 'accommodates',
                'bedrooms', 'beds', 'bathrooms', 'price_per_night', 'currency',
                'check_in_time', 'check_out_time', 'instant_bookable',
                'latitude', 'longitude','description','logo_img','background_img',
            ])
            ->first();

        if ($property) {
            $shop->property = $property;
        }

        $distance = $shop->distance ?? 1;

        // Log the shop retrieved

        return $shop->setAttribute('distance', $distance);
    }

    public function shopDetails_old(string $uuid, ?array $filter = []): Model|Builder|null
    {
        $latitude       = data_get($filter, 'address.latitude');
        $longitude      = data_get($filter, 'address.longitude');
        $locationExists = !empty($latitude) && !empty($longitude);

        $shop = Shop::where('uuid', $uuid)
            ->select('*')
            ->when($locationExists, function (Builder $query) use ($latitude, $longitude) {
                $query
                    ->addSelect([
                        DB::raw("round(ST_Distance_Sphere(point(`longitude`, `latitude`), point($longitude, $latitude)) / 1000, 1) AS distance"),
                    ]);
            })
            ->first();

        if (empty($shop) || $shop->uuid !== $uuid) {
            $shop = Shop::where('id', (int)$uuid)
                ->when($locationExists, function (Builder $query) use ($latitude, $longitude) {
                    $query
                        ->select('*')
                        ->addSelect([
                            DB::raw("round(ST_Distance_Sphere(point(`longitude`, `latitude`), point($longitude, $latitude)) / 1000, 1) AS distance"),
                        ]);
                })
                ->first();
        }

        $distance = $shop?->distance ?? 1;

        return $shop->fresh($this->with())->setAttribute('distance', $distance);
    }

    /**
     * @param string $slug
     * @param array|null $filter
     * @return Model|Builder|null
     */
    public function shopDetailsBySlug(string $slug, ?array $filter = []): Model|Builder|null
    {
        /** @var Shop $shop */
        $shop = $this->model();

        $latitude       = data_get($filter, 'address.latitude');
        $longitude      = data_get($filter, 'address.longitude');
        $locationExists = !empty($latitude) && !empty($longitude);

        $shop = Shop::query()
        ->with($this->with())
        ->where('slug', $slug)
        ->when($locationExists, function (Builder $query) use ($latitude, $longitude) {
            $query->addSelect([
                DB::raw("ROUND(ST_Distance_Sphere(POINT(`longitude`, `latitude`), POINT($longitude, $latitude)) / 1000, 1) AS distance"),
            ]);
        })
        ->first();

    if (!$shop) {
        Log::info('SHOP NOT FOUND for slug', ['slug' => $slug]);
        return null; // ✅ early return prevents "none returned" error
    }

    $shop->setAttribute('distance', $shop->distance ?? 1);

    // Attach property + amenities
    $shopId = $shop->id;

    $property = DB::table('property')
        ->where('host_id', $shopId)
        ->select([
            'id', 'title', 'property_type', 'room_type', 'accommodates',
            'bedrooms', 'beds', 'bathrooms', 'price_per_night', 'currency',
            'check_in_time', 'check_out_time', 'instant_bookable',
            'latitude', 'longitude', 'description', 'logo_img', 'background_img',
        ])
        ->first();

    if ($property) {
        $property->amenities = DB::table('amenities')
            ->join('amenity_property', 'amenities.id', '=', 'amenity_property.amenity_id')
            ->where('amenity_property.property_id', $property->id)
            ->select('amenities.id', 'amenities.name')
            ->get();
        
        $shop->property = $property;
    }

    Log::info('SHOP DETAILS new', ['shop' => $shop]);
    // Log::info('PROPERTY DETAILS', ['property' => $property]);

    return $shop;


        // return $shop->with($this->with())
        //     ->where(fn($q) => $q->where('slug', $slug))
        //     ->when($locationExists, function (Builder $query) use ($latitude, $longitude) {
        //         $query
        //             ->addSelect([
        //                 DB::raw("round(ST_Distance_Sphere(point(`longitude`, `latitude`), point($longitude, $latitude)) / 1000, 1) AS distance"),
        //             ]);
        //     })
        //     ->first();
    }

    /**
     * @return Collection|array
     */
    public function takes(): Collection|array
    {
        

        return ShopTag::with([
                'translation' => fn($query) => $query
                    ->where('locale', $this->language),
            ])
            ->get();
    }

    /**
     * @return float[]|int[]
     */
    public function productsAvgPrices(): array
    {
        $min = Stock::where('price', '>=', 0)
            ->where('quantity', '>', 0)
            ->whereHas('product', fn($q) => $q->actual($this->language))
            ->min('price');

        $max = Stock::where('price', '>=', 0)
            ->where('quantity', '>', 0)
            ->whereHas('product', fn($q) => $q->actual($this->language))
            ->max('price');

        return [
            'min' => $min * $this->currency(),
            'max' => ($min === $max ? $max + 1 : $max) * $this->currency(),
        ];
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function shopsSearch(array $filter): LengthAwarePaginator
    {
        /** @var Shop $shop */
        $shop      = $this->model();
        $latitude  = data_get($filter, 'address.latitude');
        $longitude = data_get($filter, 'address.longitude');

        return $shop
            ->filter($filter)
            ->with([
                'translation' => fn($query) => $query
                    ->where('locale', $this->language),
                'discounts' => fn($q) => $q->where('end', '>=', now())->where('active', 1)
                    ->select('id', 'shop_id', 'end', 'active'),
            ])
            ->whereHas('translation', fn($query) => $query->where('locale', $this->language))
            ->latest()
            ->select([
                'id',
                'slug',
                'logo_img',
                'status',
            ])
            ->when(!empty($latitude) && !empty($longitude), function (Builder $query) use ($latitude, $longitude, $filter) {
                $query
//                    ->where('latitude', '>', 0)
//                    ->where('longitude', '>', 0)
                    ->addSelect([
                        'latitude',
                        'longitude',
                        DB::raw("round(ST_Distance_Sphere(point(`longitude`, `latitude`), point($longitude, $latitude)) / 1000, 1) AS distance"),
                    ])
                    ->when(data_get($filter, 'column') === 'distance', function ($q) use ($filter) {
                        $q->orderBy('distance', $filter['sort'] ?? 'desc');
                    });
            })
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param array $filter
     * @return mixed
     */
    public function shopsByIDs(array $filter): mixed
    {
        /** @var Shop $shop */
        $shop   = $this->model();
        

        return $shop->with([
            'translation' => fn($query) => $query->where(
                fn($q) => $q->where('locale', $this->language)
            ),
            'discounts' => fn($q) => $q
                ->where('end', '>=', now())
                ->where('active', 1)
                ->select('id', 'shop_id', 'end', 'active'),
            'tags:id,img',
            'tags.translation' => fn($query) => $query->where(
                fn($q) => $q->where('locale', $this->language)
            ),
        ])
            ->when(data_get($filter, 'status'), fn($q, $status) => $q->where('status', $status))
            ->find(data_get($filter, 'shops', []));
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function categories(array $filter): LengthAwarePaginator
    {
        $shopId = data_get($filter, 'shop_id');
        

        return Category::where([
            ['type', Category::MAIN],
            ['active', true],
        ])
            ->with([
                'translation' => fn($q) => $q
                    ->where('locale', $this->language)
                    ->select('id', 'locale', 'title', 'category_id'),
            ])
            ->whereHas(
                'translation',
                fn($query) => $query->where('locale', $this->language),
            )
            ->whereHas('products', fn($q) => $q
                ->where('active', true)
                ->where('status', Product::PUBLISHED)
                ->where('shop_id', $shopId)
            )
            ->select([
                'id',
                'uuid',
                'keywords',
                'type',
                'active',
                'img',
            ])
            ->orderBy('id')
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param int $id
     * @return array
     */
    public function reviewsGroupByRating(int $id): array
    {
        return Utility::reviewsGroupRating([
            'reviewable_type' => Shop::class,
            'assignable_type' => Shop::class,
            'assignable_id'   => $id,
        ]);
    }
}
