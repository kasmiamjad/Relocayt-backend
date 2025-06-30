<?php
declare(strict_types=1);

namespace App\Repositories\DeliveryPriceRepository;

use App\Models\DeliveryPrice;
use App\Repositories\CoreRepository;
use App\Traits\ByLocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DeliveryPriceRepository extends CoreRepository
{
    use ByLocation;

    protected function getModelClass(): string
    {
        return DeliveryPrice::class;
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function paginate(array $filter): LengthAwarePaginator
    {
        return DeliveryPrice::filter($filter)
            ->with([
                'shop:id,logo_img',
                'translation' => fn($query) => $query
                    ->where('locale', $this->language),
                'shop.translation' => fn($q) => $q
                    ->select(['id', 'shop_id', 'locale', 'title'])
                    ->where('locale', $this->language)
            ] + $this->getWith())
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param DeliveryPrice $model
     * @return DeliveryPrice
     */
    public function show(DeliveryPrice $model): DeliveryPrice
    {
        return $model
            ->load([
                'shop:id,logo_img',
                'translation' => fn($query) => $query
                    ->where('locale', $this->language),
                'shop.translation' => fn($q) => $q
                    ->select(['id', 'shop_id', 'locale', 'title'])
                    ->where('locale', $this->language)
            ] + $this->getWith());
    }

}
