<?php
declare(strict_types=1);

namespace App\Http\Requests\Payment;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Booking\StoreRequest as BookingStoreRequest;
use App\Http\Requests\Order\StoreRequest;
use App\Models\BookingExtraTime;
use Illuminate\Validation\Rule;

class PaymentRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $userId    = auth('sanctum')->id();

        $cartId    = request('cart_id');
        $tips      = request('tips');

        $rules = [];

        if ($cartId) {
            $rules = (new StoreRequest)->rules();
        }

        return [
            'cart_id' => [
                Rule::exists('carts', 'id')->where('owner_id', $userId)
            ],
            'booking_id' => [
                Rule::exists('bookings', 'id')->where('user_id', $userId)->when(!$tips || !request('extra_time'), fn($q) => $q->whereNull('parent_id'))
            ],
            'gift_cart_id' => [
                Rule::exists('gift_carts', 'id')->where('active', true)
            ],
            'member_ship_id' => [
                Rule::exists('member_ships', 'id')->where('active', true)
            ],
            'parcel_id' => [
                Rule::exists('parcel_orders', 'id')->where('user_id', $userId)
            ],
            'subscription_id' => [
                Rule::exists('subscriptions', 'id')->where('active', true)
            ],
            'ads_package_id' => [
                Rule::exists('ads_packages', 'id')->where('active', true)
            ],
            'wallet_id' => [
                Rule::exists('wallets', 'id')->where('user_id', auth('sanctum')->id())
            ],
            'total_price'   => ['numeric'],
            'phone'         => 'int',
            'email'         => 'string',
            'firstname'     => 'string',
            'lastname'      => 'string',
            'type'          => 'string|in:mtn,orange',
        ] + $rules;
    }

}
