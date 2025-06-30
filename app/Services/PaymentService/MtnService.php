<?php
declare(strict_types=1);

namespace App\Services\PaymentService;

use App\Models\Payment;
use App\Models\PaymentProcess;
use App\Models\Payout;
use Exception;
use Http;
use Illuminate\Database\Eloquent\Model;
use Stripe\Exception\ApiErrorException;
use Throwable;

class MtnService extends BaseService
{
    protected function getModelClass(): string
    {
        return Payout::class;
    }

    /**
     * @param array $data
     * @return PaymentProcess|Model
     * @throws ApiErrorException|Throwable
     */
    public function processTransaction(array $data): Model|PaymentProcess
    {
        /** @var Payment $payment */
        $payment = Payment::with(['paymentPayload'])
            ->where('tag', Payment::TAG_MTN)
            ->first();

        $payload = $payment?->paymentPayload?->payload ?? [];

        [$key, $before] = $this->getPayload($data, $payload);

        $host           = request()->getSchemeAndHttpHost();
        $modelId        = data_get($before, 'model_id');
        $trxId          = time();
        $login          = base64_encode(config('app.LOGIN'));
        $password       = base64_encode(config('app.PASSWORD'));
        $agencyCode     = config('app.AGENCY_CODE');
        $loginAgent     = config('app.LOGIN_AGENT');
        $passwordAgent  = config('app.PASSWORD_AGENT');
        $partnerId      = config('app.PARTNER_ID');

        $apiUrl = "https://apidist.gutouch.net/apidist/sec/touchpayapi/$agencyCode/transaction";

//        $isSouthAfrica = (int)substr($data['phone'], 0, 2);
//
//        $phone = (int)substr($data['phone'], 3);
//
//        if ($isSouthAfrica === 27) {
//            $phone = $isSouthAfrica;
//        }

        $request = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])
            ->put("$apiUrl?loginAgent=$loginAgent&passwordAgent=$passwordAgent", [
                'idFromClient' => $trxId,
                'additionnalInfos'  => [
                    'recipientEmail'     => $data['email'],     //'karl.ngassa@intouchgroup.net'
                    'recipientFirstName' => $data['firstname'], //'Karl'
                    'recipientLastName'  => $data['lastname'],  //'NGASSA'
                    'destinataire'       => $data['phone'],
                ],
                'amount'          => 101,
                'callback'        => "$host/api/v1/webhook/mtn/payment?$key=$modelId&trxId=$trxId",
                'recipientNumber' => $data['phone'],
                'serviceCode'     => $data['type'] === 'mtn' ? 'PAIEMENTMARCHAND_MTN_CM' : 'CM_PAIEMENTMARCHAND_OM_TP',
            ]);

        $json = $request->json();

        if ($request->status() > 299 || $request->status() < 200) {
            throw new Exception($json['description'] ?? $json['detailMessage'] ?? 'error' .  $request->status());
        }

        return PaymentProcess::updateOrCreate([
            'user_id'    => auth('sanctum')->id(),
            'model_type' => data_get($before, 'model_type'),
            'model_id'   => $modelId,
        ], [
            'id'   => $trxId,
            'data' => array_merge(['id' => $trxId, 'payment_id' => $payment->id], $before, $json)
        ]);
    }

}
