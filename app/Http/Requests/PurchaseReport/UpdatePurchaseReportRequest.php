<?php

namespace App\Http\Requests\PurchaseReport;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseReportRequest extends FormRequest
{
   public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'          => 'required|integer',
            'quantity'         => 'required|array',
            'unit'             => 'required|array',
            'item_description' => 'required|array',
            'tag'              => 'nullable|array',
            'remarks'          => 'nullable|array',
        ];
    }
}
