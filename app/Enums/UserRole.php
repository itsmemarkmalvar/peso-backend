<?php

namespace App\Enums;

/**
 * User roles for the PESO OJT Attendance System.
 *
 * GIP (Government Internship Program) is functionally identical to INTERN:
 * same capabilities, restrictions, attendance flow, and data access (own only).
 * Use HasRoles::isInternOrGip() for any check that applies to both roles.
 */
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
