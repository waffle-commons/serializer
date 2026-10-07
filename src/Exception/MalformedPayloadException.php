<?php

declare(strict_types=1);

namespace Waffle\Commons\Serializer\Exception;

use InvalidArgumentException;
use JsonException;

/**
 * The request body is not valid JSON, or nests deeper than the configured
 * limit.
 *
 * Its code is `400`, which the error handler renders as an RFC 7807
 * "Bad Request". The parser's own message stays in the previous exception and
 * never reaches the client.
 */
final class MalformedPayloadException extends InvalidArgumentException
{
    public function __construct(JsonException $previous)
    {
        parent::__construct('The payload is not valid JSON.', 400, $previous);
    }
}
