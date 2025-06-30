<?php
declare(strict_types=1);

namespace App\Repositories\GiftCartRepository;

use App\Models\Language;
use App\Models\UserGiftCart;
use App\Repositories\CoreRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Schema;

class UserGiftCartRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return UserGiftCart::class;
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
                'giftCart.translation' => fn($q) => $q
                    ->select('id', 'gift_cart_id', 'locale', 'title')
                    ->where('locale', $this->language),
                'transaction.paymentSystem'
            ])
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function paginate(array $filter): LengthAwarePaginator
    {
        

        return $this->model()
            ->filter($filter)
            ->with([
                'giftCart.translation' => fn($q) => $q
                    ->select('id', 'gift_cart_id', 'locale', 'title')
                    ->where('locale', $this->language),
                'user' => fn($q) => $q->select(['id', 'uuid', 'firstname', 'lastname', 'img', 'active']),
                'transaction.paymentSystem'
            ])
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param UserGiftCart $userGiftCart
     * @return UserGiftCart
     */
    public function show(UserGiftCart $userGiftCart): UserGiftCart
    {
        

        return $userGiftCart->load([
            'giftCart.translation' => fn($q) => $q
                ->where('locale', $this->language),
            'user' => fn($q) => $q->select(['id', 'uuid', 'firstname', 'lastname', 'img', 'active']),
            'transaction.paymentSystem'
        ]);
    }

}
