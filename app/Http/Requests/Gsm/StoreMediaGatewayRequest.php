<?php

namespace App\Http\Requests\Gsm;

use App\Support\OperationCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class StoreMediaGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user && $user->hasPermission('media.create'));
    }

    public function rules(): array
    {
        $rules = [
            'site_name' => $this->siteNameRules(),
            'site_code' => ['required', 'string', 'max:255', 'unique:media_gateways,site_code'],
            'ip_address' => ['required', $this->isGsm() ? 'ipv4' : 'ip', 'unique:media_gateways,ip_address'],
            'plan' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'string', 'max:50'],
            'network' => ['nullable', 'string', 'max:255'],
            'device_function' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->isGsm()) {
            $rules['hostname'] = ['required', 'string', 'max:255'];
            $rules['channel_count'] = ['required', 'integer', 'min:1', 'max:512'];
            $rules['device_function'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    protected function passedValidation(): void
    {
        $this->canonicalizeGsmSite();
    }

    private function isGsm(): bool
    {
        return str_starts_with((string) $this->route()?->getName(), 'gsm-gateways');
    }

    /**
     * @return list<string|In>
     */
    private function siteNameRules(): array
    {
        if (! $this->isGsm()) {
            return ['required', 'string', 'max:255'];
        }

        return ['required', 'string', Rule::in(OperationCatalog::locationNames())];
    }

    private function canonicalizeGsmSite(): void
    {
        if (! $this->isGsm()) {
            return;
        }

        $canonical = OperationCatalog::canonicalLocationName((string) $this->input('site_name'));
        if ($canonical) {
            $this->merge(['site_name' => $canonical]);
        }
    }
}
