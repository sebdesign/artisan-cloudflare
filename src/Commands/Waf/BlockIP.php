<?php

namespace Sebdesign\ArtisanCloudflare\Commands\Waf;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Sebdesign\ArtisanCloudflare\Client;
use Sebdesign\ArtisanCloudflare\Zone;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;

class BlockIP extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'cloudflare:waf:block-ip';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cloudflare:waf:block-ip {ip : The IP address to block.} {zone? : A zone identifier.} {notes? : Notes that will be attached to the rule.}';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $description = 'Block an IP address in the CloudFlare WAF.';

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
     * @param  array  $zones
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
     * @param  \Sebdesign\ArtisanCloudflare\Client  $client
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

        $ip = $this->argument('ip');

        $target = $this->isIPv4($ip) ? 'ip' : ($this->isIPv6($ip) ? 'ip6' : null);

        if (!$target) {
            $this->error('Please supply a valid IP address.');

            return 1;
        }

        $zones = $this->applyParameters($zones, $target);

        $results = $this->block($zones);

        $this->displayResults($zones, $results);

        return $this->getExitCode($results);
    }

    /**
     * Apply the paremeters for each zone.
     *
     * Use the config for each zone, unless options are passed in the command.
     *
     * @param  \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>  $zones
     * @param  string  $target
     * @return \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>
     */
    private function applyParameters($zones, $target): Collection
    {
        $parameters = [
            'mode' => 'block',
            'configuration' => [
                'target' => $target,
                'value' => $this->argument('ip'),
            ],
            'notes' => $this->argument('notes') ?? 'Blocked by artisan command.',
        ];

        return $zones->each(function (Zone $zone) use ($parameters) {
            $zone->replace($parameters);
        });
    }

    /**
     * Block the given IP address.
     *
     * @param  \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>  $zones
     */
    private function block($zones): Collection
    {
        return $this->client->blockIP($zones);
    }

    /**
     * Display a table with the results.
     *
     * @param  \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>  $zones
     * @param  \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>  $results
     * @return void
     */
    private function displayResults($zones, $results): void
    {
        $headers = ['Status', 'Zone', 'IP', 'Errors'];

        $title = [
            new TableCell(
                'CloudFlare WAF: Block IP',
                ['colspan' => count($headers)]
            ),
        ];

        // Get the status emoji
        $emoji = $results->map(function (Zone $zone) {
            return $zone->get('success') ? '✅' : '❌';
        });

        // Get the zone identifiers
        $identifiers = $zones->keys();

        // Get the ip as multiline strings
        $ip = $zones->map(function (Zone $zone) {
            return $this->formatItems([$zone->get('configuration', [])['value']]);
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
            'ip' => $ip,
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
    private function formatItems(array $items): string
    {
        return implode("\n", $items);
    }

    /**
     * Format the errors.
     *
     * @param  array[]  $errors
     * @return string[]
     */
    private function formatErrors(array $errors): array
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
    private function getZones(): Collection
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
     * @param  \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>  $results
     * @return int
     */
    private function getExitCode($results): int
    {
        return (int) $results->filter(function (Zone $zone) {
            return $zone->get('success');
        })->isEmpty();
    }

    /**
     * Check if the given IP address is IPv4.
     * @param  string  $ip
     * @return bool
     */
    private function isIPv4($ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    /**
     * Check if the given IP address is IPv6.
     * @param  string  $ip
     * @return bool
     */
    private function isIPv6($ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }
}
