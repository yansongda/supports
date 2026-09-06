<?php

/**
 * typephp 冒烟 Zend 侧运行器（仅 Zend 运行时使用，不参与 tpc 编译）：
 * 容器内 `php tests/typephp/zend-run.php` 的实际输出即 expected.txt 基线。
 */

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/main.php';

main();
