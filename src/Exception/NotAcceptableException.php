<?php

declare(strict_types=1);

namespace Waffle\Commons\Serializer\Exception;

use RuntimeException;
use Waffle\Commons\Contracts\Serializer\Exception\SerializerExceptionInterface;

/**
 * The client accepts none of the representations the API serves: JSON is the
 * only one (API-02; other formats are post-v1).
 *
 * Its code is `406`, which the error handler renders as an RFC 7807 problem.
 * The `Accept` header the client sent is kept for diagnosis, never echoed in the
 * message.
 */
final class NotAcceptableException extends RuntimeException implements SerializerExceptionInterface
{
    public function __construct(
        public private(set) string $accept,
    ) {
        parent::__construct('The client accepts none of the representations this API serves (application/json).', 406);
    }
}
