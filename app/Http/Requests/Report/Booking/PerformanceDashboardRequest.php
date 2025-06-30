<?php

namespace App\Http\Requests\Report\Booking;

use App\Http\Requests\BaseRequest;

class PerformanceDashboardRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'date_from' => 'required|date_format:Y-m-d',
            'date_to'   => 'required|date_format:Y-m-d',
            'type'      => 'required|string|in:year,month,week,day',
            'shop_id'   => 'required|int|exists:shops,id',
            'master_id' => 'int|exists:users,id',
        ];
    }
}
