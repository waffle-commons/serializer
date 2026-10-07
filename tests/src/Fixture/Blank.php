<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Fixture;

use Waffle\Commons\Contracts\Attribute\Dto;

/**
 * No constructor and no property: nothing in, `{}` out.
 */
#[Dto]
final readonly class Blank {}
