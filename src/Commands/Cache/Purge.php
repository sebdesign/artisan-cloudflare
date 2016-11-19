<?php

namespace Sebdesign\ArtisanCloudflare\Commands\Cache;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
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
      {--tag=* : One or more tags that should be removed from the cache.}';

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
     * @var \Illuminate\Support\Collection
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
        $this->zones = collect($zones);
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

        $parameters = $this->getParameters($zones);
        $results = $this->purge($parameters);

        $this->displayResults($parameters, $results);

        return $this->getExitCode($results);
    }

    /**
     * Get the paremeters for each zone.
     *
     * Use the config for each zone, unless options are passed in the command.
     *
     * @param  \Illuminate\Support\Collection $zones
     * @return \Illuminate\Support\Collection
     */
    private function getParameters(Collection $zones)
    {
        $defaults = collect([
            'files' => $this->option('file'),
            'tags' => $this->option('tag'),
        ])->filter();

        if (! $defaults->isEmpty()) {
            return $zones->fill($defaults);
        }

        return $zones->map(function ($zone) {
            return collect($zone)->filter()->only('files', 'tags');
        });
    }

    /**
     * Execute the purging operations and return each result.
     *
     * @param  \Illuminate\Support\Collection $parameters
     * @return \Illuminate\Support\Collection
     */
    private function purge(Collection $parameters)
    {
        return $parameters->map(function ($params) {
            if ($params->isEmpty()) {
                return ['purge_everything' => true];
            }

            return $params->toArray();
        })->map(function ($params, $identifier) {
            return $this->client->delete("zones/{$identifier}/purge_cache", $params);
        });
    }

    /**
     * Display a table with the results.
     *
     * @param  \Illuminate\Support\Collection $parameters
     * @param  \Illuminate\Support\Collection $results
     * @return void
     */
    private function displayResults(Collection $parameters, Collection $results)
    {
        $headers = ['Status', 'Zone', 'Files', 'Tags', 'Errors'];

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
        $identifiers = $parameters->keys();

        // Get the files as multiline strings
        $files = $parameters->pluck('files')
            ->map(function ($files) {
                return $this->formatItems($files);
            });

        // Get the tags as multiline strings
        $tags = $parameters->pluck('tags')
            ->map(function ($tags) {
                return $this->formatItems($tags);
            });

        // Get the errors as red multiline strings
        $errors = $results->pluck('errors')
            ->map(function (array $errors) {
                return $this->formatErrors($errors);
            })
            ->map(function (array $errors) {
                return $this->formatItems($errors);
            });

        $columns = collect([
            'status' => $emoji,
            'identifier' => $identifiers,
            'files' => $files,
            'tags' => $tags,
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
     * @param  array  $errors
     * @return array
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
     * @return \Illuminate\Support\Collection
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
            $zone => [],
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
