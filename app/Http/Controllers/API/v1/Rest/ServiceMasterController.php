<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\v1\Rest;

use App\Helpers\ResponseError;
use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\ServiceMasterResource;
use App\Models\ServiceMaster;
use App\Repositories\ServiceMasterRepository\ServiceMasterRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceMasterController extends RestBaseController
{
    public function __construct(private ServiceMasterRepository $repository)
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
        $filter = $request->merge(['active' => true])->all();

        if (!isset($filter['page'])) {
            $filter['page'] = 1;
        }

        if (!isset($filter['perPage'])) {
            $filter['perPage'] = 100;
        }

        $models = $this->repository->paginate($filter);

        return ServiceMasterResource::collection($models);
    }

    /**
     * Display the specified resource.
     *
     * @param ServiceMaster $serviceMaster
     * @return JsonResponse
     */
    public function show(ServiceMaster $serviceMaster): JsonResponse
    {
        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            ServiceMasterResource::make($this->repository->show($serviceMaster))
        );
    }

}
