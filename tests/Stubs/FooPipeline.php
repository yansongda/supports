<?php

declare(strict_types=1);

namespace Yansongda\Supports\Tests\Stubs;

use Yansongda\Supports\Pipeline;

class FooPipeline extends Pipeline
{
    protected function handleCarry(mixed $carry)
    {
        $carry = parent::handleCarry($carry);
        if (is_int($carry)) {
            $carry += 2;
        }
        return $carry;
    }
}
