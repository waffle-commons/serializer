<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Fixture;

enum Status: string
{
    case Draft = 'draft';
    case Paid = 'paid';
    case Shipped = 'shipped';
}
