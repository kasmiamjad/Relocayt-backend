<?php
declare(strict_types=1);

namespace App\Repositories\OrderRepository;

use App\Helpers\ResponseError;
use App\Models\Booking;
use App\Models\Language;
use App\Models\Order;
use App\Models\Settings;
use App\Models\Translation;
use App\Repositories\CoreRepository;
use App\Traits\SetCurrency;
use Barryvdh\DomPDF\Facade\Pdf as Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class OrderRepository extends CoreRepository
{
    use SetCurrency;

    /**
     * @return string
     */
    protected function getModelClass(): string
    {
        return Order::class;
    }

    public function getWith(?int $userId = null): array
    {
        

        return [
            'user',
            'currency',
            'review' => fn($q) => $userId ? $q->where('user_id', $userId) : $q,
            'shop:id,uuid,slug,latitude,longitude,tax,background_img,open,logo_img,uuid,phone,delivery_type,delivery_time,verify',
            'shop.translation' => fn($q) => $q
                ->select([
                    'id',
                    'shop_id',
                    'locale',
                    'title',
                    'address',
                ])
                ->where('locale', $this->language),
            'orderDetails' => fn($q) => $q->with([
                'galleries',
                'stock.stockExtras.value',
                'stock.product.translation' => fn($q) => $q
                    ->select([
                        'id',
                        'product_id',
                        'locale',
                        'title',
                    ])
                    ->where('locale', $this->language),
                'stock.stockExtras.group.translation' => function ($q) {
                    $q
                        ->select('id', 'extra_group_id', 'locale', 'title')
                        ->where('locale', $this->language);
                },
                'replaceStock.stockExtras.value',
                'replaceStock.product.translation' => fn($q) => $q
                    ->select([
                        'id',
                        'product_id',
                        'locale',
                        'title',
                    ])
                    ->where('locale', $this->language),
                'replaceStock.stockExtras.group.translation' => function ($q) {
                    $q
                        ->select('id', 'extra_group_id', 'locale', 'title')
                        ->where('locale', $this->language);
                },
            ]),
            'deliveryman.deliveryManSetting',
            'orderRefunds',
            'transaction.paymentSystem',
            'galleries',
            'myAddress',
            'deliveryPrice',
            'deliveryPoint.workingDays',
            'deliveryPoint.closedDates',
            'coupon',
            'pointHistories',
            'notes'
        ];
    }
    /**
     * @param array $filter
     * @return array|\Illuminate\Database\Eloquent\Collection
     */
    public function ordersList(array $filter = []): array|\Illuminate\Database\Eloquent\Collection
    {
        return $this->model()
            ->filter($filter)
            ->with([
                'deliveryman',
            ])
            ->get();
    }

    /**
     * This is only for users route
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function ordersPaginate(array $filter = []): LengthAwarePaginator
    {
        /** @var Order $order */
        $order = $this->model();

        return $order
            ->withCount('orderDetails')
            ->with([
                'children:id,total_price,parent_id',
                'shop:id,uuid,slug,logo_img',
                'shop.translation' => fn($q) => $q->select([
                    'title',
                    'locale',
                    'shop_id',
                    'id',
                ])->where('locale', $this->language),
                'currency',
                'user:id,firstname,lastname,uuid,img,phone',
            ])
            ->filter($filter)
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * This is only for users route
     * @param array $filter
     * @return Paginator
     */
    public function simpleOrdersPaginate(array $filter = []): Paginator
    {
        /** @var Order $order */
        $order = $this->model();

        return $order
            ->filter($filter)
            ->select([
                'id',
                'user_id',
                'total_price',
                'delivery_date',
                'total_tax',
                'currency_id',
                'rate',
                'status',
                'total_discount',
            ])
            ->simplePaginate($filter['perPage'] ?? 10);
    }

    /**
     * @param int $id
     * @param int|null $shopId
     * @param int|null $userId
     * @return Order|null
     */
    public function orderById(int $id, ?int $shopId = null, ?int $userId = null): ?Order
    {
        return $this->model()
            ->with($this->getWith($userId))
            ->when($shopId, fn($q) => $q->where('shop_id', $shopId))
            ->find($id);
    }

    /**
     * @param int $id
     * @param int|null $shopId
     * @param int|null $userId
     * @return Collection|null
     */
    public function ordersByParentId(int $id, ?int $shopId = null, ?int $userId = null): ?Collection
    {
        return $this->model()
            ->with($this->getWith($userId))
            ->when($shopId, fn($q) => $q->where('shop_id', $shopId))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->where(fn($q) => $q->where('id', $id)->orWhere('parent_id', $id))
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * @param int $id
     * @return Response|array
     */
    public function exportPDF(int $id): Response|array
    {
        

        $order = Order::with([
            'orderDetails.stock.product.translation' => fn($q) => $q
                ->where('locale', $this->language),
            'orderDetails.stock.stockExtras.value',
            'orderDetails.stock.stockExtras.group.translation' => function ($q) {
                $q->select('id', 'extra_group_id', 'locale', 'title')
                    ->where('locale', $this->language);
            },
            'shop:id,uuid,slug,tax',
            'shop.seller:id,phone',
            'shop.translation' => fn($q) => $q->select('id', 'shop_id', 'locale', 'title', 'address')
                ->where('locale', $this->language),
            'user:id,phone,firstname,lastname',
            'currency:id,symbol,position'
        ])->find($id);

        if (!$order) {
            return [
                'status'    => false,
                'code'      => ResponseError::ERROR_404,
                'message'   => __('errors.' . ResponseError::ERROR_404, locale: $this->language),
            ];
        }

        $logo = Settings::where('key', 'logo')->first()?->value;
        $lang = $this->language;
        $timeFormat = Settings::where('key', 'hour_format')->first()?->value;
        $timeFormat = $timeFormat === 'hh:mm a' ? 'h:i a' : 'H:i';

        Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);

        $pdf = PDF::loadView('order-invoice', compact('order', 'logo', 'lang', 'timeFormat'));

        return $pdf->download('invoice.pdf');
    }

    /**
     * @param int $id
     * @return Response|array
     */
    public function exportByParentPDF(int $id): Response|array
    {
        

        $orders = Order::with([
            'orderDetails.stock.product.translation' => fn($q) => $q
                ->where('locale', $this->language),
            'orderDetails.stock.stockExtras.value',
            'orderDetails.stock.stockExtras.group.translation' => function ($q) {
                $q->select('id', 'extra_group_id', 'locale', 'title')
                    ->where('locale', $this->language);
            },
            'shop:id,uuid,slug,tax',
            'shop.translation' => fn($q) => $q->select('id', 'shop_id', 'locale', 'title', 'address')
                ->where('locale', $this->language),
        ])
            ->where('id', $id)
            ->orWhere('parent_id', $id)
            ->get();

        if ($orders->count() === 0) {
            return [
                'status'    => false,
                'code'      => ResponseError::ERROR_404,
                'message'   => __('errors.' . ResponseError::ERROR_404, locale: $this->language),
            ];
        }

        $orders[0] = $orders[0]->loadMissing([
            'user:id,phone,firstname,lastname',
            'currency:id,symbol,position'
        ]);

        $logo       = Settings::where('key', 'logo')->first()?->value;
        $lang       = $this->language;
        $timeFormat = Settings::where('key', 'hour_format')->first()?->value;
        $timeFormat = $timeFormat === 'hh:mm a' ? 'h:i a' : 'H:i';

        Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);

        $pdf = PDF::loadView('parent-order-invoice', compact('orders', 'logo', 'lang', 'timeFormat'));

        $time = time();

        return $pdf->download("invoice-$time.pdf");
    }

    /**
     * @param int $id
     * @return Response|array
     */
    public function bookingExportPDF(int $id): Response|array
    {
        

        $booking = Booking::with([
            'master:id,firstname,lastname',
            'user:id,firstname,lastname',
            'userMemberShip',
            'currency',
            'extraTimes',
            'transaction.paymentSystem',
            'shop:id,user_id',
            'shop.translation' => fn($query) => $query
                ->select(['id', 'locale', 'title', 'shop_id'])
                ->where('locale', $this->language),

            'serviceMaster.service.translation' => fn($query) => $query
                ->select(['id', 'locale', 'title', 'service_id'])
                ->where('locale', $this->language),
            'extras.translation' => fn($query) => $query
                ->select(['id', 'locale', 'title', 'service_extra_id'])
                ->where('locale', $this->language),
            'children:id,parent_id,service_master_id,master_id,user_member_ship_id,gender,discount,price,service_fee,rate,status',
            'children.extraTimes',
            'children.serviceMaster.service.translation' => fn($query) => $query
                ->where('locale', $this->language),
            'children.extras.translation' => fn($query) => $query
                ->select(['id', 'locale', 'title', 'service_extra_id'])
                ->where('locale', $this->language),
        ])
            ->whereNull('parent_id')
            ->find($id);

        if (!$booking) {
            return [
                'status'    => false,
                'code'      => ResponseError::ERROR_404,
                'message'   => __('errors.' . ResponseError::ERROR_404, locale: $this->language),
            ];
        }

        /** @var Booking $booking */
        $titleKey = "booking.invoice.$booking->status.title";
        $title    = Translation::where(['locale' => $this->language, 'key' => $titleKey])->first()?->value ?? $titleKey;
        $logo     = Settings::where('key', 'logo')->first()?->value;

        Pdf::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);

        $pdf = Pdf::loadView('booking-invoice', [
            'model' => $booking,
            'lang'  => $this->language,
            'title' => $title,
            'logo'  => $logo,
        ]);

        return $pdf->download('invoice.pdf');
    }

}
