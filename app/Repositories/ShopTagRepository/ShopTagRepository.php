<?php
declare(strict_types=1);

namespace App\Repositories\ShopTagRepository;

use App\Models\Language;
use App\Models\ShopTag;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Schema;

class ShopTagRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return ShopTag::class;
    }

    public function paginate($data = []): LengthAwarePaginator
    {
        /** @var ShopTag $shopTags */
        $shopTags = $this->model();
        
        $column = $data['column'] ?? 'id';

        if ($column !== 'id') {
            $column = Schema::hasColumn('shop_tags', $column) ? $column : 'id';
        }

        return $shopTags
            ->with([
                'translations',
                'translation' => fn($q) => $q
                    ->where('locale', $this->language),
            ])
            ->when(data_get($data, 'search'), function (Builder $query, $search) {
                $query->whereHas('translation', fn($q) => $q->where('title', 'like', "%$search%"));
            })
            ->orderBy($column, $data['sort'] ?? 'desc')
            ->paginate($data['perPage'] ?? 10);
    }

    public function show(ShopTag $shopTag): ShopTag
    {
        

        return $shopTag->loadMissing([
            'translations',
            'translation' => fn($q) => $q
                ->where('locale', $this->language),
        ]);
    }
}
