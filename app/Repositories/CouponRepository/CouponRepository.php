<?php
declare(strict_types=1);

namespace App\Repositories\CouponRepository;

use App\Helpers\ResponseError;
use App\Models\BookingCoupon;
use App\Models\Coupon;
use App\Models\Language;
use App\Models\OrderCoupon;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CouponRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return Coupon::class;
    }

    public function couponsList(array $filter): Collection|array
    {
        

        return $this->model()
            ->filter($filter)
            ->with([
                'translation' => fn($q) => $q
                    ->select('id', 'coupon_id', 'locale', 'title')
                    ->where('locale', $this->language),
                'shop:id,logo_img',
                'shop.translation' => fn($q) => $q
                    ->select('id', 'shop_id', 'locale', 'title')
                    ->where('locale', $this->language)
            ])
            ->get();
    }

    public function couponsPaginate(array $filter): LengthAwarePaginator
    {
        

        return $this->model()
            ->filter($filter)
            ->with([
                'translation' => fn($q) => $q
                    ->where('locale', $this->language)
                    ->select('id', 'coupon_id', 'locale', 'title'),
                'shop:id,logo_img',
                'shop.translation' => fn($q) => $q
                    ->where('locale', $this->language)
                    ->select('id', 'shop_id', 'locale', 'title'),
            ])
            ->whereHas('translation', function ($q) {
                $q->where('locale', $this->language);
            })
            ->paginate($filter['perPage'] ?? 10);
    }

    public function show(Coupon $coupon): Coupon
    {
        return $coupon->loadMissing([
            'translation' => fn($q) => $q
                ->where('locale', $this->language)
                ->select('id', 'coupon_id', 'locale', 'title'),
            'shop:id,logo_img',
            'shop.translation' => fn($q) => $q
                ->where('locale', $this->language)
                ->select('id', 'shop_id', 'locale', 'title'),
            'translations'
        ]);
    }

    public function checkCoupon(array $filter): array
    {
        $coupon = Coupon::where('name', $filter['coupon'])
            ->where('shop_id', $filter['shop_id'])
            ->where('qty', '>', 0)
            ->first();

        if (empty($coupon)) {
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_249,
                'message' => __('errors.' . ResponseError::ERROR_249, locale: $this->language),
            ];
        }

        if ($coupon->expired_at < now()) {
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_250,
                'message' => __('errors.' . ResponseError::ERROR_250, locale: $this->language),
            ];
        }

        $class = OrderCoupon::class;

        if ($coupon->for == Coupon::bookingTotalPrice) {
            $class = BookingCoupon::class;
        }

        $result = $class::where(function ($q) use ($filter) {
            $q
                ->where('user_id', data_get($filter, 'user_id'))
                ->orWhere('user_id', auth('sanctum')->id());
        })
            ->where('name', $filter['coupon'])
            ->first();

        if (empty($result)) {
            return [
                'status' => true,
                'code'   => ResponseError::NO_ERROR,
                'data'   => $coupon,
            ];
        }

        return [
            'status'  => false,
            'code'    => ResponseError::ERROR_251,
            'message' => __('errors.' . ResponseError::ERROR_251, locale: $this->language),
        ];
    }
}
