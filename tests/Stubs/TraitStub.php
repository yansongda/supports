<?php

namespace Yansongda\Supports\Tests\Stubs;

use Yansongda\Supports\Traits\Accessable;
use Yansongda\Supports\Traits\Arrayable;
use Yansongda\Supports\Traits\Serializable;

class TraitStub
{
    use Accessable;
    use Arrayable;
    use Serializable;

    private $name = 'yansongda';

    private $fooBar = 'name';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): TraitStub
    {
        $this->name = $name;
        return $this;
    }
}
