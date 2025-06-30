<?php
declare(strict_types=1);

namespace App\Repositories\MemberShipRepository;

use App\Models\MemberShip;
use App\Models\Language;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Schema;

class MemberShipRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return MemberShip::class;
    }

    /**
     * @return array
     */
    private function getWith(): array
    {
        

        return [
            'galleries',
            'memberShipServices.service:id,slug',
            'memberShipServices.service.translation' => fn($query) => $query
                ->where('locale', $this->language),
            'translation' => fn($query) => $query
                ->where('locale', $this->language),
            'shop:id,uuid,slug,logo_img',
            'shop.translation' => fn($query) => $query
                ->where('locale', $this->language),
            'translations',
        ];
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function paginate(array $filter = []): LengthAwarePaginator
    {
        

        $column = $filter['column'] ?? 'id';

        if ($column !== 'id') {
            $column = Schema::hasColumn('member_ships', $column) ? $column : 'id';
        }

        return MemberShip::filter($filter)
            ->withCount('memberShipServices')
            ->with([
                'translation' => fn($query) => $query
                    ->where('locale', $this->language),
                'shop:id,uuid,slug,logo_img',
                'shop.translation' => fn($query) => $query
                    ->where('locale', $this->language),
            ])
            ->orderBy($column, $filter['sort'] ?? 'desc')
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param MemberShip $memberShip
     * @return MemberShip
     */
    public function show(MemberShip $memberShip): MemberShip
    {
        return $memberShip->fresh($this->getWith());
    }

    /**
     * @param int $id
     * @param int|null $shopId
     * @return Model|null
     */
    public function showById(int $id, ?int $shopId = null): ?Model
    {
        return $this->model()->with($this->getWith())->where(['id' => $id, 'shop_id' => $shopId])->first();
    }

}
