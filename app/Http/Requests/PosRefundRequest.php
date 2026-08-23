<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PosRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order && ($this->user()?->can('refundPos', $order) ?? false);
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:1000']];
    }
}
