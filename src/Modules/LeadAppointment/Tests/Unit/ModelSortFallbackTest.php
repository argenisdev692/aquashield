<?php

declare(strict_types=1);

use Modules\LeadAppointment\Application\Dtos\AppointmentFilterData;
use Modules\LeadAppointment\Application\Dtos\LeadFilterData;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Appointment;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Lead;
use Tests\TestCase;

uses(TestCase::class);

/*
 * The controllers validate `sort_field` with an `in:` rule, so these DTOs cannot
 * carry an arbitrary column through HTTP. The model scopes still re-check the
 * whitelist before `orderBy`: a direct DTO construction (a job, a console
 * command, a future caller) must not turn the sort into a SQL sink.
 */
it('falls back to the safe lead sort column for an unvalidated DTO', function (): void {
    $filters = new LeadFilterData(sortField: 'id; DROP TABLE leads');

    $sql = Lead::query()->applyFilters($filters)->toSql();

    expect($sql)->toContain('order by "created_at"');
});

it('falls back to the safe appointment sort column for an unvalidated DTO', function (): void {
    $filters = new AppointmentFilterData(sortField: 'id; DROP TABLE appointments');

    $sql = Appointment::query()->applyFilters($filters)->toSql();

    expect($sql)->toContain('order by "starts_at"');
});
