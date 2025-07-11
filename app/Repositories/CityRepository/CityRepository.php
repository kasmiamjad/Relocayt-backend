<?php
declare(strict_types=1);

namespace App\Repositories\CityRepository;

use App\Models\City;
use App\Models\Language;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Schema;

class CityRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return City::class;
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
            $column = Schema::hasColumn('cities', $column) ? $column : 'id';
        }

        return City::filter($filter)
            ->with([
                'area',
                'translation' => fn($query) => $query->where('locale', $this->language),
            ])
            ->when(data_get($filter, 'city_id'), function ($query, $id) use ($sort) {
                $query->orderByRaw(DB::raw("FIELD(id, $id) $sort"));
            },
                fn($q) => $q->orderBy($column, $sort)
            )
            ->paginate($filter['perPage'] ?? 10);
    }

    public function show(City $model): City
    {
        

        return $model->load([
            'region.translation' => fn($query) => $query->where('locale', $this->language),
            'country.translation' => fn($query) => $query->where('locale', $this->language),
            'translation' => fn($query) => $query->where('locale', $this->language),
            'translations',
        ]);
    }

}
