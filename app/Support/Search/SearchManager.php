<?php

namespace App\Support\Search;

/**
 * Resolves the configured search driver, falling back to the always-available
 * database (ngram) driver when the chosen engine isn't reachable. This is the
 * single entry point the SearchController uses for the text-search layer, so
 * swapping engines is a config change, not a code change.
 */
class SearchManager
{
    private ?SearchDriver $resolved = null;

    public function driver(): SearchDriver
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        // The convoro-typesense bundled extension (enabled + configured in its
        // admin settings page) takes over; otherwise the always-available
        // database driver. An env-set config driver still works headless.
        $extEnabled = \App\Support\ExtensionManager::isEnabled('convoro-typesense');
        $configTypesense = (string) config('convoro.search.driver', 'database') === 'typesense';

        if ($extEnabled || $configTypesense) {
            $ts = TypesenseSearchDriver::fromExtension();
            if ($ts->available()) {
                return $this->resolved = $ts;
            }
        }

        return $this->resolved = new DatabaseSearchDriver();
    }

    /**
     * @return int[] ranked, de-duplicated topic ids
     */
    public function topicIds(string $query, int $limit = 30): array
    {
        return $this->driver()->topicIds($query, $limit);
    }
}
