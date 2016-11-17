<?php

namespace Sebdesign\ArtisanCloudflare;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
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
     * Make a DELETE request.
     *
     * @param  string $url
     * @param  array  $options
     * @return object
     */
    public function delete($url, array $options = [])
    {
        try {
            $response = $this->client->delete($url, [
                \GuzzleHttp\RequestOptions::JSON => $options,
            ]);
        } catch (ClientException $e) {
            $response = $e->getResponse();
        } catch (RequestException $e) {
            $this->logger->error($e);

            return $this->handleException($e);
        }

        return $this->getBody($response);
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
                ]
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
