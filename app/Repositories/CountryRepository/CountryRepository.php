<?php
declare(strict_types=1);

namespace App\Repositories\CountryRepository;

use App\Models\Country;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Schema;

class CountryRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return Country::class;
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
            $column = Schema::hasColumn('countries', $column) ? $column : 'id';
        }

        return Country::filter($filter)
            ->with([
                'translation' => fn($query) => $query->where('locale', $this->language),
            ])
            ->withCount([
                'cities' => fn($q) => $q->when(data_get($filter, 'has_price'),  fn($q) => $q->whereHas('deliveryPrice'))
            ])
            ->when(data_get($filter, 'country_id'), function ($query, $id) use ($sort) {
                    $query->orderByRaw(DB::raw("FIELD(id, $id) $sort"));
                },
                fn($q) => $q->orderBy($column, $sort)
            )
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param Country $model
     * @return Country
     */
    public function show(Country $model): Country
    {
        return $model->load([
            'galleries',
            'region.translation' => fn($query) => $query
                ->where('locale', $this->language),
            'translation' => fn($query) => $query
                ->where('locale', $this->language),
            'translations',
            'city.translation' => fn($query) => $query
                ->where('locale', $this->language),
        ]);
    }

    /**
     * @param int $id
     * @param array $filter
     * @return Builder|Model|null
     */
    public function checkCountry(int $id, array $filter): Builder|Model|null
    {
        $city = data_get($filter, 'city');

        return Country::with([
            'city.translation' => fn($q) => $q
                ->where('title', 'like', "%$city%")
                ->where('locale', $this->language)
        ])
            ->whereHas('city.translation', function ($query) use ($city) {
                $query
                    ->where('title', 'like', "%$city%")
                    ->where('locale', $this->language);
            })
            ->firstWhere('id', $id);
    }
}
