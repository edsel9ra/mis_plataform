<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Session\RescheduleSessionRequest;

class RescheduleAdminSessionRequest extends RescheduleSessionRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }
}
