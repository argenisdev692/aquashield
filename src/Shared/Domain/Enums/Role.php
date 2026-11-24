<?php

declare(strict_types=1);

namespace Shared\Domain\Enums;

/**
 * Role names that appear in authorization or policy decisions.
 *
 * The seeder owns the full role catalogue; these are the only names the
 * application checks in code today. A role that becomes an authorization
 * constant gets a case here, so `SUPER_ADMIN` stops being a scattered literal.
 */
enum Role: string
{
    case SuperAdmin = 'SUPER_ADMIN';
    case Admin = 'ADMIN';
}
