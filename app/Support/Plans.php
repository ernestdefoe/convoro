<?php

namespace App\Support;

/** Managed-hosting plans — storage-tiered; members + bandwidth are unlimited. */
class Plans
{
    /** @return array<int, array> plans with display-ready fields */
    public static function all(): array
    {
        $out = [];
        foreach ((array) config('convoro.plans', []) as $id => $p) {
            $out[] = self::shape($id, $p);
        }

        return $out;
    }

    public static function find(?string $id): ?array
    {
        $plans = (array) config('convoro.plans', []);
        $id = $id && isset($plans[$id]) ? $id : array_key_first($plans);

        return $id ? self::shape($id, $plans[$id]) : null;
    }

    private static function shape(string $id, array $p): array
    {
        return [
            'id' => $id,
            'name' => $p['name'] ?? ucfirst($id),
            'priceCents' => (int) ($p['price_cents'] ?? 0),
            'price' => '$'.number_format(($p['price_cents'] ?? 0) / 100, ($p['price_cents'] ?? 0) % 100 ? 2 : 0),
            'storageGb' => (int) ($p['storage_gb'] ?? 0),
            'storageBytes' => (int) ($p['storage_gb'] ?? 0) * 1024 * 1024 * 1024,
            'blurb' => $p['blurb'] ?? '',
            // Highlighted on every plan — the differentiators.
            'features' => [
                'Unlimited members',
                'Unlimited bandwidth',
                ($p['storage_gb'] ?? 0).' GB storage',
                'Import from any forum',
                'Custom domain + HTTPS',
            ],
        ];
    }
}
