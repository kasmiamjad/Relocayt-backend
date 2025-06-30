<?php
declare(strict_types=1);

namespace App\Repositories\GiftCartRepository;

use App\Models\GiftCart;
use App\Models\Language;
use App\Repositories\CoreRepository;
use Schema;

class GiftCartRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return GiftCart::class;
    }

    /**
     * @param array $filter
     * @return mixed
     */
    public function paginate(array $filter): mixed
    {
        

        return $this->model()
            ->filter($filter)
            ->with([
                'shop:id,uuid,slug,logo_img,user_id',
                'shop.translation' => fn($query) => $query
                    ->where('locale', $this->language),
                'translation' => fn($q) => $q
                    ->select('id', 'gift_cart_id', 'locale', 'title')
                    ->where('locale', $this->language),
            ])
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param array $filter
     * @return mixed
     */
    public function myGiftCarts(array $filter): mixed
    {
        

        return $this->model()
            ->filter($filter)
            ->with([
                'shop:id,uuid,slug,logo_img,user_id',
                'shop.translation' => fn($query) => $query
                    ->where('locale', $this->language),
                'translation' => fn($q) => $q
                    ->select('id', 'gift_cart_id', 'locale', 'title')
                    ->where('locale', $this->language),
            ])
            ->paginate($filter['perPage'] ?? 10);
    }

    public function show(GiftCart $giftCart): GiftCart
    {
        return $giftCart->loadMissing([
            'shop:id,uuid,slug,logo_img,user_id',
            'shop.translation' => fn($query) => $query
                ->where('locale', $this->language),
            'translation' => fn($q) => $q
                ->where('locale', $this->language)
                ->select('id', 'gift_cart_id', 'locale', 'title'),
            'translations'
        ]);
    }
}
