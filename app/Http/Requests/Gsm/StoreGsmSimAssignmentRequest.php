<?php

namespace App\Http\Requests\Gsm;

use App\Support\GsmSimInventory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGsmSimAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $permission = $this->isMethod('POST') ? 'media.create' : 'media.edit';

        return (bool) ($user && $user->hasPermission($permission) && $user->canMutateGateways());
    }

    public function rules(): array
    {
        return [
            'network' => ['required', 'string', Rule::in(GsmSimInventory::networks())],
            'sim_type' => ['required', 'in:globe,smart'],
            'sim_id' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = GsmSimInventory::typeForNetwork((string) $this->input('network'));
        if ($type) {
            $this->merge(['sim_type' => $type]);
        }
    }

    protected function passedValidation(): void
    {
        $network = GsmSimInventory::canonicalNetwork((string) $this->input('network'));
        $type = GsmSimInventory::typeForNetwork((string) $network);
        if ($network) {
            $this->merge(['network' => $network]);
        }
        if ($type) {
            $this->merge(['sim_type' => $type]);
        }
    }
}
