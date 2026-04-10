<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Evaluation;
use App\Models\Photo;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Photo>
 */
class PhotoFactory extends Factory
{
    protected $model = Photo::class;

    public function definition(): array
    {
        // Each photo gets a unique key — prevents hash collisions across factory calls
        $key = 'test/'.fake()->uuid().'/'.fake()->uuid().'/front_'.now()->format('YmdHis').'.jpg';
        $hash = hash_hmac('sha256', $key, config('app.key'));

        return [
            'tenant_id' => Tenant::factory(),
            'evaluation_id' => Evaluation::factory(),
            'type' => Photo::TYPE_FRONT,
            's3_key' => $key,           // encrypted by model cast
            's3_key_hash' => $hash,
            'quality_score' => 75,
            'analysis_status' => Photo::ANALYSIS_PENDING,
        ];
    }
}
