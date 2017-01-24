<?php

namespace Sebdesign\ArtisanCloudflare\Test;

use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Client as GuzzleClient;
use Sebdesign\ArtisanCloudflare\Client;
use GuzzleHttp\Exception\RequestException;

trait GuzzleHelpers
{
    /**
     * Transactions container.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $transactions;

    /**
     * @var \GuzzleHttp\Handler\MockHandler
     */
    protected $handler;

    /**
     * Assert the request URI contains the given path.
     *
     * @param  array  $transaction
     * @param  string $path
     * @return self
     */
    protected function seeRequestContainsPath(array $transaction, $path)
    {
        $this->assertContains($path, $transaction['request']->getUri()->getPath());

        return $this;
    }

    /**
     * Assert the request has the given JSON body.
     *
     * @param  array  $transaction
     * @param  array  $body
     * @return self
     */
    protected function seeRequestWithBody(array $transaction, array $body)
    {
        $this->assertEquals(json_encode($body), (string) $transaction['request']->getBody());

        return $this;
    }

    /**
     * Mock the Guzzle client.
     *
     * @return void
     */
    protected function mockClient()
    {
        // Attach a mock handler to the handler stack.
        $this->handler = new MockHandler();
        $stack = HandlerStack::create($this->handler);

        // Attach the transaction history to the handler stack.
        $this->transactions = collect();
        $stack->push(Middleware::history($this->transactions));

        // Initialize the mocked guzzle client
        $guzzle = new GuzzleClient([
            'handler' => $stack,
            'base_uri' => Client::BASE_URI,
            \GuzzleHttp\RequestOptions::HEADERS => [
                'X-Auth-Key' => $this->app['config']['cloudflare.key'],
                'X-Auth-Email' => $this->app['config']['cloudflare.email'],
            ],
        ]);

        // Register the service provider
        $this->registerServiceProvider();

        // Bind a new client with the mocked Guzzle client.
        $this->instance(Client::class, new Client($guzzle, $this->app['log']));
    }

    protected function mockSuccessResponse()
    {
        return $this->mockResponse(200, [], [
            'success' => true,
            'errors' => [],
        ]);
    }

    protected function mockClientErrorResponse(array $errors)
    {
        return $this->mockResponse(400, [], [
            'success' => false,
            'errors' => $errors,
        ]);
    }

    protected function mockRequestException($message, $url)
    {
        $request = new Request('DELETE', $url);

        return $this->handler->append(new RequestException($message, $request));
    }

    protected function mockServerErrorResponse($message)
    {
        return $this->handler->append(new Response(500, [], $message));
    }

    protected function mockResponse($status, array $headers, array $body)
    {
        $this->handler->append(new Response($status, $headers, json_encode($body)));
    }
}
