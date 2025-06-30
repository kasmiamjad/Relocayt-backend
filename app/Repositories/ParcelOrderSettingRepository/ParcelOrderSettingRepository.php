<?php
declare(strict_types=1);

namespace App\Repositories\ParcelOrderSettingRepository;

use App\Models\Language;
use App\Models\ParcelOrderSetting;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\Paginator;
use Schema;

class ParcelOrderSettingRepository extends CoreRepository
{
    /**
     * @return string
     */
    protected function getModelClass(): string
    {
        return ParcelOrderSetting::class;
    }

    /**
     * @param array $filter
     * @return Paginator
     */
    public function restPaginate(array $filter = []): Paginator
    {
        /** @var ParcelOrderSetting $model */
        $model  = $this->model();
        

        return $model
            ->filter($filter)
            ->with([
                'parcelOptions.translation' => fn($query) => $query->where('locale', $this->language)
            ])
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param array $filter
     * @return Paginator
     */
    public function paginate(array $filter = []): Paginator
    {
        /** @var ParcelOrderSetting $model */
        $model = $this->model();

        return $model
            ->filter($filter)
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param ParcelOrderSetting $parcelOrderSetting
     * @return ParcelOrderSetting
     */
    public function show(ParcelOrderSetting $parcelOrderSetting): ParcelOrderSetting
    {
        

        return $parcelOrderSetting
            ->loadMissing([
                'parcelOptions.translation' => fn($query) => $query->where('locale', $this->language)
            ]);
    }

    /**
     * @param int $id
     * @return ParcelOrderSetting|null
     */
    public function showById(int $id): ?ParcelOrderSetting
    {
        $parcelOrderSetting = ParcelOrderSetting::find($id);

        

        return $parcelOrderSetting
            ?->loadMissing([
                'parcelOptions.translation' => fn($query) => $query->where('locale', $this->language)
            ]);
    }
}
