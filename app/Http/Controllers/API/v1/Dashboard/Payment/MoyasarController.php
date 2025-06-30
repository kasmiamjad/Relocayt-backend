<?php

namespace App\Http\Controllers\API\v1\Dashboard\Payment;

use App\Models\Payment;
use App\Models\PaymentPayload;
use App\Models\Transaction;
use App\Services\PaymentService\MoyasarService;
use Illuminate\Http\Request;

class MoyasarController extends PaymentBaseController
{
    public function __construct(private MoyasarService $service)
    {
        parent::__construct($service);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function paymentWebHook(Request $request): array
    {
        $payment = Payment::where('tag', Payment::TAG_MOYA_SAR)->first();
        $payload = PaymentPayload::where('payment_id', $payment?->id)->first()?->payload;

        if (data_get($payload, 'secret_token') !== $request->input('secret_token')) {
            return ['message' => '...', 'status' => false];
        }

        $status = $request->input('data.status');

        $status = match ($status) {
            'paid', 'captured'      => Transaction::STATUS_PAID,
            'failed'                => Transaction::STATUS_CANCELED,
            'refunded', 'voided'    => Transaction::STATUS_REFUND,
            default                 => 'progress',
        };

        $token = $request->input('data.invoice_id');

        return $this->service->afterHook($token, $status);
    }

}
