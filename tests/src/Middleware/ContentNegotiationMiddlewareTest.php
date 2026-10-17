<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Waffle\Commons\Serializer\Exception\NotAcceptableException;
use Waffle\Commons\Serializer\Middleware\ContentNegotiationMiddleware;
use WaffleTests\Commons\Serializer\AbstractTestCase as TestCase;

#[CoversClass(ContentNegotiationMiddleware::class)]
#[CoversClass(NotAcceptableException::class)]
final class ContentNegotiationMiddlewareTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptingJson(): iterable
    {
        yield 'no header' => [''];
        yield 'JSON' => ['application/json'];
        yield 'anything' => ['*/*'];
        yield 'any application type' => ['application/*'];
        yield 'a browser' => ['text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'];
        yield 'JSON among others' => ['text/html;q=0.9, application/json;q=0.5'];
        yield 'parameters before the weight' => ['application/json; charset=utf-8; q=0.1'];
        yield 'a malformed weight counts as 1' => ['application/json;q=abc'];
        yield 'a weight above 1 counts as 1' => ['application/json;q=5'];
        yield 'case does not matter' => ['Application/JSON'];
        yield 'the most specific range wins' => ['*/*;q=0, application/json'];
    }

    #[DataProvider('acceptingJson')]
    public function testLetsARequestThatAcceptsJsonThrough(string $accept): void
    {
        $handler = self::handler(new Response(200));

        $response = new ContentNegotiationMiddleware()->process(self::request($accept), $handler);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['Accept'], $response->getHeader('Vary'));
        static::assertSame(1, $handler->calls);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusingJson(): iterable
    {
        yield 'HTML only' => ['text/html'];
        yield 'anything but JSON' => ['text/html, */*;q=0'];
        yield 'JSON excluded explicitly' => ['application/json;q=0, */*'];
        yield 'JSON excluded among application types' => ['application/*;q=0'];
        yield 'a zero weight written as 0.0' => ['application/json;q=0.0'];
    }

    #[DataProvider('refusingJson')]
    public function testRefusesARequestThatAcceptsNoJson(string $accept): void
    {
        $handler = self::handler(new Response(200));

        try {
            new ContentNegotiationMiddleware()->process(self::request($accept), $handler);
            static::fail('A request that accepts no JSON must be refused.');
        } catch (NotAcceptableException $exception) {
            static::assertSame(406, $exception->getCode());
            static::assertSame($accept, $exception->accept);
            static::assertSame(
                'The client accepts none of the representations this API serves (application/json).',
                $exception->getMessage(),
            );
            static::assertSame(0, $handler->calls, 'No controller runs for a refused request.');
        }
    }

    public function testDoesNotRepeatAnAcceptTheResponseAlreadyVariesOn(): void
    {
        $middleware = new ContentNegotiationMiddleware();

        $already = $middleware->process(self::request(''), self::handler(new Response(200, [
            'Vary' => 'Origin, accept',
        ])));
        $everything = $middleware->process(self::request(''), self::handler(new Response(200, ['Vary' => '*'])));
        $other = $middleware->process(self::request(''), self::handler(new Response(200, ['Vary' => 'Origin'])));

        static::assertSame(['Origin, accept'], $already->getHeader('Vary'));
        static::assertSame(['*'], $everything->getHeader('Vary'));
        static::assertSame(['Origin', 'Accept'], $other->getHeader('Vary'));
    }

    private static function request(string $accept): ServerRequestInterface
    {
        $request = new ServerRequest('GET', 'http://localhost/orders/42');

        return $accept === '' ? $request : $request->withHeader('Accept', $accept);
    }

    private static function handler(ResponseInterface $response): CountingHandler
    {
        return new CountingHandler($response);
    }
}

/**
 * Returns a fixed response and counts its calls.
 */
final class CountingHandler implements RequestHandlerInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly ResponseInterface $response,
    ) {}

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        ++$this->calls;

        return $this->response;
    }
}
