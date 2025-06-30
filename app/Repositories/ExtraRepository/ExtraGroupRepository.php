<?php
declare(strict_types=1);

namespace App\Repositories\ExtraRepository;

use App\Models\ExtraGroup;
use App\Models\Language;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Schema;

class ExtraGroupRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return ExtraGroup::class;
    }

    public function extraGroupList(array $filter = []): LengthAwarePaginator
    {
        
        $column = $filter['column'] ?? 'id';

        if ($column !== 'id') {
            $column = Schema::hasColumn('extra_groups', $column) ? $column : 'id';
        }

        return $this->model()
            ->with([
                'shop:id,uuid',
                'shop.translation' => fn($q) => $q->select('id', 'locale', 'title', 'shop_id')
                    ->where('locale', $this->language),
                'translation' => fn($q) => $q
                    ->where('locale', $this->language)
                    ->when(data_get($filter, 'search'), fn ($q, $search) => $q->where('title', 'LIKE', "%$search%"))
            ])
            ->whereHas('translation', fn($q) => $q
                ->where('locale', $this->language)
                ->when(data_get($filter, 'search'), fn ($q, $search) => $q->where('title', 'LIKE', "%$search%"))
            )
            ->when(data_get($filter, 'active'),  fn($q, $active) => $q->where('active', $active))
            ->when(data_get($filter, 'shop_id'), fn($q) => $q->where(function ($query) use ($filter) {

                $query->where('shop_id', data_get($filter, 'shop_id'));

                if (!isset($filter['is_admin'])) {
                    $query->orWhereNull('shop_id');
                }

            }))
            ->orderBy($column, $filter['sort'] ?? 'desc')
            ->paginate($filter['perPage'] ?? 10);
    }

    public function extraGroupDetails(int $id): Model|null
    {
        

        return $this->model()
            ->with([
                'shop:id,uuid',
                'shop.translation' => fn($q) => $q
                    ->select('id', 'locale', 'title', 'shop_id')
                    ->where('locale', $this->language),

                'translation' => fn($q) => $q
                    ->where('locale', $this->language)
            ])
            ->whereHas(
                'translation',
                fn($query) => $query->where('locale', $this->language)
            )
            ->find($id);
    }

}
