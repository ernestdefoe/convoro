<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'slug' => 'group-messages',
                'name' => 'Group Messages',
                'type' => 'extension',
                'package' => 'convoro-group-messages',
                'tagline' => 'Multi-participant group conversations on top of core DMs.',
                'description' => "Turn one-to-one messages into group chats. Add participants, name conversations, see who's read what, and react to messages.\n\n• Multi-participant threads\n• Read receipts & reactions\n• Realtime delivery",
                'price_cents' => 1900,
                'featured' => true,
            ],
            [
                'slug' => 'aurora-theme',
                'name' => 'Aurora Theme',
                'type' => 'theme',
                'package' => 'convoro-aurora',
                'tagline' => 'A bold, gradient-rich theme with animated hero widgets.',
                'description' => "Give your community a striking, modern look with Aurora — gradient palettes, animated hero widgets, and refined typography.",
                'price_cents' => 2900,
                'featured' => true,
            ],
            [
                'slug' => 'pro-analytics',
                'name' => 'Pro Analytics',
                'type' => 'extension',
                'package' => 'convoro-analytics',
                'tagline' => 'Deep engagement dashboards for admins.',
                'description' => "Understand your community with cohort retention, top-content reports, and member growth dashboards.",
                'price_cents' => 3900,
                'featured' => false,
            ],
        ];

        foreach ($products as $p) {
            Product::updateOrCreate(['slug' => $p['slug']], $p);
        }
    }
}
