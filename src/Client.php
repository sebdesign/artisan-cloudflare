<?php

namespace Sebdesign\ArtisanCloudflare;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Each;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class Client
{
    /**
     * Base URI.
     */
    const BASE_URI = 'https://api.cloudflare.com/client/v4/';

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Constructor.
     */
    public function __construct(GuzzleClient $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Delete all the given zones with their parameters.
     *
     * All the requests are asynchronous and sent concurrently.
     *
     * The promise waits until all the promises have been resolved or rejected
     * and returns the results of each request.
     *
     * @param  \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>  $zones
     * @return \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>
     */
    public function purge(Collection $zones): Collection
    {
        return $zones->map(function (Zone $zone, $identifier) {
            return $this->delete($identifier, $zone);
        })->pipe(function ($promises) {
            return $this->settle($promises);
        })->wait();
    }

    /**
     * Block the given IP address.
     *
     * @param  \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>  $zones
     * @return \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>
     */
    public function blockIP($zones): Collection
    {
        return $zones->map(function (Zone $zone, $identifier) {
            return $this->client->postAsync("zones/{$identifier}/firewall/access_rules/rules", [
                \GuzzleHttp\RequestOptions::JSON => $zone,
            ]);
        })->pipe(function ($promises) {
            return $this->settle($promises);
        })->wait();
    }

    protected function delete(string $identifier, Zone $zone): PromiseInterface
    {
        return $this->client->deleteAsync("zones/{$identifier}/purge_cache", [
            \GuzzleHttp\RequestOptions::JSON => $zone,
        ]);
    }

    /**
     * Returns a promise that is fulfilled when all of the provided promises have
     * been fulfilled or rejected.
     *
     * The returned promise is fulfilled with a collection of results.
     *
     * @param  \Illuminate\Support\Collection<string,\GuzzleHttp\Promise\PromiseInterface>  $promises
     */
    protected function settle(Collection $promises): PromiseInterface
    {
        $results = new Collection();

        return Each::of(
            $promises->getIterator(),
            $this->onFulfilled($results),
            $this->onRejected($results)
        )->then(function () use ($results) {
            return $results;
        });
    }

    /**
     * Put the body of the fulfilled promise into the results.
     *
     * @param  \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>  $results
     * @return \Closure
     */
    protected function onFulfilled(Collection $results)
    {
        /*
         * @param  \Psr\Http\Message\ResponseInterface $response
         * @param  string                              $identifier
         * @return \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>
         */
        return function ($response, $identifier) use ($results) {
            return $results->put($identifier, $this->getBody($response));
        };
    }

    /**
     * Handle the rejected promise and put the errors into the results.
     *
     * @param  \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>  $results
     * @return \Closure
     */
    protected function onRejected(Collection $results)
    {
        /*
         * @param  \GuzzleHttp\Exception\RequestException $reason
         * @param  string                                 $identifier
         * @return \Illuminate\Support\Collection<string,\Sebdesign\ArtisanCloudflare\Zone>
         */
        return function ($reason, $identifier) use ($results) {
            $this->logger->error($reason->getMessage(), [
                'zone' => $identifier,
                'exception' => $reason,
            ]);

            return $results->put($identifier, $this->handleException($reason));
        };
    }

    /**
     * Transform a request exception into a result object.
     */
    protected function handleException(RequestException $e): Zone
    {
        if ($e->hasResponse()) {
            /** @var \Psr\Http\Message\ResponseInterface $response */
            $response = $e->getResponse();

            if ($e instanceof ClientException) {
                return $this->getBody($response);
            }

            $message = (string) $response->getBody();
        } else {
            $message = $e->getMessage();
        }

        return new Zone([
            'success' => false,
            'errors' => [
                [
                    'code' => $e->getCode(),
                    'message' => $message,
                ],
            ],
        ]);
    }

    /**
     * Transform the response body into a result object.
     */
    protected function getBody(ResponseInterface $response): Zone
    {
        return new Zone(json_decode($response->getBody(), true));
    }

    /**
     * Get the Guzzle client.
     */
    public function getClient(): GuzzleClient
    {
        return $this->client;
    }
}
