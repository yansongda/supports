<?php

declare(strict_types=1);

use native_types;

function main(): void
{
    $a = 10;
    $a += 2.5;
    echo $a, PHP_EOL;
    $b = strlen('abc');
    $b += 1;
    echo $b, PHP_EOL;
}
