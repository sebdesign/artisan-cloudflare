<?php

namespace Sebdesign\ArtisanCloudflare\Commands\Cache;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Sebdesign\ArtisanCloudflare\Client;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\HttpFoundation\ParameterBag;
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
     * @var \Illuminate\Support\Collection<string, \Symfony\Component\HttpFoundation\ParameterBag>
     */
    private $zones;

    /**
     * Purge constructor.
     *
     * @param \Sebdesign\ArtisanCloudflare\Client $client
     * @param array                               $zones
     */
    public function __construct(Client $client, array $zones)
    {
        parent::__construct();

        $this->client = $client;

        $this->zones = collect($zones)->map(function ($zone) {
            return new ParameterBag(array_filter($zone));
        });
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
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
     * @param  \Illuminate\Support\Collection<string, \Symfony\Component\HttpFoundation\ParameterBag> $zones
     * @return \Illuminate\Support\Collection<string, \Symfony\Component\HttpFoundation\ParameterBag>
     */
    private function applyParameters(Collection $zones)
    {
        $defaults = array_filter([
            'files' => $this->option('file'),
            'tags' => $this->option('tag'),
            'hosts' => $this->option('host'),
        ]);

        if (empty($defaults)) {
            return $zones;
        }

        return $zones->each(function ($zone) use ($defaults) {
            $zone->replace($defaults);
        });
    }

    /**
     * Execute the purging operations and return each result.
     *
     * @param  \Illuminate\Support\Collection<string, \Symfony\Component\HttpFoundation\ParameterBag> $zones
     * @return \Illuminate\Support\Collection<string, object>
     */
    private function purge(Collection $zones)
    {
        $parameters = $zones->map(function ($zone) {
            if ($zone->count()) {
                return $zone->all();
            }

            return ['purge_everything' => true];
        });

        $results = $this->client->purge($parameters);

        return $results->reorder($zones->keys());
    }

    /**
     * Display a table with the results.
     *
     * @param  \Illuminate\Support\Collection<string, \Symfony\Component\HttpFoundation\ParameterBag> $zones
     * @param  \Illuminate\Support\Collection<string, object> $results
     * @return void
     */
    private function displayResults(Collection $zones, Collection $results)
    {
        $headers = ['Status', 'Zone', 'Files', 'Tags', 'Hosts', 'Errors'];

        $title = [
            new TableCell(
                'The following zones have been purged from CloudFlare.',
                ['colspan' => count($headers)]
            ),
        ];

        // Get the status emoji
        $emoji = $results->pluck('success')->map(function ($success) {
            return $success ? '✅' : '❌';
        });

        // Get the zone identifiers
        $identifiers = $zones->keys();

        // Get the files as multiline strings
        $files = $zones->map(function ($zones) {
            return $this->formatItems($zones->get('files'));
        });

        // Get the tags as multiline strings
        $tags = $zones->map(function ($zones) {
            return $this->formatItems($zones->get('tags'));
        });

        // Get the hosts as multiline strings
        $hosts = $zones->map(function ($zones) {
            return $this->formatItems($zones->get('hosts'));
        });

        // Get the errors as red multiline strings
        $errors = $results->map(function ($result) {
            return $this->formatErrors($result->errors);
        })->map(function ($errors) {
            return $this->formatItems($errors);
        });

        $columns = collect([
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
     * @param  array|null  $items
     * @return string
     */
    private function formatItems(array $items = null)
    {
        return implode("\n", (array) $items);
    }

    /**
     * Format the errors.
     *
     * @param  object[] $errors
     * @return string[]
     */
    private function formatErrors(array $errors)
    {
        return array_map(function ($error) {
            if ($error->code) {
                return "<fg=red>{$error->code}: {$error->message}</>";
            }

            return "<fg=red>{$error->message}</>";
        }, $errors);
    }

    /**
     * Get the zone identifier from the input argument or the configuration.
     *
     * @return \Illuminate\Support\Collection<string, \Symfony\Component\HttpFoundation\ParameterBag>
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

        return collect([
            $zone => new ParameterBag(),
        ]);
    }

    /**
     * Return 1 if all successes are false, otherwise return 0.
     *
     * @param  \Illuminate\Support\Collection $results
     * @return int
     */
    private function getExitCode(Collection $results)
    {
        return (int) $results->pluck('success')->filter()->isEmpty();
    }
}
