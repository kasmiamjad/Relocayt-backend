<?php
declare(strict_types=1);

namespace App\Repositories\CareerRepository;

use App\Models\Career;
use App\Models\Language;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Schema;

class CareerRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return Career::class;
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function paginate(array $filter): LengthAwarePaginator
    {
        
        $column = $filter['column'] ?? 'id';

        if ($column !== 'id') {
            $column = Schema::hasColumn('careers', $column) ? $column : 'id';
        }
        /** @var Career $model */
        $model = $this->model();

        return $model
            ->filter($filter)
            ->with([
                'translations',
                'translation' => fn($query) => $query->where('locale', $this->language),
                'category.translation' => fn($query) => $query->where('locale', $this->language),
            ])
            ->orderBy($column, $filter['sort'] ?? 'desc')
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param Career $model
     * @return Career|null
     */
    public function show(Career $model): Career|null
    {
        

        return $model->loadMissing([
            'translations',
            'translation' => fn($query) => $query->where('locale', $this->language),
            'category.translation' => fn($query) => $query->where('locale', $this->language),
        ]);
    }

    /**
     * @param int $id
     * @return Model|null
     */
    public function showById(int $id): ?Model
    {
        

        return Career::with([
            'translations',
            'translation' => fn($query) => $query->where('locale', $this->language),
            'category.translation' => fn($query) => $query->where('locale', $this->language),
        ])
            ->where('id', $id)
            ->first();

    }
}
