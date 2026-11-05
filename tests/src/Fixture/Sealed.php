<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Fixture;

/**
 * A private constructor: reflection refuses to build it.
 */
final readonly class Sealed
{
    private function __construct() {}

    public static function create(): self
    {
        return new self();
    }
}
