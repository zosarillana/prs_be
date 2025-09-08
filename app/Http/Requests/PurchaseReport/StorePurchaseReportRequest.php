<?php

namespace App\Http\Requests\PurchaseReport;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer',
            'series_no' => 'required|string|max:50',
            'po_no' => 'nullable|integer',
            'pr_purpose' => 'required|string|max:255',
            'department' => 'required|string|max:100',
            'date_submitted' => 'required|date',
            'date_needed' => 'required|date|after_or_equal:date_submitted',

            'quantity' => 'required|array',
            'unit' => 'required|array',
            'item_description' => 'required|array',
            'tag' => 'nullable|array',
            'item_status' => 'nullable|array',
            'pr_status' => 'nullable|string|max:100',
            'remarks' => 'nullable|array',
        ];

        
    }

  
}
