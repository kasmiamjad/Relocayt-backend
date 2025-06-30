<?php
declare(strict_types=1);

namespace App\Repositories\DigitalFileRepository;

use App\Models\DigitalFile;
use App\Models\Language;
use App\Models\UserDigitalFile;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class DigitalFileRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return DigitalFile::class;
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function paginate(array $filter): LengthAwarePaginator
    {
        

        return DigitalFile::filter($filter)
            ->with([
                'product' => fn($query) => $query->when(data_get($filter, 'shop_id'), function ($query, $shopId) {
                    $query->whereHas('product', fn($q) => $q->where('shop_id', $shopId));
                }),
                'product.translation' => fn($query) => $query->where('locale', $this->language),
            ])
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param DigitalFile $model
     * @return DigitalFile
     */
    public function show(DigitalFile $model): DigitalFile
    {
        

        return $model->loadMissing([
            'product.translation' => fn($query) => $query->where('locale', $this->language),
        ]);
    }

    public function myDigitalFile(array $filter): LengthAwarePaginator
    {
        

        return UserDigitalFile::filter($filter)
            ->with([
                'digitalFile.product' => fn($query) => $query
                    ->when(data_get($filter, 'shop_id'), function ($query, $shopId) {
                        $query->whereHas('product', fn($q) => $q->where('shop_id', $shopId));
                    }),
                'digitalFile.product.translation' => fn($query) => $query
                    ->where('locale', $this->language),
                'digitalFile.product.stocks' => fn($q) => $q->where('quantity', '>', 0),
                'digitalFile.product.stocks.stockExtras.value',
                'digitalFile.product.stocks.stockExtras.group.translation' => function ($q) {
                    $q->where('locale', $this->language);
                },
                'digitalFile.product.stocks.bonus' => fn($q) => $q
                    ->where('expired_at', '>', now())
                    ->select([
                        'id', 'expired_at', 'stock_id',
                        'bonus_quantity', 'value', 'type', 'status'
                    ]),
                'digitalFile.product.stocks.discount' => fn($q) => $q
                    ->where('start', '<=', today())
                    ->where('end', '>=', today())
                    ->where('active', 1),
            ])
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param int $id
     * @return Builder|Collection|Model|null
     */
    public function getDigitalFile(int $id): Model|Collection|Builder|null
    {
        

        return UserDigitalFile::with([
            'digitalFile:id,path,product_id,active',
            'digitalFile.product:id',
            'digitalFile.product.translation' => fn($query) => $query
                ->where('locale', $this->language),
        ])
            ->where('user_id', auth('sanctum')->id())
            ->find($id);
    }

}
