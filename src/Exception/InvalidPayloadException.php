<?php

declare(strict_types=1);

namespace Waffle\Commons\Serializer\Exception;

use InvalidArgumentException;
use Throwable;
use Waffle\Commons\Contracts\Exception\Validation\ValidationExceptionInterface;

/**
 * A payload that is valid JSON but does not fit the DTO: a missing or mistyped
 * field, a value a `set` hook rejected, an unknown enum value.
 *
 * It is a {@see ValidationExceptionInterface}, so the error handler renders it
 * as an RFC 7807 `422` and shows its message and its field. The field is a path
 * from the payload root: `email`, `shipping.city`, `lines[2].quantity`.
 */
final class InvalidPayloadException extends InvalidArgumentException implements ValidationExceptionInterface
{
    public function __construct(
        string $message,
        private readonly ?string $field = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 422, $previous);
    }

    /**
     * The path of the offending field, or null when the failure is not tied to
     * one field (the payload is not an object, a hook rejected a value without
     * naming it at the root).
     */
    #[\Override]
    public function getField(): ?string
    {
        return $this->field;
    }
}
