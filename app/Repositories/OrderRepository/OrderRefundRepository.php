<?php
declare(strict_types=1);

namespace App\Repositories\OrderRepository;

use App\Models\Language;
use App\Models\OrderRefund;
use App\Models\User;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class OrderRefundRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return OrderRefund::class;
    }

    /**
     * @param array $filter
     * @return array|Collection
     */
    public function list(array $filter = []): array|Collection
    {
        /** @var OrderRefund $orderRefund */

        $orderRefund = $this->model();
        

        return $orderRefund
            ->filter($filter)
            ->with([
                'order' => fn($q) => $q->select('id', 'shop_id', 'user_id', 'status', 'created_at'),
                'order.shop:id,uuid',
                'order.shop.translation' => fn($q) => $q
                    ->where('locale', $this->language)
            ])
            ->get();
    }

    public function paginate(array $filter = []): LengthAwarePaginator
    {
        /** @var OrderRefund $orderRefund */

        $orderRefund = $this->model();

        if (data_get($filter, 'user_uuid')) {

            $user = User::whereUuid(data_get($filter, 'user_uuid'))->select(['uuid', 'id'])->first();

            $filter['user_id'] = $user?->id;

        }

        

        return $orderRefund
            ->filter($filter)
            ->with([
                'order.transaction.paymentSystem',
                'order' => fn($q) => $q
                    ->when(data_get($filter, 'user_id'), function ($q, $userId) {
                        $q->where('user_id', $userId);
                    })
                    ->select('id', 'user_id', 'status', 'total_price', 'created_at'),
                'order.shop:id,uuid,logo_img',
                'order.shop.translation' => fn($q) => $q
                    ->select('id', 'locale', 'title', 'shop_id')
                    ->where('locale', $this->language),
                'order.user:id,firstname,lastname,uuid,phone,img,email,created_at,o_count,o_sum'
            ])
            ->paginate($filter['perPage'] ?? 10);
    }

    public function show(OrderRefund $orderRefund): OrderRefund
    {
        

        return $orderRefund->load([
            'order.transaction.paymentSystem',
            'order.shop',
            'order.myAddress',
            'order.deliveryPrice',
            'order.deliveryPoint',
            'order.shop.translation' => fn($q) => $q
                ->where('locale', $this->language)
                ->select('id', 'locale', 'title', 'shop_id'),
            'order.user:id,firstname,lastname,uuid,phone,img,email,created_at,o_count,o_sum',
            'order.deliveryman.deliveryManSetting',
            'order.orderDetails.stock.stockExtras.value',
            'order.orderDetails.stock.stockExtras.group.translation' => function ($q) {
                $q
                    ->select('id', 'extra_group_id', 'locale', 'title')
                    ->where('locale', $this->language);
            },
            'order.orderDetails.stock.product.translation' => function ($q) {
                $q
                    ->select('id', 'product_id', 'locale', 'title')
                    ->where('locale', $this->language);
            },
            'order.orderDetails.replaceStock.stockExtras.value',
            'order.orderDetails.replaceStock.stockExtras.group.translation' => function ($q) {
                $q
                    ->select('id', 'extra_group_id', 'locale', 'title')
                    ->where('locale', $this->language);
            },
            'order.orderDetails.replaceStock.product.translation' => function ($q) {
                $q
                    ->select('id', 'product_id', 'locale', 'title')
                    ->where('locale', $this->language);
            },
        ]);
    }
}
