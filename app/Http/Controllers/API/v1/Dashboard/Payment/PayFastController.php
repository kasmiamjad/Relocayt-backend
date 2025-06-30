<?php

namespace App\Http\Controllers\API\v1\Dashboard\Payment;

use App\Traits\OnResponse;
use App\Models\Transaction;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Services\PaymentService\PayFastService;

class PayFastController extends PaymentBaseController
{
	use OnResponse, ApiResponse;

	public function __construct(private PayFastService $service)
	{
		parent::__construct($service);
	}

	/**
	 * @param Request $request
	 * @return void
	 */
	public function paymentWebHook(Request $request): void
	{
		$token = $request->input('m_payment_id');

        if (empty($token)) {
            $token = $request->input('payment_id');
        }

		$status = match ($request->input('payment_status')) {
			'COMPLETE' => Transaction::STATUS_PAID,
			'CANCELED' => Transaction::STATUS_CANCELED,
			default	   => 'progress',
		};

		$this->service->afterHook($token, $status);
	}
}
