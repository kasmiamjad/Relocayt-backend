<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\v1\Dashboard\Seller;

use App\Helpers\ResponseError;
use App\Http\Requests\Service\ExtraUpdateRequest;
use App\Http\Requests\Service\FaqsUpdateRequest;
use App\Http\Requests\Service\StoreRequest;
use App\Http\Requests\Service\UpdateRequest;
use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Repositories\ServiceRepository\ServiceRepository;
use App\Services\ModelService\ModelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


class ServiceController extends SellerBaseController
{
    public function __construct(private ServiceRepository $repository, private ModelService $service)
    {
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     *
     * @param FilterParamsRequest $request
     * @return AnonymousResourceCollection
     */
    public function index(FilterParamsRequest $request): AnonymousResourceCollection
    {
        Log::info('Reached adin seller ServiceController@index');
        $filters = $request->merge([
            'shop_id' => $this->shop->id,
            'type'    => 'online',
        ])->all();

        $models = $this->repository->paginate($filters);

        return ServiceResource::collection($models);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param StoreRequest $request
     * @return JsonResponse
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // capture commission_fee for ServiceMaster (not part of Service payload)
        $commissionFee = $validated['commission_fee'] ?? $request->input('commission_fee', 1);
        unset($validated['commission_fee']);

        // ensure service is tied to current shop
        $validated['shop_id'] = $this->shop->id;

        try {
            $result = DB::transaction(function () use ($validated, $commissionFee) {

                /** 1) CREATE MASTER FIRST */
                $seller = $this->shop->seller; // owner user of the shop
                $baseEmail = $seller?->email ?? ('user'.time().'@relocayt.com');
                $masterEmail = $this->generateUniqueEmail($baseEmail);

                $userServiceResponse = app(\App\Services\UserServices\UserService::class)->create([
                    'firstname' => data_get($validated, 'title.en', 'Master'),
                    'lastname'  => $seller->lastname ?? null,
                    'email'     => $masterEmail,
                    'phone'     => null,
                    'password'  => 'secure-default-password',
                    'password_confirmation' => 'secure-default-password',
                    'birthday'  => $seller->birthday?->format('Y-m-d'),
                    'gender'    => $seller->gender ?? 1,
                    'role'      => 'master',
                    'images'    => [],
                    'shop_id'   => [$this->shop->id],
                ]);

                // robustly extract master user id
                $resp = is_array($userServiceResponse)
                    ? $userServiceResponse
                    : json_decode(json_encode($userServiceResponse), true);

                $masterId = data_get($resp, 'data.App\\Models\\User.id')
                    ?? data_get($resp, 'data.id')
                    ?? data_get($resp, 'id')
                    ?? data_get($resp, 'user_id')
                    ?? data_get($resp, 'data.user.id');

                if (empty($masterId)) {
                    Log::error('Failed to extract master user id from UserService response', ['response' => $resp]);
                    throw new \RuntimeException('Master creation failed');
                }

                /** 2) NORMALIZE + ATTACH RADIUS (kilometers) */
                $radiusKm = data_get($validated, 'radius_km');                 // preferred field from FE
                if (is_null($radiusKm)) { $radiusKm = data_get($validated, 'radius_km'); } // fallback (also km)
                if (is_null($radiusKm) && ($m = data_get($validated, 'radius_m'))) {
                    $radiusKm = round(((float)$m) / 1000, 3);                  // meters → km
                }
                if (!is_null($radiusKm)) {
                    $validated['radius_km'] = (float)$radiusKm;
                    unset($validated['radius'], $validated['radius_m']);
                }

                /** 3) ATTACH MASTER TO SERVICE ROW */
                $validated['master_id'] = $masterId;

                /** 4) CREATE SERVICE (now includes master_id + radius_km) */
                $serviceCreate = $this->service->create($validated);
                if (!data_get($serviceCreate, 'status')) {
                    throw new \RuntimeException(data_get($serviceCreate, 'message', 'Service create failed'));
                }

                /** @var \App\Models\Service $serviceModel */
                $serviceModel = data_get($serviceCreate, 'data');
                $serviceId = $serviceModel->id ?? data_get($serviceCreate, 'data.id');
                if (empty($serviceId)) {
                    throw new \RuntimeException('Service created but id missing');
                }

                /** 5) LINK SERVICE ⇄ MASTER (ServiceMaster) */
                app(\App\Services\ServiceMasterService\ServiceMasterService::class)->create([
                    'service_id'     => $serviceId,
                    'master_id'      => $masterId,
                    'shop_id'        => $this->shop->id,
                    'price'          => $validated['price']        ?? 0,
                    'interval'       => $validated['interval']     ?? 10,
                    'pause'          => $validated['pause']        ?? 20,
                    'type'           => $validated['type']         ?? 'offline_in',
                    'commission_fee' => $commissionFee             ?? 1,
                    'gender'         => $validated['gender']       ?? 1,
                    'active'         => 1,
                ]);

                return $serviceModel->fresh(); // ensure master_id/radius_km present
            });

            return $this->successResponse(
                __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_CREATED, locale: $this->language),
                ServiceResource::make($result)
            );

        } catch (\Throwable $e) {
            Log::error('Service store failed', ['error' => $e->getMessage()]);
            return $this->onErrorResponse([
                'status'  => false,
                'code'    => ResponseError::ERROR_501,
                'message' => $e->getMessage(),
            ]);
        }
    }


    /**
     * Generate unique email like "base+YYYYmmddHHMMSS123@domain".
     */
    private function generateUniqueEmail(string $baseEmail): string
    {
        $parts  = explode('@', $baseEmail);
        $prefix = $parts[0] ?? 'user';
        $domain = $parts[1] ?? 'example.com';
        $unique = $prefix . '+' . now()->format('YmdHis') . rand(100, 999);
        return "$unique@$domain";
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param int $id
     * @param ExtraUpdateRequest $request
     * @return JsonResponse
     */
    public function extrasUpdate(int $id, ExtraUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->service->extrasUpdate($id, $validated);

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_CREATED, locale: $this->language),
            ServiceResource::make(data_get($result, 'data'))
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param int $id
     * @param FaqsUpdateRequest $request
     * @return JsonResponse
     */
    public function faqsUpdate(int $id, FaqsUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->service->faqsUpdate($id, $validated);

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_CREATED, locale: $this->language),
            ServiceResource::make(data_get($result, 'data'))
        );
    }

    /**
     * Display the specified resource.
     *
     * @param Service $service
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
      
        $service = Service::findOrFail($id);
        Log::info("Requesting service ID new...: {$service->id}, shop_id: {$service->shop_id}, user shop_id: {$this->shop->id}");
        if ($service->shop_id !== $this->shop->id) {
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_404,
                'message' => __('errors.' . ResponseError::ERROR_404, locale: $this->language),
            ]);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            ServiceResource::make($this->repository->show($service))
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Service $service
     * @param UpdateRequest $request
     * @return JsonResponse
     */
    public function update(Service $service, UpdateRequest $request): JsonResponse
    {
        if ($service->shop_id !== $this->shop->id) {
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_404,
                'message' => __('errors.' . ResponseError::ERROR_404, locale: $this->language),
            ]);
        }

        $validated = $request->validated();
        unset($validated['commission_fee']);
        $result = $this->service->update($service, $validated);

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_UPDATED, locale: $this->language),
            ServiceResource::make(data_get($result, 'data'))
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param FilterParamsRequest $request
     * @return JsonResponse
     */
    public function destroy(FilterParamsRequest $request): JsonResponse
    {
        $result = $this->service->delete($request->merge(['shop_id' => $this->shop->id])->all());

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_DELETED, locale: $this->language),
            []
        );
    }
}
