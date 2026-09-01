<?php
namespace App\Http\Requests;
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

        return [
            'site_name'=>['required','string','max:255'],
            'site_code'=>['required','string','max:255',Rule::unique('media_gateways','site_code')->ignore($id)],
            'ip_address'=>['required','ip',Rule::unique('media_gateways','ip_address')->ignore($id)],
            'username'=>['required','string','max:255'],
            'database'=>['required','string','max:255'],
        ];
    }
}
