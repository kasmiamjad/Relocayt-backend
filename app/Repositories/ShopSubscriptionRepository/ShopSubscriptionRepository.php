<?php
declare(strict_types=1);

namespace App\Repositories\ShopSubscriptionRepository;

use App\Models\Language;
use App\Models\ShopSocial;
use App\Models\ShopSubscription;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShopSubscriptionRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return ShopSocial::class;
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function paginate(array $filter): LengthAwarePaginator
    {
        

        return ShopSubscription::filter($filter)
            ->with([
                'subscription',
                'transaction',
                'shop.translation' => fn($query) => $query
                    ->where('locale', $this->language),
            ])
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param ShopSubscription $shopSubscription
     * @return ShopSubscription
     */
    public function show(ShopSubscription $shopSubscription): ShopSubscription
    {
        

        return $shopSubscription->load([
            'subscription',
            'transaction',
            'shop.translation' => fn($query) => $query
                ->where('locale', $this->language),
        ]);
    }
}
