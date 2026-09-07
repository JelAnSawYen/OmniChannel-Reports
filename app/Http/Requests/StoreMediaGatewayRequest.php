<?php
namespace App\Http\Requests;

use App\Support\OperationCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user && $user->hasPermission('media.create'));
    }

    public function rules(): array
    {
        return [
            'site_name' => $this->siteNameRules(),
            'site_code' => ['required', 'string', 'max:255', 'unique:media_gateways,site_code'],
            'ip_address' => ['required', 'ip', 'unique:media_gateways,ip_address'],
            'plan' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'string', 'max:50'],
            'network' => ['nullable', 'string', 'max:255'],
            'device_function' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function passedValidation(): void
    {
        $this->canonicalizeGsmSite();
    }

    /**
     * @return list<string|\Illuminate\Validation\Rules\In>
     */
    private function siteNameRules(): array
    {
        if (! str_starts_with((string) $this->route()?->getName(), 'gsm-gateways')) {
            return ['required', 'string', 'max:255'];
        }

        return ['required', 'string', Rule::in(OperationCatalog::locationNames())];
    }

    private function canonicalizeGsmSite(): void
    {
        if (! str_starts_with((string) $this->route()?->getName(), 'gsm-gateways')) {
            return;
        }

        $canonical = OperationCatalog::canonicalLocationName((string) $this->input('site_name'));
        if ($canonical) {
            $this->merge(['site_name' => $canonical]);
        }
    }
}
