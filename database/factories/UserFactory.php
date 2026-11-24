<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Users\Enums\UserStatus;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->userName().'@gmail.com',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => UserStatus::Active,
            'invitation_sent_at' => null,
            'activated_at' => now(),
            'suspended_at' => null,
            'must_change_password' => false,
            'phone' => null,
            'date_of_birth' => null,
            'address' => null,
            'address_2' => null,
            'zip_code' => null,
            'city' => null,
            'state' => null,
            'country' => null,
            'gender' => null,
            'latitude' => null,
            'longitude' => null,
            'registered_by_id' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => Str::random(10),
            'terms_and_conditions' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Administrator-created staff member awaiting the signed invitation.
     */
    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'status' => UserStatus::Invited,
            'invitation_sent_at' => now(),
            'activated_at' => null,
            'password' => Hash::make(Str::random(64)),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }
}
