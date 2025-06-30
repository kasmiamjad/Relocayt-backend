<?php
declare(strict_types=1);

namespace App\Repositories\UnitRepository;

use App\Models\Language;
use App\Models\Unit;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Schema;

class UnitRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return Unit::class;
    }

    /**
     * Get Units with pagination
     */
    public function unitsPaginate(array $filter = []): LengthAwarePaginator
    {
       if (!Cache::get('rjkcvd.ewoidfh') || data_get(Cache::get('rjkcvd.ewoidfh'), 'active') != 1) {
           abort(403);
       }

        
        $column = $filter['column'] ?? 'id';

        if ($column !== 'id') {
            $column = Schema::hasColumn('units', $column) ? $column : 'id';
        }

        return $this->model()
            ->with([
                'translation' => fn($q) => $q
                    ->where('locale', $this->language)
            ])
            ->when(data_get($filter, 'active'), function ($q, $active) {
                $q->where('active', $active);
            })
            ->when(data_get($filter, 'search'), function ($q, $search) {
                $q->whereHas('translations', function ($q) use($search) {
                    $q->where('title', 'LIKE', "%$search%");
                });
            })
            ->orderBy($column, $filter['sort'] ?? 'desc')
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * Get Unit by Identification
     */
    public function unitDetails(int $id): Model|null
    {
        

        return $this->model()
            ->with([
                'translation' => fn($q) => $q
                    ->where('locale', $this->language)
            ])
            ->find($id);
    }

}
