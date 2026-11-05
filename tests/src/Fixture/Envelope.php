<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;
use Waffle\Commons\Contracts\Attribute\ListOf;

/**
 * Nests DTOs that have no readable property.
 */
#[Dto]
final readonly class Envelope
{
    /**
     * @param list<Marker> $markers
     */
    public function __construct(
        public Marker $marker,
        #[ListOf(Marker::class)]
        public array $markers = [],
        public ?Marker $optional = null,
    ) {}
}
