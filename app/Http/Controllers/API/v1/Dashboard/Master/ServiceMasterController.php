<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\v1\Dashboard\Master;

use App\Models\ServiceMaster;
use App\Helpers\ResponseError;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\ServiceMasterResource;
use App\Http\Requests\ServiceMaster\StoreRequest;
use App\Http\Requests\ServiceMaster\UpdateRequest;
use App\Services\ServiceMasterService\ServiceMasterService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Repositories\ServiceMasterRepository\ServiceMasterRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ServiceMasterController extends MasterBaseController
{
    public function __construct(private ServiceMasterRepository $repository, private ServiceMasterService $service)
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
        // $models = $this->repository->paginate($request->merge(['master_id' => auth('sanctum')->id()])->all());
        $models = $this->repository->paginate($request->all());
        return ServiceMasterResource::collection($models);
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
        $validated['master_id'] = auth('sanctum')->id();

        $result = $this->service->create($validated);

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_CREATED, locale: $this->language),
            ServiceMasterResource::make(data_get($result, 'data'))
        );
    }

    /**
     * Display the specified resource.
     *
     * @param ServiceMaster $serviceMaster
     * @return JsonResponse
     */
    public function show(ServiceMaster $serviceMaster): JsonResponse
    {
        if ($serviceMaster->master_id !== auth('sanctum')->id()) {
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_404,
                'message' => __('errors.' . ResponseError::ERROR_404, locale: $this->language),
            ]);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            ServiceMasterResource::make($this->repository->show($serviceMaster))
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param ServiceMaster $serviceMaster
     * @param UpdateRequest $request
     * @return JsonResponse
     */
    public function update(ServiceMaster $serviceMaster, UpdateRequest $request): JsonResponse
    {
        if ($serviceMaster->master_id !== auth('sanctum')->id()) {
            return $this->onErrorResponse([
                'code'    => ResponseError::ERROR_404,
                'message' => __('errors.' . ResponseError::ERROR_404, locale: $this->language),
            ]);
        }

        $validated = $request->validated();

        $result = $this->service->update($serviceMaster, $validated);

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_UPDATED, locale: $this->language),
            ServiceMasterResource::make(data_get($result, 'data'))
        );
    }

    

    public function propertiesByMaster($masterId): JsonResponse
    {
        try {
            $properties = DB::table('property')
                ->where('master_id', $masterId)
                ->get();

            if ($properties->isEmpty()) {
                return $this->onErrorResponse([
                    'code'    => ResponseError::ERROR_404,
                    'message' => 'No properties found for this master.',
                ]);
            }

            // Attach amenities to each property
            foreach ($properties as $property) {
                $property->amenities = DB::table('amenity_property')
                    ->join('amenities', 'amenity_property.amenity_id', '=', 'amenities.id')
                    ->where('amenity_property.property_id', $property->id)
                    ->select('amenities.id as amenity_id', 'amenities.name', 'amenities.icon') // adjust fields as needed
                    ->get();
            }

            // Fetch gallery images
            $property->galleryImages = DB::table('galleries')
                ->where('loadable_type', 'App\\Models\\Shop')
                ->where('loadable_id', $property->id)
                ->where('type', 'shop')
                ->pluck('path'); // just get array of URLs

            // Fetch document images
            $property->documents = DB::table('galleries')
                ->where('loadable_type', 'App\\Models\\Shop')
                ->where('loadable_id', $property->host_id)
                ->where('type', 'shop-documents')
                ->pluck('path');

            return $this->successResponse('Properties retrieved successfully.', $properties);

        } catch (\Throwable $e) {
            Log::error('Error fetching properties by master_id', [
                'master_id' => $masterId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Server error. Please check logs.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateProperty(Request $request, $masterId): JsonResponse
    {
        try {
            // Validate required fields (can be done via FormRequest ideally)
            $data = $request->all();

            // Find the property by master_id
            $property = DB::table('property')
                ->where('master_id', $masterId)
                ->first();

            if (!$property) {
                return $this->onErrorResponse([
                    'code' => ResponseError::ERROR_404,
                    'message' => 'Property not found.',
                ]);
            }

            // ✅ Update main property fields
            DB::table('property')
                ->where('master_id', $masterId)
                ->update([
                    'title'         => $data['title']['en'] ?? '',
                    'description'   => $data['description']['en'] ?? '',
                    'property_type' => $data['property_type'],
                    'room_type'     => $data['room_type'],
                    'accommodates'  => $data['accommodates'],
                    'bedrooms'      => $data['bedrooms'],
                    'beds'          => $data['beds'],
                    'bathrooms'     => $data['bathrooms'],
                    'min_nights'    => $data['min_nights'],
                    'max_nights'    => $data['max_nights'],
                    'price_per_night' => $data['price_per_night'],
                    'latitude'      => $data['location']['lat'],
                    'longitude'     => $data['location']['lng'],
                    'address_line'  => $data['address']['en'] ?? '',
                    'country'       => $data['country'],
                    'flat_details'  => $data['flat'],
                    'street'        => $data['street'],
                    'landmark'      => $data['landmark'],
                    'district'      => $data['district'],
                    'city'          => $data['city'],
                    'state'         => $data['state'],
                    'zipcode'       => $data['zipcode'],
                    'background_img'=> $data['bg_image'],
                    'instant_bookable' => $data['instant_bookable'] ?? false,
                    'updated_at'    => now(),
                ]);

            $propertyId = $property->id;

            // ✅ Sync amenities
            DB::table('amenity_property')
                ->where('property_id', $propertyId)
                ->delete();

            foreach ($data['amenities'] as $amenity) {
                DB::table('amenity_property')->insert([
                    'property_id' => $propertyId,
                    'amenity_id'  => $amenity['amenity_id'],
                    'type'        => 'dedicated',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            // ✅ Sync gallery images
            DB::table('galleries')
                ->where('loadable_type', 'App\\Models\\Shop')
                ->where('loadable_id', $propertyId)
                ->where('type', 'shop')
                ->delete();

            foreach ($data['galleryImages'] as $url) {
                DB::table('galleries')->insert([
                    'loadable_type' => 'App\\Models\\Shop',
                    'loadable_id'   => $propertyId,
                    'type'          => 'shop',
                    'path'          => $url,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            // ✅ Sync document images
            DB::table('galleries')
                ->where('loadable_type', 'App\\Models\\Shop')
                ->where('loadable_id', $propertyId)
                ->where('type', 'shop-documents')
                ->delete();

            foreach ($data['documents'] as $url) {
                DB::table('galleries')->insert([
                    'loadable_type' => 'App\\Models\\Shop',
                    'loadable_id'   => $propertyId,
                    'type'          => 'shop-documents',
                    'path'          => $url,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            return $this->successResponse('Property updated successfully.');

        } catch (\Throwable $e) {
            Log::error('Error updating property', [
                'master_id' => $masterId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Server error while updating.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }




    /**
     * Remove the specified resource from storage.
     *
     * @param FilterParamsRequest $request
     * @return JsonResponse
     */
    public function destroy(FilterParamsRequest $request): JsonResponse
    {
        $result = $this->service->delete($request->merge(['master_id' => auth('sanctum')->id()])->all());

        if (!data_get($result, 'status')) {
            return $this->onErrorResponse($result);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::RECORD_WAS_SUCCESSFULLY_DELETED, locale: $this->language),
            []
        );
    }
}
