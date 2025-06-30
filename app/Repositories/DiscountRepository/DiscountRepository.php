<?php
declare(strict_types=1);

namespace App\Repositories\DiscountRepository;

use App\Models\Discount;
use App\Models\Language;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Schema;

class DiscountRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return Discount::class;
    }

    public function discountsPaginate(array $filter = []): LengthAwarePaginator
    {
        $column = $filter['column'] ?? 'id';

        if ($column !== 'id') {
            $column = Schema::hasColumn('discounts', $column) ? $column : 'id';
        }

        return $this->model()
            ->filter($filter)
            ->orderBy($column, data_get($filter, 'sort','desc'))
            ->paginate($filter['perPage'] ?? 10);
    }

    public function discountDetails(Discount $discount): Discount
    {
        

        return $discount->load([
            'galleries',
            'stocks.stockExtras.value',
            'stocks.stockExtras.group.translation' => fn($query) => $query
                ->where('locale', $this->language),
            'stocks.product.translation' => function($q) {
                $q
                    ->select('id', 'product_id', 'locale', 'title')
                    ->where('locale', $this->language);
            }
        ]);
    }

}
