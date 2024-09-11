<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecoverAddressRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }
    public function rules()
    {
        return [
            'message' => 'required|string',
            'address' => 'required|string|starts_with:0x',
            'signature' => 'required|string|starts_with:0x',

        ];
    }
    protected function prepareForValidation()
    {
        $this->mergeIfMissing([
            'message' => 'Default message',
            'address' => '0x0000000000000000000000000000000000000000',
            'signature' => '0x0000000000000000000000000000000000000000',
        ]);
    }
}
