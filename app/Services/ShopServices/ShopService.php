<?php
declare(strict_types=1);

namespace App\Services\ShopServices;

use App\Helpers\ResponseError;
use App\Models\Gallery;
use App\Models\Invitation;
use App\Models\Language;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceMaster;
use App\Models\Shop;
use App\Models\User;
use App\Services\CoreService;
use App\Traits\SetTranslations;
use DB;
use Exception;
use Throwable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Property;
use Illuminate\Support\Str;


class ShopService extends CoreService
{
    use SetTranslations;

    protected function getModelClass(): string
    {
        return Shop::class;
    }

    /**
     * Create a new Shop model.
     * @param array $data
     * @return array
     */

    protected function generateUniqueEmail($baseEmail): string
    {
        [$name, $domain] = explode('@', $baseEmail);
        return $name . '+master@' . $domain; // e.g., john+master@example.com
    }

    public function create(array $data): array
    {
        
        try {
            if (!isset($data['user_id'])) {
                throw new Exception(__('errors.' . ResponseError::ERROR_404, locale: $this->language));
            }
            
            $seller = User::with(['roles'])->find($data['user_id']);

            /** @var User $seller */
            if ($seller?->hasRole('admin')) {
                throw new Exception(__('errors.' . ResponseError::ERROR_207, locale: $this->language));
            }

            $shop = Shop::where('user_id', $data['user_id'])->first();

            if (!empty($shop)) {
                throw new Exception(__('errors.' . ResponseError::ERROR_206, locale: $this->language));
            }

            $authUser = auth()->user();
            /** @var Shop $shop */
            $shop = DB::transaction(function () use($data, $authUser) {

                /** @var Shop $shop */
                $shop = $this->model()->create($this->setShopParams($data));

                $this->setTranslations($shop, $data);

                if (data_get($data, 'images.0')) {
                    $shop->update([
                        'logo_img'       => data_get($data, 'images.0'),
                        'background_img' => data_get($data, 'images.0'),
                    ]);
                    $shop->uploads(data_get($data, 'images'));
                }

                if (data_get($data, 'documents.0')) {
                    $shop->uploads(data_get($data, 'documents'), Gallery::SHOP_DOCUMENTS);
                }

                if (data_get($data, 'tags.0')) {
                    $shop->tags()->sync(data_get($data, 'tags', []));
                }

                return $shop;
            });
   
            $response = app(\App\Services\UserServices\UserService::class)->create([
                'firstname' => data_get($data, 'title.en', 'Master'),
                'lastname'  => $seller->lastname,
                'email'     => $this->generateUniqueEmail($seller->email),
                'phone'     => NULL,
                'password'  => 'secure-default-password',
                'password_confirmation' => 'secure-default-password',
                'birthday'  => $seller->birthday?->format('Y-m-d'),
                'gender'    => $seller->gender,
                'role'      => 'master',
                'images'    => [data_get($data, 'images.1')], 
                'shop_id'   => [$shop->id],
            ]);

            // Debug: Log the raw response structure
           // Log::debug('Raw API response', ['response' => $response]);

            // Convert response to array if it isn't already
            $responseArray = is_array($response) ? $response : json_decode(json_encode($response), true);

            // Debug: Log the converted array structure
            //Log::debug('Converted response array', ['response_array' => $responseArray]);

            // SAFEST WAY TO GET USER ID - handles all possible response formats
            $userId = null;
            
            // Check all possible paths where the ID might exist
            $possiblePaths = [
                'data.App\\Models\\User.id',  // Primary expected path
                'data.id',                   // Alternative path
                'id',                        // Fallback
                'user_id',                   // Another possible variation
                'data.user.id'               // Another possible structure
            ];

            foreach ($possiblePaths as $path) {
                if ($foundId = data_get($responseArray, $path)) {
                    $userId = $foundId;
                    break;
                }
            }

            if (empty($userId)) {
                // Detailed error logging
                Log::error('User ID extraction failed', [
                    'error' => 'User ID not found in any expected paths',
                    'available_paths' => array_keys($responseArray), // Show top-level keys
                    'full_response' => $responseArray,
                    'possible_paths_tried' => $possiblePaths
                ]);
                throw new \RuntimeException('User creation failed - could not extract user ID');
            }
            //Log::info('Successfully created master user', ['user_id' => $userId]);
            try {
                $property = Property::create([
                    'master_id'        => $userId,
                    'host_id'          => $shop->id,
                    'title'            => data_get($data, 'title.en'), // or another source
                    'description'      => data_get($data, 'description.en'),
                    'property_type'    => data_get($data, 'property_type'),
                    'room_type'        => data_get($data, 'room_type'),
                    'accommodates'     => data_get($data, 'accommodates'),
                    'bedrooms'         => data_get($data, 'bedrooms'),
                    'beds'             => data_get($data, 'beds'),
                    'bathrooms'        => data_get($data, 'bathrooms'),
                    'address_line'     => data_get($data, 'address.en', 'NA'),
                    'city'             => data_get($data, 'city'),
                    'state'            => data_get($data, 'state'),
                    'country'          => data_get($data, 'country'),
                    'flat_details'     => data_get($data, 'flat'), // NEW
                    'street'           => data_get($data, 'street'), // OPTIONAL if not already in address_line
                    'landmark'         => data_get($data, 'landmark'), // NEW
                    'district'         => data_get($data, 'district'), // NEW
                    'zipcode'          => data_get($data, 'zipcode'),
                    'latitude'         => data_get($data, 'location.lat', 1),
                    'longitude'        => data_get($data, 'location.lng', 1),
                    'price_per_night'  => data_get($data, 'price_per_night'),
                    'currency'         => data_get($data, 'currency'),
                    'min_nights'       => data_get($data, 'min_nights'),
                    'max_nights'       => data_get($data, 'max_nights'),
                    'check_in_time'    => data_get($data, 'check_in_time'),
                    'check_out_time'   => data_get($data, 'check_out_time'),
                    'instant_bookable' => data_get($data, 'instant_bookable'),
                    'logo_img'         => data_get($data, 'images.0'),
                    'background_img'   => data_get($data, 'images.0'),
                    'status'           => 'inactive', // or 'active' as needed
                    'uuid'             => Str::uuid(),
                    'slug'             => Str::slug(data_get($data, 'title.en')) . '-' . rand(100,999),
                    'user_id'          => $seller->id,
                    'phone'            => data_get($data, 'phone'),
                ]); 
                
                if (!empty($data['galleryImages']) && is_array($data['galleryImages'])) {
                    $galleryRecords = array_map(function ($url) {
                        return [
                            'path'       => $url,
                            'type'       => Gallery::PROPERTY_GALLERIES,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }, $data['galleryImages']);

                    Log::info('Inserting gallery records', [
                        'property_id' => $property->id,
                        'records'     => $galleryRecords,
                    ]);

                    $property->galleries()->createMany($galleryRecords);
                }

            } catch (\Throwable $e) {
                // 🧹 Rollback: Delete the master user and shop
                Log::error('Property creation failed, rolling back master and shop.', [
                    'error' => $e->getMessage(),
                ]);

                // Delete master user and related wallet/invitations if needed
                User::where('id', $masterId)->delete();

                // Delete the shop
                $shop->delete();

                // Rethrow to be caught by outer catch block
                throw $e;
            }

            /// Log::info('Property id ', ['property id is ' => $property]);

            foreach ($data['amenities'] ?? [] as $amenity) {
                DB::table('amenity_property')->insert([
                    'property_id' => $property->id,
                    'amenity_id'  => $amenity['amenity_id'],
                    'type'        =>  "dedicated", //$amenity['type'],
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
            try {
                $service = \App\Models\Service::create([
                    'category_id'    => 133,
                    'commission_fee' => 1,
                    'gender'         => 1,
                    'interval'       => 30,
                    'pause'          => 12,
                    'price'          => data_get($data, 'price_per_night'),
                    'shop_id'        => $shop->id,
                    'status'         => data_get($data, 'status', 'new'),
                    'type'           => data_get($data, 'type', 'offline_in'),
                ]);

                $service->translations()->create([
                    'locale' => 'en', // or whatever lang you’re using
                    'title' => data_get($data, 'title.en'),
                    'description' => data_get($data, 'description.en'),
                ]);

                // Optionally set translations for title/description if your app supports it
                if (method_exists($service, 'setTranslations')) {
                    $service->setTranslations('title', data_get($data, 'title'));
                    $service->setTranslations('description', data_get($data, 'description'));
                }

            } catch (\Throwable $e) {
                Log::error('Service creation failed, rolling back.', [
                    'error' => $e->getMessage(),
                ]);

                // Cleanup all previously created entities
                User::where('id', $userId)->delete();
                $shop->delete();
                $property->delete();

                throw $e;
            }

             // Log::debug('Raw API response', ['Service' => $service]);
             $serviceArray = is_array($service) ? $service : json_decode(json_encode($service), true);
             // SAFEST WAY TO GET USER ID - handles all possible response formats
            $serviceId = null;
            
            // Check all possible paths where the ID might exist
            $possibleservicePaths = [
                'Service.App\\Models\\Service.id',  // Primary expected path
                'Service.id',                   // Alternative path
                'id',                        // Fallback
                'user_id',                   // Another possible variation
                'Service.user.id'               // Another possible structure
            ];

            foreach ($possibleservicePaths as $path) {
                if ($foundId = data_get($serviceArray, $path)) {
                    $serviceId = $foundId;
                    break;
                }
            }

             // Log::debug('Service ID', ['Service' => $serviceId]);
            try {
                $serviceMaster = app(\App\Services\ServiceMasterService\ServiceMasterService::class)->create([
                    'service_id'      => $serviceId,
                    'master_id'       => $userId,
                    'shop_id'         => $shop->id,
                    'price'           => data_get($data, 'price_per_night', 100),
                    'interval'        => data_get($data, 'interval', 10),
                    'pause'           => data_get($data, 'pause', 20),
                    'type'            => data_get($data, 'type', 'offline_in'),
                    'commission_fee'  => data_get($data, 'commission_fee', 1),
                    'gender'          => data_get($data, 'gender', 1),
                    'active'          => 1,
                ]);

            } catch (\Throwable $e) {
                Log::error('Failed to assign master to service.', [
                    'error' => $e->getMessage(),
                ]);

                // Optional rollback logic here if you want to clean up service/shop/master/etc
                throw $e;
            }

            return [
                'status' => true,
                'code'   => ResponseError::NO_ERROR,
                'data'   => $shop->load([
                    'translation' => fn($query) => $query->where(function ($q) {
                        $q->where('locale', $this->language);
                    }),
                    'subscription',
                    'seller.roles',
                    'tags.translation' => fn($query) => $query->where(function ($q) {
                        $q->where('locale', $this->language);
                    }),
                    'seller' => fn($q) => $q->select('id', 'firstname', 'lastname', 'uuid'),
                ])
            ];
        } catch (Throwable $e) {
            $this->error($e);
            return [
                'status'    => false,
                'code'      => ResponseError::ERROR_501,
                'message'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Update specified Shop model.
     * @param string $uuid
     * @param array $data
     * @return array
     */
    
    public function update(string $uuid, array $data): array
    {
        try {
            $shop = $this->model()
                ->with(['invitations'])
                ->when(data_get($data, 'user_id') && !request()->is('api/v1/dashboard/admin/*'), fn($q, $userId) => $q->where('user_id', $data['user_id']))
                ->where('uuid', $uuid)
                ->first();

            if (empty($shop)) {
                return ['status' => false, 'code' => ResponseError::ERROR_404];
            }

            /** @var Shop $parent */
            /** @var Shop $shop */
            $shop->update($this->setShopParams($data, $shop));

            if ($shop->delivery_type === Shop::DELIVERY_TYPE_IN_HOUSE) {
                Invitation::whereHas('user.roles', fn($q) => $q->where('name', 'deliveryman'))
                    ->where([
                        'shop_id' => $shop->id
                    ])
                    ->delete();
            }

            $this->setTranslations($shop, $data);

            if (data_get($data, 'images.0')) {
                $shop->galleries()->delete();
                $shop->update([
                    'logo_img'       => data_get($data, 'images.0'),
                    'background_img' => data_get($data, 'images.1'),
                ]);
                $shop->uploads(data_get($data, 'images'));
            }

            if (data_get($data, 'documents.0')) {
                $shop->uploads(data_get($data, 'documents'));
            }

            if (data_get($data, 'tags.0')) {
                $shop->tags()->sync(data_get($data, 'tags', []));
            }

            

            return [
                'status' => true,
                'code' => ResponseError::NO_ERROR,
                'data' => Shop::with([
                    'translation' => fn($query) => $query->where(function ($q) {
                        $q->where('locale', $this->language);
                    }),
                    'subscription',
                    'seller.roles',
                    'tags.translation' => fn($query) => $query->where(function ($q) {
                        $q->where('locale', $this->language);
                    }),
                    'seller' => fn($q) => $q->select('id', 'firstname', 'lastname', 'uuid'),
                    'workingDays',
                    'closedDates',
                ])->find($shop->id)
            ];
        } catch (Exception $e) {
            return [
                'status'  => false,
                'code'    => $e->getCode() ? 'ERROR_' . $e->getCode() : ResponseError::ERROR_400,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete Shop model.
     * @param array|null $ids
     * @return array
     */
    public function delete(?array $ids = []): array
    {

        foreach (Shop::with(['orders.pointHistories'])->whereIn('id', (array)$ids)->get() as $shop) {

            /** @var Shop $shop */

            $shop->galleries()->where('type', '!=', Gallery::SHOP_GALLERIES)->delete();

            if (!$shop->seller?->hasRole('admin')) {
                $shop->seller?->syncRoles('user');
            }

            foreach ($shop->orders as $order) {
                /** @var Order $order */
                $order->pointHistories()->delete();
            }

            $shop->delete();

        }

        return ['status' => true, 'code' => ResponseError::NO_ERROR];
    }

    /**
     * Set params for Shop to update or create model.
     * @param array $data
     * @param Shop|null $shop
     * @return array
     */
    private function setShopParams(array $data, ?Shop $shop = null): array
    {
        $deliveryTime = $shop?->delivery_time ?? [];

        if (isset($data['delivery_time_from'])) {
            $deliveryTime['from'] = $data['delivery_time_from'];
        }

        if (isset($data['delivery_time_to'])) {
            $deliveryTime['to'] = $data['delivery_time_to'];
        }

        if (isset($data['delivery_time_type'])) {
            $deliveryTime['type'] = $data['delivery_time_type'];
        }

        if (isset($data['lat_long'])) {
            $data['latitude']  = @$data['lat_long']['latitude'];
            $data['longitude'] = @$data['lat_long']['longitude'];
            unset($data['lat_long']);
        }

        $data['delivery_time'] = $deliveryTime;
        $data['type']          = 1;

        return $data;
    }

    /**
     * @param string $uuid
     * @param array $data
     * @return array
     */
    public function imageDelete(string $uuid, array $data): array
    {
        $shop = Shop::firstWhere('uuid', $uuid);

        if (empty($shop)) {
            return [
                'status' => false,
                'code'   => ResponseError::ERROR_404,
                'data'   => $shop->refresh(),
            ];
        }

        $tag = data_get($data, 'tag');

        $shop->galleries()
            ->where('path', $tag === 'background' ? $shop->background_img : $shop->logo_img)
            ->delete();

        $shop->update([data_get($data, 'tag') . '_img' => null]);

        return [
            'status' => true,
            'code'   => ResponseError::NO_ERROR,
            'data'   => $shop->refresh(),
        ];
    }

    public function updateShopPrices(ServiceMaster|Service $model, float $newMinPrice = 0, float $newMaxPrice = 0): void
    {
        $shop = Shop::find($model->shop_id);

        if (empty($shop)) {
            return;
        }

        if ($newMinPrice <= 0) {
            $newMinPrice = $model->price;
        }

        if ($newMaxPrice <= 0) {
            $newMaxPrice = $model->price;
        }

        $minPrice = $shop->service_min_price;

        if ($minPrice > $newMinPrice) {
            $minPrice = $newMinPrice;
        }

        $maxPrice = $shop->service_max_price;

        if ($maxPrice < $newMaxPrice) {
            $maxPrice = $newMaxPrice;
        }

        if ($minPrice !== $shop->service_min_price || $maxPrice !== $shop->service_max_price) {
            $shop->update([
                'service_min_price' => $minPrice ?? 1,
                'service_max_price' => $maxPrice ?? 1,
            ]);
        }

    }

    /**
     * @param int|string $uuid
     * @return array
     */
    public function updateVerify(int|string $uuid): array
    {
        $shop = Shop::where('uuid', $uuid)->first();

        if (empty($shop) || $shop->uuid !== $uuid) {
            $shop = Shop::where('id', (int)$uuid)->first();
        }

        if (empty($shop)) {
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_404,
                'message' => __('errors.' . ResponseError::ERROR_404, locale: $this->language)
            ];
        }

        $shop->update(['verify' => !$shop->verify]);

        return [
            'status' => true,
            'data'   => $shop,
        ];
    }
}
