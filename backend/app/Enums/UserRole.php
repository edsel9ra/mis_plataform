<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case CompanyAdmin = 'company_admin';
    case Employee = 'employee';
    case Mentor = 'mentor';
    case Mentee = 'mentee';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => __('roles.super_admin'),
            self::Admin => __('roles.admin'),
            self::CompanyAdmin => __('roles.company_admin'),
            self::Employee => __('roles.employee'),
            self::Mentor => __('roles.mentor'),
            self::Mentee => __('roles.mentee'),
        };
    }
}
