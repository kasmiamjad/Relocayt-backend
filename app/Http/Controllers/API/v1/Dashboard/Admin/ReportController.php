<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\v1\Dashboard\Admin;

use App\Http\Requests\FilterParamsRequest;
use App\Http\Requests\Report\Booking\AppointmentsSummaryRequest;
use App\Http\Requests\Report\Booking\PaymentsSummaryRequest;
use App\Http\Requests\Report\Booking\SalesListRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\GiftCartResource;
use App\Http\Resources\MemberShipResource;
use App\Http\Resources\TranslationResource;
use Exception;
use App\Helpers\ResponseError;
use Illuminate\Http\JsonResponse;
use App\Repositories\DashboardRepository\ReportRepository;
use App\Http\Requests\Report\Booking\PerformanceDashboardRequest;
use App\Http\Requests\Report\Booking\SalesSummaryRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReportController extends AdminBaseController
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
        $result = $this->repository->performanceDashboard($request->validated());

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            $result
        );
    }

    /**
     * @param PerformanceDashboardRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function onlinePresenceDashboard(PerformanceDashboardRequest $request): JsonResponse
    {
        $result = $this->repository->onlinePresenceDashboard($request->validated());

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            $result
        );
    }

    /**
     * @param SalesSummaryRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function salesSummary(SalesSummaryRequest $request): JsonResponse
    {
        $result = $this->repository->salesSummary($request->validated());

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            $result
        );
    }

    /**
     * @param SalesListRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function salesList(SalesListRequest $request): JsonResponse
    {
        $models = $this->repository->salesList($request->validated());

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            BookingResource::collection($models)
        );
    }

    /**
     * @param SalesListRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function salesLogDetail(SalesListRequest $request): JsonResponse
    {
        $models = $this->repository->salesLogDetail($request->validated());

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            BookingResource::collection($models)
        );
    }

    /**
     * @param SalesListRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function giftCardList(SalesListRequest $request): JsonResponse
    {
        $models = $this->repository->giftCardList($request->validated());

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            GiftCartResource::collection($models)
        );
    }

    /**
     * @param SalesListRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function membershipList(SalesListRequest $request): JsonResponse
    {
        $models = $this->repository->membershipList($request->validated());

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            MemberShipResource::collection($models)
        );
    }

    /**
     * @param PaymentsSummaryRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function paymentsSummary(PaymentsSummaryRequest $request): JsonResponse
    {
        $result = $this->repository->paymentsSummary($request->validated());

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            $result
        );
    }

    /**
     * @param PaymentsSummaryRequest $request
     * @return AnonymousResourceCollection
     * @throws Exception
     */
    public function paymentTransactions(PaymentsSummaryRequest $request): AnonymousResourceCollection
    {
        $models = $this->repository->paymentTransactions($request->validated());

        return TranslationResource::collection($models);
    }

    /**
     * @param PaymentsSummaryRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function financeSummary(PaymentsSummaryRequest $request): JsonResponse
    {
        $result = $this->repository->financeSummary($request->validated());

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            $result
        );
    }

    /**
     * @param AppointmentsSummaryRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function appointmentsSummary(AppointmentsSummaryRequest $request): JsonResponse
    {
        $result = $this->repository->appointmentsSummary($request->validated());

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            $result
        );
    }

    /**
     * @param FilterParamsRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function workingHours(FilterParamsRequest $request): JsonResponse
    {
        $result = $this->repository->workingHours($request->all());

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            $result
        );
    }

}
