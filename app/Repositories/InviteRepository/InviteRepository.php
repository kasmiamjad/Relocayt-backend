<?php
declare(strict_types=1);

namespace App\Repositories\InviteRepository;

use Schema;
use App\Models\Invitation;
use App\Repositories\CoreRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InviteRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return Invitation::class;
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function paginate(array $filter): LengthAwarePaginator
    {
        $column = $filter['column'] ?? 'id';

        if ($column !== 'id') {
            $column = Schema::hasColumn('invitations', $column) ? $column : 'id';
        }

        return $this->model()
            ->filter($filter)
            ->with([
                'user.roles',
                'user' => fn($q) => $q->select('id', 'firstname', 'lastname', 'img'),
                'shop.translation' => fn ($q) => $q->where('locale', $this->language)
            ])
            ->orderBy($column, $filter['sort'] ?? 'desc')
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param Invitation $invitation
     * @return Invitation
     */
    public function show(Invitation $invitation): Invitation
    {
        return $invitation->loadMissing([
            'user.roles',
            'user' => fn ($q) => $q->select('id', 'firstname', 'lastname', 'img'),
            'shop.translation' => fn ($q) => $q->where('locale', $this->language)
        ]);
    }

}
