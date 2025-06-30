<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\v1\Dashboard\Seller;

use Exception;
use App\Helpers\ResponseError;
use Illuminate\Http\JsonResponse;
use App\Repositories\DashboardRepository\ReportRepository;
use App\Http\Requests\Report\Booking\PerformanceDashboardRequest;

class ReportController extends SellerBaseController
{

    public function __construct(private ReportRepository $repository)
    {
        parent::__construct();
    }

    /**
     * @param PerformanceDashboardRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function performanceDashboard(PerformanceDashboardRequest $request): JsonResponse
    {
        $result = $this->repository->performanceDashboard($request->merge(['shop_id' => $this->shop->id])->all());

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            $result
        );
    }

}
