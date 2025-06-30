<?php
declare(strict_types=1);

namespace App\Repositories\PropertyRepository;

use App\Models\Language;
use App\Models\PropertyValue;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Schema;

class PropertyValueRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return PropertyValue::class;
    }

    public function index(array $filter): LengthAwarePaginator
    {
        $column = $filter['column'] ?? 'id';

        if ($column !== 'id') {
            $column = Schema::hasColumn('property_values', $column) ? $column : 'id';
        }

        

        return $this->model()
            ->with([
                'group.translation' => fn($q) => $q
                    ->where('locale', $this->language)
            ])
            ->when(isset($filter['active']), fn($q) => $q->where('active', $filter['active']))
            ->when(data_get($filter, 'group_id'), fn($q, $groupId) => $q->where('property_group_id', $groupId))
            ->orderBy($column, $filter['sort'] ?? 'desc')
            ->paginate($filter['perPage'] ?? 10);
    }

    public function show(int $id): Model|null
    {
        

        return $this->model()
            ->with([
                'galleries'         => fn($q) => $q->select('id', 'type', 'loadable_id', 'path', 'title', 'preview'),
                'group.translation' => fn($q) => $q
                    ->where('locale', $this->language)
            ])
            ->find($id);
    }

}
