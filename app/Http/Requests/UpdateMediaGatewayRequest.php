<?php
namespace App\Http\Requests;

use App\Support\OperationCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMediaGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user && $user->hasPermission('media.edit'));
    }

    public function rules(): array
    {
        $gateway = $this->route('mediaGateway');
        $id = is_object($gateway) ? $gateway->id : $gateway;

        $rules = [
            'site_name' => $this->siteNameRules($gateway),
            'site_code' => ['required', 'string', 'max:255', Rule::unique('media_gateways', 'site_code')->ignore($id)],
            'ip_address' => ['required', $this->isGsm() ? 'ipv4' : 'ip', Rule::unique('media_gateways', 'ip_address')->ignore($id)],
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
        if (! $this->isGsm()) {
            return;
        }

        $canonical = OperationCatalog::canonicalLocationName((string) $this->input('site_name'));
        if ($canonical) {
            $this->merge(['site_name' => $canonical]);
        }
    }

    private function isGsm(): bool
    {
        return str_starts_with((string) $this->route()?->getName(), 'gsm-gateways');
    }

    /**
     * @return list<string|\Illuminate\Validation\Rules\In>
     */
    private function siteNameRules(mixed $gateway): array
    {
        if (! $this->isGsm()) {
            return ['required', 'string', 'max:255'];
        }

        $allowed = OperationCatalog::locationNames();
        $current = is_object($gateway) ? trim((string) $gateway->site_name) : '';
        if ($current !== '' && ! in_array($current, $allowed, true)) {
            $allowed[] = $current;
        }

        return ['required', 'string', Rule::in($allowed)];
    }
}
