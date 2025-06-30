<?php

namespace App\Services\PaymentService;

use Http;
use Exception;
use App\Models\User;
use App\Models\Payout;
use App\Models\Payment;
use Illuminate\Support\Str;
use App\Models\PaymentPayload;
use App\Models\PaymentProcess;

class PayFastService extends BaseService
{
	protected function getModelClass(): string
	{
		return Payout::class;
	}

	/**
	 * @param array $data
	 * @return PaymentProcess|array
	 * @throws Exception
	 */
	public function processTransaction(array $data): PaymentProcess|array
	{
		$payment = Payment::where('tag', Payment::TAG_PAY_FAST)->first();

		$paymentPayload = PaymentPayload::where('payment_id', $payment?->id)->first();
		$payload        = $paymentPayload?->payload ?? [];
		[$key, $before] = $this->getPayload($data, $payload);
		$modelId 		= data_get($before, 'model_id');

		$host = request()->getSchemeAndHttpHost();
		$url  = "$host/payment-success?$key=$modelId&lang=$this->language";
		$totalPrice = round((float)data_get($before, 'total_price'), 2);
		$uuid = Str::uuid();

		$notifyUrl = "$host/api/v1/webhook/pay-fast/payment?payment_id=$uuid";

		/** @var User $user */
		$user = auth('sanctum')->user();

		$body = [
			'merchant_id' 	=> (int)$payload['merchant_id'],
			'merchant_key' 	=> $payload['merchant_key'],
			'return_url' 	=> $url,
			'cancel_url' 	=> "$url&status=error",
			'notify_url' 	=> $notifyUrl,
			'amount' 		=> $totalPrice,
			'name_first' 	=> $user?->firstname ?? 'First Name',
			'name_last'  	=> $user?->lastname  ?? 'Last Name',
			'item_name' 	=> Str::replace('_id', '', Str::ucfirst($key)),
			'email_address' => $user->email ?? Str::random() . '@gmail.com',
		];

		if (data_get($data, 'type') === 'mobile') {

			unset($body['merchant_id']);
			unset($body['merchant_key']);

			return PaymentProcess::updateOrCreate([
				'user_id'    => auth('sanctum')->id(),
				'model_id'   => $modelId,
				'model_type' => data_get($before, 'model_type')
			], [
				'id' => $uuid,
				'data' => array_merge([
					'price' 	 => $totalPrice,
					'payment_id' => $payment?->id,
					'sandbox'	 => $payload['sandbox'],
				], $before, $body),
			]);

		}

		$body['signature'] = $this->generateSignature($body, $payload['pass_phrase']);

		$response = $this->generatePaymentIdentifier($body, $payload);

		if (!isset($response['uuid'])) {
			throw new Exception('error pay fast');
		}

		return PaymentProcess::updateOrCreate([
			'user_id'    => auth('sanctum')->id(),
			'model_id'   => $modelId,
			'model_type' => data_get($before, 'model_type')
		], [
			'id' => $uuid,
			'data' => array_merge([
				'price' 	 => $totalPrice,
				'payment_id' => $payment?->id,
				'sandbox'	 => $payload['sandbox'],
				'signature'  => $body["signature"],
			], $before, $response),
		]);
	}

	/**
	 * @param array $data
	 * @param string|null $passPhrase
	 * @return string
	 */
	private function generateSignature(array $data, ?string $passPhrase = null): string
	{
		if ($passPhrase !== null) {
			$data['passphrase'] = $passPhrase;
		}

		// Sort the array by key, alphabetically
		ksort($data);

		//create parameter string
		$pfParamString = http_build_query($data);

		return md5($pfParamString);
	}

	public function generatePaymentIdentifier(array $body, array $payload): array|string|null
	{
		$url = 'www.payfast.co.za';

		if ($payload['sandbox']) {
			$url = 'sandbox.payfast.co.za';
		}

		$request = Http::post("https://$url/onsite/process", $body);

		return $request->json();
	}

}
