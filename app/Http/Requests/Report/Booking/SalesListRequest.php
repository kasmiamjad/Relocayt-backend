<?php

namespace App\Http\Requests\Report\Booking;

use App\Http\Requests\BaseRequest;

class SalesListRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $rules = (new SalesSummaryRequest)->rules();

        unset($rules['type']);
        unset($rules['column']);

        $rules['perPage'] = 'int';
        $rules['column']  = 'string';

        return $rules;
    }
}
