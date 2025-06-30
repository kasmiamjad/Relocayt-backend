<?php
declare(strict_types=1);

namespace App\Repositories\MasterDisabledTimeRepository;

use App\Models\Language;
use App\Models\MasterDisabledTime;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Schema;

class MasterDisabledTimeRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return MasterDisabledTime::class;
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function paginate(array $filter = []): LengthAwarePaginator
    {
        $column = $filter['column'] ?? 'id';

        if ($column !== 'id') {
            $column = Schema::hasColumn('master_disabled_times', $column) ? $column : 'id';
        }

        

        return MasterDisabledTime::filter($filter)
            ->with([
                'master',
                'translations',
                'translation' => fn($q) => $q
                    ->select(['id', 'disabled_time_id', 'locale', 'title'])
                    ->where('locale', $this->language)
            ])
            ->orderBy($column, $filter['sort'] ?? 'desc')
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param MasterDisabledTime $model
     * @return MasterDisabledTime
     */
    public function show(MasterDisabledTime $model): MasterDisabledTime
    {
        

        return $model->fresh([
            'master',
            'translations',
            'translation' => fn($q) => $q
                ->select(['id', 'disabled_time_id', 'locale', 'title'])
                ->where('locale', $this->language)
        ]);
    }

}
