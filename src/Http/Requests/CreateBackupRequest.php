<?php

namespace Sarfraznawaz2005\BackupManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBackupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // Regex: no space
        return [
            'backupName' => ['nullable' ,'string', 'max: 25', 'alpha_dash']
        ];
    }
}
