<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case INTERN = 'intern';
    case COORDINATOR = 'coordinator';

    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Administrator',
            self::INTERN => 'Intern',
            self::COORDINATOR => 'OJT Coordinator / Supervisor',
        };
    }
}
