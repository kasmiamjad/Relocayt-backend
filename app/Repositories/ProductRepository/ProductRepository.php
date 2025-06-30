<?php
declare(strict_types=1);

namespace App\Repositories\ProductRepository;

use App\Models\Language;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Stock;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProductRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return Product::class;
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function productsPaginate(array $filter): LengthAwarePaginator
    {
        /** @var Product $product */
        $product = $this->model();

        return $product
            ->filter($filter)
            ->with([
                'digitalFile',
                'shop' => fn($q) => $q->select('id', 'uuid', 'user_id', 'logo_img', 'background_img', 'type', 'status'),
                'shop.translation' => fn($q) => $q
                    ->where('locale', $this->language)
                    ->select('id', 'locale', 'title', 'shop_id'),
                'translation' => fn($q) => $q
                    ->where('locale', $this->language),
                'translations',
                'category' => fn($q) => $q->select('id', 'uuid'),
                'category.translation' => fn($q) => $q
                    ->where('locale', $this->language)
                    ->select('id', 'category_id', 'locale', 'title'),
                'brand' => fn($q) => $q->select('id', 'uuid', 'title'),
                'unit.translation' => fn($query) => $query->where('locale', $this->language),
                'tags.translation' => fn($q) => $q->select('id', 'category_id', 'locale', 'title')
                    ->where('locale', $this->language),
                'stocks.gallery',
                'stocks.stockExtras.value',
                'stocks.stockExtras.group.translation' => fn($query) => $query->where('locale', $this->language),
            ])
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param int $id
     * @return Model|Builder|Product|null
     */
    public function productDetails(int $id): Model|Builder|Product|null
    {
        

        return $this->model()
            ->whereHas('translation', fn($query) => $query->where('locale', $this->language))
            ->with([
                'shop.translation' => fn($q) => $q
                    ->where('locale', $this->language),
                'category' => fn($q) => $q->select('id', 'uuid'),
                'category.translation' => fn($q) => $q
                    ->select('id', 'category_id', 'locale', 'title')
                    ->where('locale', $this->language),
                'brand' => fn($q) => $q->select('id', 'uuid', 'title'),
                'unit.translation' => fn($q) => $q
                    ->where('locale', $this->language),
                'translation' => fn($query) => $query->where('locale', $this->language),
                'tags.translation' => fn($q) => $q
                    ->select('id', 'category_id', 'locale', 'title')
                    ->where('locale', $this->language),
                'galleries' => fn($q) => $q->select('id', 'type', 'loadable_id', 'path', 'title', 'preview'),
                'properties.group.translation' => fn($q) => $q
                    ->where('locale', $this->language),
                'properties.value',
                'stocks.galleries',
                'stocks.stockExtras.group.translation' => fn($q) => $q
                    ->where('locale', $this->language),
        ])->find($id);
    }

    /**
     * @param string $uuid
     * @return Product|Builder|Model|null
     */
    public function productByUUID(string $uuid): Model|Builder|Product|null
    {
        /** @var Product $product */
        $product = $this->model();
        

        return $product
            ->with([
                'digitalFile',
                'shop.translation' => fn($q) => $q
                    ->where('locale', $this->language),
                'category' => fn($q) => $q->select('id', 'uuid'),
                'category.translation' => fn($q) => $q
                    ->where('locale', $this->language)
                    ->select('id', 'category_id', 'locale', 'title'),
                'brand' => fn($q) => $q->select('id', 'uuid', 'title'),
                'unit.translation' => fn($q) => $q
                    ->where('locale', $this->language),
                'translation' => fn($query) => $query
                    ->where('locale', $this->language),
                'tags.translation' => fn($q) => $q
                    ->select('id', 'category_id', 'locale', 'title')
                    ->where('locale', $this->language),
                'galleries' => fn($q) => $q->select('id', 'type', 'loadable_id', 'path', 'title', 'preview'),
                'properties.group.translation' => fn($q) => $q
                    ->where('locale', $this->language),
                'properties.value',
                'stocks.galleries',
                'stocks.stockExtras.value',
                'stocks.stockExtras.group.translation' => fn($q) => $q
                    ->where('locale', $this->language),
                'stocks.wholeSalePrices',
            ])
            ->firstWhere('uuid', $uuid);
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function productsSearch(array $filter = []): LengthAwarePaginator
    {
        

        return $this->model()
            ->filter($filter)
            ->with([
                'translation' => fn($q) => $q
                    ->select([
                        'id',
                        'product_id',
                        'locale',
                        'title',
                    ])
                    ->where('locale', $this->language),
            ])
            ->whereHas('shop', fn ($query) => $query->where('status', Shop::APPROVED))
            ->whereHas('stock', fn($q) => $q->where('quantity', '>', 0))
            ->latest()
            ->select([
                'id',
                'img',
                'shop_id',
                'uuid',
            ])
            ->paginate($filter['perPage'] ?? 10);
    }


    /**
     * @param array $data
     * @return LengthAwarePaginator
     */
    public function selectStockPaginate(array $data): LengthAwarePaginator
    {
        return Stock::with([
            'gallery',
            'stockExtras.value',
            'stockExtras.group.translation' => fn($q) => $q
                ->where('locale', $this->language),
            'product' => fn($q) => $q->select(['id', 'shop_id']),
            'product.translation' => fn($q) => $q
                ->select('id', 'product_id', 'locale', 'title')
                ->where('locale', $this->language),
        ])
            ->whereHas('product', fn($q) => $q
                ->whereHas(
                    'translation',
                    fn($query) => $query->where('locale', $this->language)
                )
                ->where('shop_id', data_get($data, 'shop_id') )
                ->when(isset($data['active']), fn($q) => $q->where('active', $data['active']))
                ->when(data_get($data, 'status'), fn($q, $status) => $q->where('status', $status))
                ->when(data_get($data, 'search'), function ($q, $search) {

                    $q->where(function ($query) use ($search) {
                        $query
                            ->where('keywords', 'LIKE', "%$search%")
                            ->orWhereHas('translation', function ($q) use ($search) {
                                $q->where('title', 'LIKE', "%$search%")->select('id', 'product_id', 'locale', 'title');
                            });
                    });

                })
            )
            ->where('quantity', '>', 0)
            ->paginate($data['perPage'] ?? 10);
    }

}

