<?php
declare(strict_types=1);

namespace App\Repositories\AdsPackageRepository;

use App\Models\Language;
use App\Models\ShopAdsPackage;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Schema;

class ShopAdsPackageRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return ShopAdsPackage::class;
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function paginate(array $filter): LengthAwarePaginator
    {
        

        return ShopAdsPackage::filter($filter)
            ->with([
                'transaction',
                'shop:id',
                'shop.translation' => fn($query) => $query
                    ->select(['id', 'shop_id', 'locale', 'title'])
                    ->where('locale', $this->language),
                'adsPackage.translation' => fn($query) => $query
                    ->where('locale', $this->language),
            ])
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param ShopAdsPackage $model
     * @return ShopAdsPackage
     */
    public function show(ShopAdsPackage $model): ShopAdsPackage
    {
        

        return $model->loadMissing([
            'transaction',
            'shopAdsProducts.product.translation' => fn($query) => $query
                ->where('locale', $this->language),
            'adsPackage.translation' => fn($query) => $query
                ->where('locale', $this->language),
            'shop:id',
            'shop.translation' => fn($query) => $query
                ->select(['id', 'shop_id', 'locale', 'title'])
                ->where('locale', $this->language),
        ]);
    }

}
