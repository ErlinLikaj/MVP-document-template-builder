<?php

namespace Database\Factories;

use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'structure' => [
                'version' => 1,
                'sections' => [
                    [
                        'id' => (string) Str::uuid(),
                        'type' => 'header',
                        'grid' => ['colStart' => 1, 'colSpan' => 12],
                        'props' => [
                            'title' => fake()->sentence(4),
                            'subtitle' => fake()->sentence(6),
                            'align' => 'left',
                        ],
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'type' => 'text',
                        'grid' => ['colStart' => 1, 'colSpan' => 12],
                        'props' => [
                            'content' => fake()->paragraph(),
                            'align' => 'left',
                        ],
                    ],
                ],
            ],
            'settings' => [
                'pageSize' => 'A4',
            ],
        ];
    }
}
