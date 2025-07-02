<?php

namespace App\Repositories\PaymentToPartnerRepository;

use App\Models\PaymentToPartner;
use App\Repositories\CoreRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Schema;

class PaymentToPartnerRepository extends CoreRepository
{
    protected function getModelClass(): string
    {
        return PaymentToPartner::class;
    }

    /**
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function paginate(array $filter): LengthAwarePaginator
    {
        
        $column = $filter['column'] ?? 'id';

        if ($column !== 'id') {
            $column = Schema::hasColumn('payment_to_partners', $column) ? $column : 'id';
        }

        return $this->model()
            ->filter($filter)
            ->with([
                'user',
                'model',
                'transaction.paymentSystem'
            ])
            ->orderBy($column, $filter['sort'] ?? 'desc')
            ->paginate($filter['perPage'] ?? 10);
    }

    /**
     * @param int $id
     * @return PaymentToPartner|null
     */
    public function show(int $id): ?PaymentToPartner
    {
       

        return $this->model()
            ->with([
                'user',
                'model',
                'transaction.paymentSystem'
            ])
            ->find($id);
    }
}
