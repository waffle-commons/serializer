<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;
use Waffle\Commons\Contracts\Attribute\ListOf;

/**
 * A list of every element kind, and a nullable list.
 */
#[Dto]
final readonly class Measures
{
    /**
     * @param list<int>           $counts
     * @param list<float>         $ratios
     * @param list<bool>          $flags
     * @param list<Priority>      $priorities
     * @param list<string>|null   $notes
     */
    public function __construct(
        #[ListOf('int')]
        public array $counts,
        #[ListOf('float')]
        public array $ratios,
        #[ListOf('bool')]
        public array $flags,
        #[ListOf(Priority::class)]
        public array $priorities,
        #[ListOf('string')]
        public ?array $notes = null,
    ) {}
}
