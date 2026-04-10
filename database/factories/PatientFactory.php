<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        $name = fake()->name();
        $email = fake()->unique()->safeEmail();

        return [
            'tenant_id' => Tenant::factory(),
            // Eloquent will encrypt these via the 'encrypted' cast
            'name_encrypted' => $name,
            'email_encrypted' => $email,
            'phone_encrypted' => fake()->phoneNumber(),
            'name_hash' => hash_hmac('sha256', mb_strtolower($name), config('app.key')),
            'email_hash' => hash_hmac('sha256', mb_strtolower($email), config('app.key')),
            'created_via' => 'widget',
        ];
    }
}
