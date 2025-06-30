<?php

namespace App\Http\Controllers\API\v1\Dashboard\Payment;

use App\Helpers\ResponseError;
use App\Models\Transaction;
use App\Services\PaymentService\MtnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;
use Throwable;

class MtnController extends PaymentBaseController
{
    public function __construct(private MtnService $service)
    {
        parent::__construct($service);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function paymentWebHook(Request $request): JsonResponse
    {
        try {
            Log::error('webhook', $request->all());

            $status = match($request->input('status')) {
                'FAILED'     => Transaction::STATUS_CANCELED,
                'SUCCESSFUL' => Transaction::STATUS_PAID,
                default      => Transaction::STATUS_PROGRESS,
            };

            $token = $request->input('partner_transaction_id');

            $this->service->afterHook($token, $status);

        } catch (Throwable $e) {
            $this->error($e);
        }

        return $this->successResponse(__('errors.' . ResponseError::NO_ERROR));
    }

    //{"service_id":"PAIEMENTMARCHAND_MTN_CM","gu_transaction_id":"1721384891975","status":"FAILED","partner_transaction_id":"1721384891","call_back_url":"https://api.koiffure.com/api/v1/webhook/mtn/payment?booking_id=569","commission":0.0,"message":"[21] Invalid transaction. Please try again.","booking_id":"569"}
}
