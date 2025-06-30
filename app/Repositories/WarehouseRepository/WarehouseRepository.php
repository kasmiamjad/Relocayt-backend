<?php
declare(strict_types=1);

namespace App\Repositories\WarehouseRepository;

use App\Models\Language;
use App\Models\Warehouse;
use App\Repositories\CoreRepository;
use App\Traits\ByLocation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WarehouseRepository extends CoreRepository
{
    use ByLocation;

    protected function getModelClass(): string
    {
        return Warehouse::class;
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function paginate(array $filter = []): LengthAwarePaginator
    {
        

        return Warehouse::filter($filter)
            ->with([
                'translation' => fn($query) => $query->where('locale', $this->language),
                'workingDays',
                'closedDates',
            ] + $this->getWith())
            ->whereHas(
                'translation',
                fn($query) => $query->where('locale', $this->language)
            )
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param Warehouse $warehouse
     * @return Warehouse
     */
    public function show(Warehouse $warehouse): Warehouse
    {
        return $this->loadShow($warehouse);
    }

    /**
     * @param int $id
     * @return Warehouse|null
     */
    public function showById(int $id): ?Warehouse
    {
        $model = Warehouse::find($id);

        if (!$model) {
            return null;
        }

        return $this->loadShow($model);
    }

    private function loadShow(Warehouse $model): Warehouse
    {
        

        return $model->loadMissing([
            'galleries',
            'workingDays',
            'closedDates',
            'translation' => fn($query) => $query->where('locale', $this->language),
            'translations'
        ] + $this->getWith());
    }
}
