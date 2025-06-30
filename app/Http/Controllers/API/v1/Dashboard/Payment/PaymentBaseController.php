<?php

namespace App\Http\Controllers\API\v1\Dashboard\Payment;

use Throwable;
use App\Traits\ApiResponse;
use App\Services\CoreService;
use App\Models\PaymentProcess;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\PaymentService\BaseService;
use App\Http\Requests\Payment\PaymentRequest;
use App\Services\PaymentService\StripeService;

abstract class PaymentBaseController extends Controller
{
    use ApiResponse;

    public function __construct(private BaseService|StripeService|CoreService $service)
    {
        parent::__construct();

        $this->middleware(['sanctum.check'])->except(['created', 'resultTransaction', 'mtnProcess', 'paymentWebHook']);
    }

    /**
     * process transaction.
     *
     * @param PaymentRequest $request
     * @return PaymentProcess|JsonResponse
     */
    public function processTransaction(PaymentRequest $request): PaymentProcess|JsonResponse
    {
        try {
            $result = $this->service->processTransaction($request->all());

            return $this->successResponse('success', $result);
        } catch (Throwable $e) {

            $this->error($e);

            return $this->onErrorResponse(['message' => $e->getMessage()]);
        }
    }
}
