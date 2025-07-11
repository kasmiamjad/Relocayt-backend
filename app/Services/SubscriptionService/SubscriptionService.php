<?php
declare(strict_types=1);

namespace App\Services\SubscriptionService;

use App\Helpers\ResponseError;
use App\Models\Shop;
use App\Models\ShopSubscription;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\CoreService;
use DB;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class SubscriptionService extends CoreService
{
    protected function getModelClass(): string
    {
        return Subscription::class;
    }

    /**
     * @param array $data
     * @return array
     */
    public function create(array $data): array
    {
        try {
            $subscription = $this->model()->create($data);

            return ['status' => true, 'code' => ResponseError::NO_ERROR, 'data' => $subscription];

        } catch (Throwable $e) {
            $this->error($e);
            return [
                'status'    => false,
                'code'      => ResponseError::ERROR_501,
                'message'   => __('errors.' . ResponseError::ERROR_501, locale: $this->language)
            ];
        }
    }

    /**
     * @param Subscription $subscription
     * @param array $data
     * @return array
     */
    public function update(Subscription $subscription, array $data): array
    {
        try {
            $subscription->update($data);

            return ['status' => true, 'code' => ResponseError::NO_ERROR, 'data' => $subscription];

        } catch (Throwable $e) {
            $this->error($e);
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_502,
                'message' => __('errors.' . ResponseError::ERROR_502, locale: $this->language)
            ];
        }
    }

}
