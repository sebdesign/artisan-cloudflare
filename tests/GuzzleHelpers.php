<?php

namespace Sebdesign\ArtisanCloudflare\Test;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Constraint\StringContains;
use Sebdesign\ArtisanCloudflare\Client;

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
     */
    protected function seeRequestContainsPath(array $transaction, string $path): self
    {
        $constraint = new StringContains($path, false);

        $this->assertThat($transaction['request']->getUri()->getPath(), $constraint);

        return $this;
    }

    /**
     * Assert the request has the given JSON body.
     */
    protected function seeRequestWithBody(array $transaction, array $body): self
    {
        $this->assertEquals(json_encode($body), (string) $transaction['request']->getBody());

        return $this;
    }

    /**
     * Mock the Guzzle client.
     */
    protected function mockClient(): void
    {
        // Attach a mock handler to the handler stack.
        $this->handler = new MockHandler();
        $stack = HandlerStack::create($this->handler);

        // Attach the transaction history to the handler stack.
        $this->transactions = Collection::make();
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

    protected function mockSuccessResponse(): void
    {
        $this->mockResponse(200, [], [
            'success' => true,
            'errors' => [],
        ]);
    }

    protected function mockClientErrorResponse(array $errors): void
    {
        $this->mockResponse(400, [], [
            'success' => false,
            'errors' => $errors,
        ]);
    }

    protected function mockRequestException(string $message, string $url): void
    {
        $request = new Request('DELETE', $url);

        $this->handler->append(new RequestException($message, $request));
    }

    protected function mockServerErrorResponse($message): void
    {
        $this->handler->append(new Response(500, [], $message));
    }

    protected function mockResponse(int $status, array $headers, array $body): void
    {
        $this->handler->append(new Response($status, $headers, json_encode($body)));
    }
}
