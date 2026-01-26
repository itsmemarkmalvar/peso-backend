<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case SUPERVISOR = 'supervisor';
    case GIP = 'gip';
    case INTERN = 'intern';

    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Administrator',
            self::SUPERVISOR => 'Supervisor',
            self::GIP => 'GIP',
            self::INTERN => 'Intern',
        };
    }
}
