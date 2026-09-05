<?php

namespace Database\Factories;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Dashboard> */
class DashboardFactory extends Factory
{
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        $manifest = [
            'id' => $slug,
            'version' => '1.0.0',
            'title' => 'Dashboard '.$slug,
            'params' => [
                ['id' => 'meta', 'label' => 'Meta', 'type' => 'number', 'default' => 100, 'min' => 0, 'max' => 1000],
                ['id' => 'activo', 'label' => 'Activo', 'type' => 'boolean', 'default' => true],
            ],
        ];

        $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [
            'slug' => $slug,
            'title' => $manifest['title'],
            'version' => $manifest['version'],
            'html' => "<!doctype html><html><head><script type=\"application/json\" id=\"dashboard-manifest\">{$json}</script></head><body><h1>{$manifest['title']}</h1></body></html>",
            'manifest' => $manifest,
            'is_published' => true,
            'created_by' => User::factory()->superAdmin(),
        ];
    }

    public function withParams(array $params): static
    {
        return $this->state(function (array $attributes) use ($params) {
            $manifest = $attributes['manifest'];
            $manifest['params'] = $params;
            $json = json_encode($manifest, JSON_UNESCAPED_UNICODE);

            return [
                'manifest' => $manifest,
                'html' => "<!doctype html><html><head><script type=\"application/json\" id=\"dashboard-manifest\">{$json}</script></head><body></body></html>",
            ];
        });
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
