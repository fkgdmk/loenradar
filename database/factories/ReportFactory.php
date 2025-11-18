<?php

namespace Database\Factories;

use App\Models\AreaOfResponsibility;
use App\Models\JobTitle;
use App\Models\Region;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Report>
 */
class ReportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Report::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jobTitle = JobTitle::inRandomOrder()->first();
        $region = Region::inRandomOrder()->first();
        
        if (!$jobTitle || !$region) {
            throw new \Exception('JobTitle or Region not found. Please run migrations first.');
        }

        return [
            'job_title_id' => $jobTitle->id,
            'sub_job_title' => fake()->optional()->jobTitle(),
            'experience' => fake()->optional()->numberBetween(0, 20),
            'area_of_responsibility_id' => fake()->optional()->randomElement([
                null,
                AreaOfResponsibility::inRandomOrder()->first()?->id,
            ]),
            'region_id' => $region->id,
            'user_id' => User::factory(),
            'lower_percentile' => fake()->optional()->randomFloat(2, 30000, 50000),
            'median' => fake()->optional()->randomFloat(2, 40000, 60000),
            'upper_percentile' => fake()->optional()->randomFloat(2, 50000, 80000),
            'conclusion' => fake()->optional()->paragraph(),
            'filters' => fake()->optional()->randomElement([
                null,
                [
                    'experience_min' => fake()->numberBetween(0, 5),
                    'experience_max' => fake()->numberBetween(5, 20),
                ],
            ]),
        ];
    }
}
