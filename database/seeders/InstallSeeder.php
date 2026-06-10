<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Minimal, production-safe base content for a fresh install — a few starter
 * categories and nothing else (no demo users, no dev admin; the installer
 * creates the real admin from the setup form).
 */
class InstallSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Announcements', 'icon' => 'fa-solid fa-bullhorn', 'color' => '#6366f1', 'description' => 'News and updates from the team.'],
            ['name' => 'General', 'icon' => 'fa-solid fa-comments', 'color' => '#2563eb', 'description' => 'General discussion.'],
            ['name' => 'Support', 'icon' => 'fa-solid fa-circle-question', 'color' => '#e8830c', 'description' => 'Questions and help.'],
        ];

        foreach ($categories as $i => $c) {
            Category::firstOrCreate(
                ['slug' => Str::slug($c['name'])],
                $c + ['position' => $i],
            );
        }
    }
}
