<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case INTERN = 'intern';
    case SUPERVISOR = 'supervisor';
    case COORDINATOR = 'coordinator';

    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Administrator',
            self::INTERN => 'Intern',
            self::SUPERVISOR => 'Supervisor',
            self::COORDINATOR => 'OJT Coordinator',
        };
    }
}
