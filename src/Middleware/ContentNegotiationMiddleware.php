<?php

declare(strict_types=1);

namespace Waffle\Commons\Serializer\Middleware;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Waffle\Commons\Serializer\Exception\NotAcceptableException;

use function array_pad;
use function array_slice;
use function explode;
use function is_numeric;
use function max;
use function mb_trim;
use function min;
use function strtolower;

/**
 * `Accept` negotiation for a JSON API (API-02).
 *
 * JSON is the only representation the serializer writes, so negotiation is a
 * yes-or-no question: does the client accept `application/json`? A request
 * whose `Accept` header rules it out is refused with a 406
 * ({@see NotAcceptableException}, rendered by the error handler) before any
 * controller runs. Every response that passes adds `Vary: Accept`, so a cache
 * keeps the representations apart.
 *
 * Mount it inside the error-handler middleware. Stateless: it keeps nothing
 * between requests.
 */
final readonly class ContentNegotiationMiddleware implements MiddlewareInterface
{
    public const string JSON = 'application/json';

    /**
     * @throws NotAcceptableException   When the client accepts no JSON representation.
     * @throws InvalidArgumentException When the response refuses the `Vary` header (PSR-7).
     */
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $accept = $request->getHeaderLine('Accept');
        if (!self::acceptsJson($accept)) {
            throw new NotAcceptableException($accept);
        }

        $response = $handler->handle($request);

        return self::varies($response) ? $response : $response->withAddedHeader('Vary', 'Accept');
    }

    /**
     * Whether an `Accept` header admits `application/json` (RFC 9110 §12.5.1).
     *
     * No header, or an empty one, accepts anything. Otherwise the most specific
     * range that matches decides — `application/json`, then `application/*`, then
     * `*\/*` — and a weight of 0 rules JSON out. A malformed weight counts as 1.
     */
    public static function acceptsJson(string $accept): bool
    {
        if (mb_trim($accept) === '') {
            return true;
        }

        $best = 0;
        $weight = 0.0;
        foreach (explode(',', $accept) as $range) {
            $parts = explode(';', $range);
            $specificity = match (strtolower(mb_trim($parts[0]))) {
                self::JSON => 3,
                'application/*' => 2,
                '*/*' => 1,
                default => 0,
            };
            if ($specificity <= $best) {
                continue;
            }

            $best = $specificity;
            $weight = self::weight(array_slice($parts, 1));
        }

        return $weight > 0.0;
    }

    /**
     * The `q` parameter of a media range, between 0 and 1; 1 when it is absent or
     * malformed.
     *
     * @param list<string> $parameters
     */
    private static function weight(array $parameters): float
    {
        foreach ($parameters as $parameter) {
            [$name, $value] = array_pad(explode('=', $parameter, 2), 2, '');
            if (strtolower(mb_trim($name)) !== 'q') {
                continue;
            }

            $value = mb_trim($value);

            return is_numeric($value) ? max(0.0, min(1.0, (float) $value)) : 1.0;
        }

        return 1.0;
    }

    /**
     * Whether the response already varies on `Accept` (or on everything).
     */
    private static function varies(ResponseInterface $response): bool
    {
        foreach ($response->getHeader('Vary') as $line) {
            foreach (explode(',', $line) as $field) {
                $field = strtolower(mb_trim($field));
                if ($field === 'accept' || $field === '*') {
                    return true;
                }
            }
        }

        return false;
    }
}
