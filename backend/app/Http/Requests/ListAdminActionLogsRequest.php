<?php

namespace App\Http\Requests;

use App\Enums\AdminActionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAdminActionLogsRequest extends FormRequest
{
    public const MAX_PER_PAGE = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action'            => ['nullable', Rule::enum(AdminActionName::class)],
            'affected_admin_id' => ['nullable', 'integer', Rule::exists('patients', 'id')],
            'date_from'         => ['nullable', 'date'],
            'date_to'           => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page'          => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_PER_PAGE],
            'page'              => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.enum'                 => 'La acción indicada no es válida.',
            'affected_admin_id.integer'   => 'El administrador afectado no es válido.',
            'affected_admin_id.exists'    => 'El administrador afectado no existe.',
            'date_from.date'              => 'La fecha desde no es válida.',
            'date_to.date'                => 'La fecha hasta no es válida.',
            'date_to.after_or_equal'      => 'La fecha hasta no puede ser anterior a la fecha desde.',
            'per_page.integer'            => 'La cantidad por página debe ser un número entero.',
            'per_page.min'                => 'La cantidad por página debe ser al menos 1.',
            'per_page.max'                => 'La cantidad por página no puede superar ' . self::MAX_PER_PAGE . '.',
            'page.integer'                => 'La página debe ser un número entero.',
            'page.min'                    => 'La página debe ser al menos 1.',
        ];
    }
}
