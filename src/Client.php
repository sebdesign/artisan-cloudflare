<?php

namespace Sebdesign\ArtisanCloudflare;

use GuzzleHttp\Promise;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Collection;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

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
     *
     * @param \GuzzleHttp\Client       $client
     * @param \Psr\Log\LoggerInterface $logger
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
     * @param  \Illuminate\Support\Collection|array[]  $parameters
     * @return \Illuminate\Support\Collection|object[]
     */
    public function purge(Collection $parameters)
    {
        $promises = $parameters->map(function ($parameters, $identifier) {
            return $this->client->deleteAsync("zones/{$identifier}/purge_cache", [
                \GuzzleHttp\RequestOptions::JSON => $parameters,
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
     * @param  \Illuminate\Support\Collection|\GuzzleHttp\Promise\PromiseInterface[] $promises
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    protected function settle(Collection $promises)
    {
        $results = collect();

        return Promise\each(
            $promises->toArray(),
            $this->onFulfilled($results),
            $this->onRejected($results)
        )->then(function () use ($results) {
            return $results;
        });
    }

    /**
     * Put the body of the fulfilled promise into the results.
     *
     * @param  \Illuminate\Support\Collection|object[] $results
     * @return \Closure
     */
    protected function onFulfilled(Collection $results)
    {
        return function ($response, $identifier) use ($results) {
            return $results->put($identifier, $this->getBody($response));
        };
    }

    /**
     * Handle the rejected promise and put the errors into the results.
     *
     * @param  \Illuminate\Support\Collection|object[] $results
     * @return \Closure
     */
    protected function onRejected(Collection $results)
    {
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
     *
     * @param  \GuzzleHttp\Exception\RequestException $e
     * @return object
     */
    protected function handleException(RequestException $e)
    {
        if ($e instanceof ClientException) {
            return $this->getBody($e->getResponse());
        }

        if ($e->hasResponse()) {
            $message = (string) $e->getResponse()->getBody();
        } else {
            $message = $e->getMessage();
        }

        $result = [
            'success' => false,
            'errors' => [
                (object) [
                    'code' => $e->getCode(),
                    'message' => $message,
                ],
            ],
        ];

        return (object) $result;
    }

    /**
     * Transform the response body into a result object.
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
     * @return \GuzzleHttp\ClientInterface
     */
    public function getClient()
    {
        return $this->client;
    }
}
