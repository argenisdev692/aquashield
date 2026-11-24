<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LeadAppointment\Domain\Enums\LeadSource;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\Lead;

/**
 * @extends Factory<Lead>
 */
final class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company_name' => null,
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+1'.fake()->numerify('##########'),
            'address' => fake()->streetAddress(),
            'address_2' => null,
            'city' => 'Houston',
            'state' => 'TX',
            'zip_code' => fake()->numerify('770##'),
            'country' => 'US',
            'region' => Region::Houston->value,
            'source' => LeadSource::Website->value,
            'status' => LeadStatus::New->value,
            'is_emergency' => false,
            'preferred_language' => 'en',
            'is_spam' => false,
            'spam_score' => 0,
            'sms_consent' => false,
        ];
    }

    public function forRegion(Region $region): self
    {
        return $this->state(fn (): array => [
            'region' => $region->value,
            'state' => $region->state(),
            'city' => $region->label(),
        ]);
    }

    public function emergency(): self
    {
        return $this->state(fn (): array => ['is_emergency' => true]);
    }
}
