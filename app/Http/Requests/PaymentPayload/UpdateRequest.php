<?php
declare(strict_types=1);

namespace App\Http\Requests\PaymentPayload;

use App\Http\Requests\BaseRequest;
use Illuminate\Support\Facades\Cache;

class UpdateRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        
        return [
            'payload' => 'required|array',
            'payload.*' => ['required']
        ];
    }

}
