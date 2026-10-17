<?php

declare(strict_types=1);

namespace WaffleTests\Commons\Serializer\Aot\Throwing;

use RuntimeException;

// A build broken in a way that throws as soon as the file loads.
throw new RuntimeException('A broken build.');
