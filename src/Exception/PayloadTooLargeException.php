<?php

declare(strict_types=1);

namespace Waffle\Commons\Serializer\Exception;

use RuntimeException;

/**
 * The request body is larger than the configured limit; it was not decoded.
 *
 * Its code is `413`, which the error handler renders as the HTTP status.
 */
final class PayloadTooLargeException extends RuntimeException
{
    public function __construct(
        public private(set) int $limit,
    ) {
        parent::__construct(sprintf('The payload is larger than %d bytes.', $limit), 413);
    }
}
