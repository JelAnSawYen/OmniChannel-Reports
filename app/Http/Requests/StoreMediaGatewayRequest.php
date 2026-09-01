<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
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
            'site_name'=>['required','string','max:255'],
            'site_code'=>['required','string','max:255','unique:media_gateways,site_code'],
            'ip_address'=>['required','ip','unique:media_gateways,ip_address'],
            'username'=>['required','string','max:255'],
            'database'=>['required','string','max:255'],
        ];
    }
}
