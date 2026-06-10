<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The store is intentionally empty by default — products are real items you
 * add in Admin → Store (with a price, package id, and uploaded download).
 * The marketing homepage + /store pull live from the products table, so they
 * show only what actually exists (and a "coming soon" state when empty).
 */
class StoreSeeder extends Seeder
{
    public function run(): void
    {
        // No seeded/placeholder products.
    }
}
