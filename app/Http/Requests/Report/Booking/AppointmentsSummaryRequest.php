<?php

namespace App\Http\Requests\Report\Booking;

use App\Http\Requests\BaseRequest;
use App\Models\Transaction;
use Illuminate\Validation\Rule;

class AppointmentsSummaryRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'date_from'   => 'required|date_format:Y-m-d',
            'date_to'     => 'required|date_format:Y-m-d',
            'shop_id'     => 'int|exists:shops,id',
            'shop_ids'    => 'array',
            'shop_ids.*'  => 'int|exists:shops,id',
            'statuses'    => 'array',
            'lang'        => 'string|exists:languages,locale',
            'statuses.*'  => ['string', Rule::in(Transaction::STATUSES)],
            'perPage'     => 'int',
        ];
    }
}
