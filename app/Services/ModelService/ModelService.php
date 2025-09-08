<?php
declare(strict_types=1);

namespace App\Services\ModelService;

use App\Helpers\ResponseError;
use App\Models\Service;
use App\Models\ServiceExtra;
use App\Models\ServiceFaq;
use App\Services\CoreService;
use App\Services\ShopServices\ShopService;
use App\Traits\SetTranslations;
use DB;
use Exception;
use Throwable;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class ModelService extends CoreService
{
    use SetTranslations;

    protected function getModelClass(): string
    {
        return Service::class;
    }

    public function create(array $data): array
    {
        try {
            $model = DB::transaction(function () use ($data) {
                \Log::info('🧪 Service creation payload:', $data);

                // Coordinates
                $latitude = data_get($data, 'lat_long.latitude');
                $longitude = data_get($data, 'lat_long.longitude');
                // --- Normalize radius (store in kilometers) ---
                $radiusKm = data_get($data, 'radius_km');
                if (is_null($radiusKm)) {
                    // FE might send `radius` already in km
                    $radiusKm = data_get($data, 'radius_m');
                }
                if (is_null($radiusKm)) {
                    // Or meters as a fallback
                    $meters = data_get($data, 'radius_m');
                    if (!is_null($meters)) {
                        $radiusKm = round(((float)$meters) / 1000, 3);
                    }
                }

                // Main fields to insert
                $createData = collect($data)->only([
                    'category_id',
                    'shop_id',
                    'status',
                    'commission_fee',
                    'interval',
                    'pause',
                    'type',
                    'service_type',
                    'gender',
                    'price',
                    'master_id',
                    'radius_m',
                    'radius_km',
                ])->merge([
                    'address'   => data_get($data, 'address.en'),
                    'description' => data_get($data, 'description.en'),
                    'title'     => data_get($data, 'title.en'),
                    'street'    => data_get($data, 'street'),
                    'city'      => data_get($data, 'city'),
                    'state'     => data_get($data, 'state'),
                    'zipcode'   => data_get($data, 'zipcode'),
                    'country'   => data_get($data, 'country'),
                    'latitude'  => $latitude,
                    'longitude' => $longitude,
                    'radius_km'   => $radiusKm,
                ])->toArray();

                /** @var Service $model */
                $model = $this->model()->create($createData);

                // Update shop price fields if needed
                (new ShopService)->updateShopPrices($model);

                // Translations
                $this->setTranslations($model, $data);

                // Cover image
                if (data_get($data, 'previews.0') || data_get($data, 'images.0')) {
                    $model->update([
                        'img' => data_get($data, 'previews.0') ?? data_get($data, 'images.0'),
                    ]);
                }

                if (data_get($data, 'galleryImages')) {
                    $model->gallery = data_get($data, 'galleryImages');
                }

                if (data_get($data, 'documents')) {
                    $model->documents = data_get($data, 'documents');
                }


                 $model->save(); // save once

                // Optional: handle uploads if needed
                if (data_get($data, 'images')) {
                    $model->uploads($data['images']);
                }
                \Log::info("📧 Loaded shop user", [
                    'user' => $model->shop?->user,
                ]);

                \Log::info("📧 Trying to send service created email", [
                    'shop_id' => $model,
                    'user_id' => $model->shop?->user_id,
                ]);


                try {
                    $seller = User::find($model->shop?->user_id);
                    if ($seller && $seller->email) {
                        app(\App\Services\EmailSettingService\EmailSendService::class)->sendServiceCreated($seller, $model);
                    }

                } catch (\Throwable $e) {
                    \Log::error("Failed to send service created email: ".$e->getMessage());
                }
                return $model;
            });

            return ['status' => true, 'code' => ResponseError::NO_ERROR, 'data' => $model];
        } catch (Throwable $e) {
            \Log::error("Service Creation Failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ['status' => false, 'code' => ResponseError::ERROR_400, 'message' => $e->getMessage()];
        }
    }


    /**
     * @param int $id
     * @param array $data
     * @return array
     */
    public function extrasUpdate(int $id, array $data): array
    {
        try {
            $model = DB::transaction(function () use ($id, $data) {

                $model = Service::find($id);

                if (empty($model)) {
                    throw new Exception(__('errors.' . ResponseError::ERROR_404, locale: $this->language));
                }

                $model->serviceExtras()->delete();

                foreach ($data['extras'] as $extra) {

                    /** @var ServiceExtra $serviceExtra */
                    $extra['shop_id'] = $model->shop_id;
                    $serviceExtra = $model->serviceExtras()->create($extra);
                    $this->setTranslations($serviceExtra, $extra);

                    if (isset($data['img'])) {
                        $serviceExtra->uploads([$data['img']]);
                    }

                }

                return $model;
            });

            return ['status' => true, 'code' => ResponseError::NO_ERROR, 'data' => $model->fresh(['serviceExtras'])];
        } catch (Throwable $e) {
            return ['status' => false, 'code' => ResponseError::ERROR_400, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param int $id
     * @param array $data
     * @return array
     */
    public function faqsUpdate(int $id, array $data): array
    {
        try {
            $model = DB::transaction(function () use ($id, $data) {

                $model = Service::find($id);

                if (empty($model)) {
                    throw new Exception(__('errors.' . ResponseError::ERROR_404, locale: $this->language));
                }

                $model->serviceFaqs()->delete();

                foreach ($data['faqs'] as $faq) {

                    $serviceFaq = $model->serviceFaqs()->create($faq);

                    /** @var ServiceFaq $serviceFaq */
                    if (count($faq['question'] ?? []) === 0) {
                        continue;
                    }

                    $serviceFaq->translations()->delete();

                    foreach ($faq['question'] as $index => $value) {

                        $serviceFaq->translations()->create([
                            'locale'   => $index,
                            'question' => $value,
                            'answer'   => $faq['answer'][$index] ?? '',
                        ]);

                    }

                }

                return $model;
            });

            return ['status' => true, 'code' => ResponseError::NO_ERROR, 'data' => $model->fresh(['serviceFaqs'])];
        } catch (Throwable $e) {
            return ['status' => false, 'code' => ResponseError::ERROR_400, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param Service $service
     * @param array $data
     * @return array
     */
    public function update(Service $service, array $data): array
    {
        try {
            // ✅ Direct SQL-level update
            $updated = DB::table('services')
                ->where('id', $service->id)
                ->update([
                    'status'     => data_get($data, 'status'),
                    'updated_at' => now()
                ]);

            

            // ✅ Reload with shop & user chain
            $service = Service::with('shop.user')->find($service->id);
            \Log::info('Raw update result:', ['updated' => $service]);

            if ($service && $service->shop && $service->shop->user) {
                $user = $service->shop->user;

                try {
                    $mailer = app(\App\Services\EmailSettingService\EmailSendService::class);

                    $resp = $mailer->sendServiceStatusUpdated($user, $service, data_get($data, 'status'));

                    \Log::info("📧 Service update email response: ", $resp);
                } catch (\Exception $e) {
                    \Log::error("❌ Failed to send service update email", [
                        'error' => $e->getMessage(),
                        'service_id' => $service->id,
                    ]);
                }
            }

            return [
                'status' => true,
                'data'   => $service,
            ];

        } catch (Throwable $e) {
            return [
                'status'  => false,
                'message' => $e->getMessage(),
            ];
        }
    }



    public function update_old(Service $service, array $data): array
    {
        try {
            $service = DB::transaction(function () use ($service, $data) {

                $service->update($data);

                (new ShopService)->updateShopPrices($service);

                $this->setTranslations($service, $data);

                if (data_get($data, 'images.0')) {
                    $service->update(['img' => data_get($data, 'previews.0') ?? data_get($data, 'images.0')]);
                    $service->uploads(data_get($data, 'images'));
                }

                return $service;
            });

            return ['status' => true, 'code' => ResponseError::NO_ERROR, 'data' => $service];
        }
        catch (Throwable $e) {
            return ['status' => false, 'code' => ResponseError::ERROR_400, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param array $ids
     * @return array
     */
    public function delete(array $ids = []): array
    {
        try {
            $services = Service::whereIn('id', data_get($ids, 'ids', []))
                ->when(data_get($ids, 'shop_id'),   fn($q, $shopId) => $q->where('shop_id', $shopId))
                ->get();

            foreach ($services as $service) {
                /** @var Service $service */
                $service->galleries()->delete();
                $service->delete();
            }

            return ['status' => true, 'code' => ResponseError::NO_ERROR];
        } catch (Exception $e) {
            return ['status' => false, 'code' => ResponseError::ERROR_400, 'message' => $e->getMessage()];
        }
    }

}
