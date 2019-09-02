<?php

namespace Sebdesign\ArtisanCloudflare\Commands\Cache;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Sebdesign\ArtisanCloudflare\Zone;
use Sebdesign\ArtisanCloudflare\Client;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;

class Purge extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'cloudflare:cache:purge';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cloudflare:cache:purge {zone? : A zone identifier.}
      {--file=* : One or more files that should be removed from the cache.}
      {--tag=* : One or more tags that should be removed from the cache.}
      {--host=* : One or more hosts that should be removed from the cache.}';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $description = 'Purge files/tags from CloudFlare\'s cache.';

    /**
     * CloudFlare API client.
     *
     * @var \Sebdesign\ArtisanCloudflare\Client
     */
    private $client;

    /**
     * API item identifier tags.
     *
     * @var \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>
     */
    private $zones;

    /**
     * Purge constructor.
     *
     * @param array $zones
     */
    public function __construct(array $zones)
    {
        parent::__construct();

        $this->zones = Collection::make($zones)->map(function (array $zone) {
            return new Zone(array_filter($zone));
        });
    }

    /**
     * Execute the console command.
     *
     * @param \Sebdesign\ArtisanCloudflare\Client $client
     * @return int
     */
    public function handle(Client $client)
    {
        $this->client = $client;

        $zones = $this->getZones();

        if ($zones->isEmpty()) {
            $this->error('Please supply a valid zone identifier in the input argument or the cloudflare config.');

            return 1;
        }

        $zones = $this->applyParameters($zones);

        $results = $this->purge($zones);

        $this->displayResults($zones, $results);

        return $this->getExitCode($results);
    }

    /**
     * Apply the paremeters for each zone.
     *
     * Use the config for each zone, unless options are passed in the command.
     *
     * @param  \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone> $zones
     * @return \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>
     */
    private function applyParameters($zones)
    {
        $defaults = array_filter([
            'files' => $this->option('file'),
            'tags' => $this->option('tag'),
            'hosts' => $this->option('host'),
        ]);

        if (empty($defaults)) {
            return $zones;
        }

        return $zones->each(function (Zone $zone) use ($defaults) {
            $zone->replace($defaults);
        });
    }

    /**
     * Execute the purging operations and return each result.
     *
     * @param  \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone> $zones
     * @return \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>
     */
    private function purge($zones)
    {
        $results = $this->client->purge($zones);

        return $results->reorder($zones->keys());
    }

    /**
     * Display a table with the results.
     *
     * @param  \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone> $zones
     * @param  \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone> $results
     * @return void
     */
    private function displayResults($zones, $results)
    {
        $headers = ['Status', 'Zone', 'Files', 'Tags', 'Hosts', 'Errors'];

        $title = [
            new TableCell(
                'The following zones have been purged from CloudFlare.',
                ['colspan' => count($headers)]
            ),
        ];

        // Get the status emoji
        $emoji = $results->map(function (Zone $zone) {
            return $zone->get('success') ? '✅' : '❌';
        });

        // Get the zone identifiers
        $identifiers = $zones->keys();

        // Get the files as multiline strings
        $files = $zones->map(function (Zone $zone) {
            return $this->formatItems($zone->get('files', []));
        });

        // Get the tags as multiline strings
        $tags = $zones->map(function (Zone $zone) {
            return $this->formatItems($zone->get('tags', []));
        });

        // Get the hosts as multiline strings
        $hosts = $zones->map(function (Zone $zone) {
            return $this->formatItems($zone->get('hosts', []));
        });

        // Get the errors as red multiline strings
        $errors = $results->map(function (Zone $result) {
            return $this->formatErrors($result->get('errors', []));
        })->map(function (array $errors) {
            return $this->formatItems($errors);
        });

        $columns = Collection::make([
            'status' => $emoji,
            'identifier' => $identifiers,
            'files' => $files,
            'tags' => $tags,
            'hosts' => $hosts,
            'errors' => $errors,
        ]);

        $rows = $columns->_transpose()->insertBetween(new TableSeparator());

        $this->table([$title, $headers], $rows);
    }

    /**
     * Format an array into a multiline string.
     *
     * @param  array  $items
     * @return string
     */
    private function formatItems(array $items)
    {
        return implode("\n", $items);
    }

    /**
     * Format the errors.
     *
     * @param  array[] $errors
     * @return string[]
     */
    private function formatErrors(array $errors)
    {
        return array_map(function (array $error) {
            if (isset($error['code'])) {
                return "<fg=red>{$error['code']}: {$error['message']}</>";
            }

            return "<fg=red>{$error['message']}</>";
        }, $errors);
    }

    /**
     * Get the zone identifier from the input argument or the configuration.
     *
     * @return \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>
     */
    private function getZones()
    {
        if (! $zone = $this->argument('zone')) {
            return $this->zones;
        }

        $zones = $this->zones->only($zone);

        if ($zones->count()) {
            return $zones;
        }

        return new Collection([
            $zone => new Zone(),
        ]);
    }

    /**
     * Return 1 if all successes are false, otherwise return 0.
     *
     * @param  \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone> $results
     * @return int
     */
    private function getExitCode($results)
    {
        return (int) $results->filter(function (Zone $zone) {
            return $zone->get('success');
        })->isEmpty();
    }
}
