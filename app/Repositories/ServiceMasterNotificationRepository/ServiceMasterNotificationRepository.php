<?php
declare(strict_types=1);

namespace App\Repositories\ServiceMasterNotificationRepository;

use Schema;
use App\Repositories\CoreRepository;
use App\Models\ServiceMasterNotification;
use Illuminate\Pagination\LengthAwarePaginator;

class ServiceMasterNotificationRepository extends CoreRepository
{

    protected function getModelClass(): string
    {
        return ServiceMasterNotification::class;
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function paginate(array $filter = []): LengthAwarePaginator
    {
        $column = $filter['column'] ?? 'id';

        if ($column !== 'id') {
            $column = Schema::hasColumn('service_master_notifications', $column) ? $column : 'id';
        }

        return $this->model()
            ->with([
                'serviceMaster.master',
                'translation' => fn($q) => $q->where('locale', $this->language)
            ])
            ->filter($filter)
            ->orderBy($column, $filter['sort'] ?? 'desc')
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param ServiceMasterNotification $serviceMasterNotification
     * @return ServiceMasterNotification
     */
    public function show(ServiceMasterNotification $serviceMasterNotification): ServiceMasterNotification
    {
        return $serviceMasterNotification
            ->load([
                'serviceMaster.master',
                'serviceMaster.service:id',
                'serviceMaster.service.translation' => fn($q) => $q->where('locale', $this->language),
                'translation' => fn($q) => $q->where('locale', $this->language),
                'translations'
            ]);
    }
}
