<?php
declare(strict_types=1);

namespace App\Repositories\AreaRepository;
set_time_limit(0);
ini_set('memory_limit', '4G');

use App\Models\Area;
use App\Models\Language;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Schema;

class AreaRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return Area::class;
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function paginate(array $filter): LengthAwarePaginator
    {
        

        $column = $filter['column'] ?? 'id';
        $sort   = $filter['sort'] ?? 'desc';

        if ($column !== 'id') {
            $column = Schema::hasColumn('areas', $column) ? $column : 'id';
        }

        return Area::filter($filter)
            ->with([
                'translation' => fn($query) => $query->where('locale', $this->language),
            ])
            ->when(data_get($filter, 'area_id'), function ($query, $id) use ($sort) {
                $query->orderByRaw(DB::raw("FIELD(id, $id) $sort"));
            },
                fn($q) => $q->orderBy($column, $sort)
            )
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param Area $model
     * @return Area
     */
    public function show(Area $model): Area
    {
        

        return $model->load([
            'region.translation'  => fn($query) => $query->where('locale', $this->language),
            'country.translation' => fn($query) => $query->where('locale', $this->language),
            'city.translation'    => fn($query) => $query->where('locale', $this->language),
            'translation'         => fn($query) => $query->where('locale', $this->language),
            'translations',
        ]);
    }

}
