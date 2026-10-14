<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin' && $this->user()?->is_active;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $receipt = is_string($this->input('creation_token')) && Str::isUuid($this->input('creation_token')) ? DB::table('account_creation_receipts')
            ->where('id', $this->input('creation_token'))->where('admin_id', $this->user()->id)->first() : null;
        $emailRule = Rule::unique(User::class);
        if ($receipt) {
            $emailRule->ignore($receipt->user_id);
        }

        return [
            'creation_token' => ['nullable', 'uuid'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', $emailRule],
            'role' => ['required', Rule::in(array_diff(array_keys(User::ROLE_LABELS), ['seeker']))],
            'account_status' => ['required', Rule::in(['active', 'pending'])],
            'password' => ['required', 'confirmed', Password::defaults()],
            'password_confirmation' => ['required'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }
}
