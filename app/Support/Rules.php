<?php

namespace App\Support;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * One definition of what a valid record looks like.
 *
 * The same rows can now be written from two places — a handset through the API
 * and a person through the admin panel — so the rules live here rather than
 * inside either controller. If the two ever disagree, the panel and the app
 * would accept different data into the same table.
 */
class Rules
{
    /**
     * An owner account. Only the panel writes these — a handset can sign in but
     * never sign anyone up — so unlike the rest of this file there is no second
     * caller to keep in step. It lives here anyway, because the panel's two
     * forms (add and edit) have to agree with each other.
     *
     * On an edit the password is optional: a blank box means "leave it alone",
     * not "set an empty password".
     */
    public static function user(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'is_admin' => ['nullable', 'boolean'],
        ];
    }

    public static function business(?Business $business = null): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'name' => [$business ? 'sometimes' : 'required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'jurisdiction_text' => ['nullable', 'string', 'max:255'],
            'gst_number' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'bank_details' => ['nullable', 'string'],
            'upi_id' => ['nullable', 'string', 'max:255'],
            'bill_prefix' => ['nullable', 'string', 'max:16'],
            'fy_reset' => ['nullable', 'boolean'],
            'terms_text' => ['nullable', 'string'],
        ];
    }

    public static function customer(Business $business, ?Customer $customer = null): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'name' => [
                $customer ? 'sometimes' : 'required', 'string', 'max:255',
                Rule::unique('customers', 'name')
                    ->where(fn ($q) => $q->where('business_id', $business->id)->whereNull('deleted_at'))
                    ->ignore($customer?->id),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string'],
            'gst_number' => ['nullable', 'string', 'max:20'],
        ];
    }

    public static function item(?Item $item = null): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'name' => [$item ? 'sometimes' : 'required', 'string', 'max:255'],
            'default_rate' => ['nullable', 'numeric', 'min:0'],
            'hsn_code' => ['nullable', 'string', 'max:16'],
        ];
    }

    public static function invoice(bool $isUpdate = false): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'doc_type' => ['nullable', 'in:'.implode(',', [Invoice::TYPE_BILL, Invoice::TYPE_QUOTATION, Invoice::TYPE_CHALLAN])],
            'customer_name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'customer_uuid' => ['nullable', 'uuid'],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'photo_path' => ['nullable', 'string'],
            'converted_from_uuid' => ['nullable', 'uuid'],

            'discount_type' => ['nullable', 'in:'.implode(',', [Invoice::DISCOUNT_NONE, Invoice::DISCOUNT_PERCENT, Invoice::DISCOUNT_AMOUNT])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'round_off' => ['nullable', 'numeric'],

            'lines' => [$isUpdate ? 'sometimes' : 'required', 'array', 'min:1'],
            'lines.*.uuid' => ['nullable', 'uuid'],
            'lines.*.particulars' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric'],
            'lines.*.rate' => ['required', 'numeric'],
            'lines.*.position' => ['nullable', 'integer'],

            'taxes' => ['nullable', 'array'],
            'taxes.*.uuid' => ['nullable', 'uuid'],
            'taxes.*.label' => ['required', 'string', 'max:32'],
            'taxes.*.percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public static function payment(): array
    {
        return [
            'uuid' => ['nullable', 'uuid'],
            'date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'mode' => ['nullable', 'in:'.implode(',', Payment::MODES)],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
