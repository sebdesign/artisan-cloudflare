<?php

namespace Sebdesign\ArtisanCloudflare;

use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Logging\Log;

class Client
{
    /**
     * Base URI.
     */
    const BASE_URI = 'https://api.cloudflare.com/client/v4/';

    /**
     * @var \GuzzleHttp\ClientInterface
     */
    protected $client;

    /**
     * @var \Illuminate\Contracts\Logging\Log
     */
    protected $logger;

    /**
     * Constructor.
     *
     * @param \GuzzleHttp\ClientInterface    $client
     * @param \Illuminate\Contracts\Logging  $logger
     */
    public function __construct(ClientInterface $client, Log $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Delete all the given zones with their options.
     *
     * All the requests are asynchronous and sent concurrently.
     *
     * The promise waits until all the promises have been resolved or rejected
     * and returns the results of each request.
     *
     * @param  \Illuminate\Support\Collection $zones
     * @return \Illuminate\Support\Collection
     */
    public function purge(Collection $zones)
    {
        $promises = $zones->map(function ($options, $identifier) {
            return $this->client->deleteAsync("zones/{$identifier}/purge_cache", [
                \GuzzleHttp\RequestOptions::JSON => $options,
            ]);
        });

        return $this->settle($promises)->wait();
    }

    /**
     * Returns a promise that is fulfilled when all of the provided promises have
     * been fulfilled or rejected.
     *
     * The returned promise is fulfilled with a collection of results.
     *
     * @param Illuminate\Support\Collection $promises
     * @return GuzzleHttp\Promise\PromiseInterface
     */
    protected function settle(Collection $promises)
    {
        $results = collect();

        return Promise\each(
            $promises->toArray(),
            $this->onFulfilled($results),
            $this->onRejected($results)
        )->then(function () use (&$results) {
            return $results;
        });
    }

    /**
     * Put the body of the fulfilled promise into the results.
     *
     * @param  \Illuminate\Support\Collection $results
     * @return Closure
     */
    protected function onFulfilled(Collection &$results)
    {
        return function ($value, $identifier) use (&$results) {
            return $results->put($identifier, $this->getBody($value));
        };
    }

    /**
     * Handle the rejected promise and put the errors into the results.
     *
     * @param  \Illuminate\Support\Collection $results
     * @return Closure
     */
    protected function onRejected(Collection &$results)
    {
        return function ($reason, $identifier) use (&$results) {
            if ($reason instanceof ClientException) {
                return $results->put($identifier, $this->getBody($reason->getResponse()));
            }

            $this->logger->error($reason);

            return $results->put($identifier, $this->handleException($reason));
        };
    }

    /**
     * Transform a request exception into a response object.
     *
     * @param  \GuzzleHttp\Exception\RequestException $e
     * @return object
     */
    protected function handleException(RequestException $e)
    {
        if ($e->hasResponse()) {
            $message = (string) $e->getResponse()->getBody();
        } else {
            $message = $e->getMessage();
        }

        $response = [
            'success' => false,
            'errors' => [
                (object) [
                    'code' => $e->getCode(),
                    'message' => $message,
                ],
            ],
        ];

        return (object) $response;
    }

    /**
     * Get the response body.
     *
     * @param  \GuzzleHttp\Psr7\Response $response
     * @return object
     */
    protected function getBody(Response $response)
    {
        return json_decode($response->getBody(), false);
    }

    /**
     * Get the Guzzle client.
     *
     * @return \GuzzleHttp\Client
     */
    public function getClient()
    {
        return $this->client;
    }
}
