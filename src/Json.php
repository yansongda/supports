<?php

declare(strict_types=1);

namespace Yansongda\Supports;

use JsonException;

/**
 * JSON 编解码工具类（薄封装原生 json_encode / json_decode）。
 *
 * 与原生函数的差异：
 *  - 失败必抛：任何 JSON 错误（json_last_error() !== JSON_ERROR_NONE）都抛 \JsonException，绝不静默返回 false/半成品；
 *  - 默认 flags：encode 默认 JSON_UNESCAPED_UNICODE（原生为 0，中文会转义为 \uXXXX），可显式覆盖；
 *  - 默认关联数组：decode 默认返回关联数组（原生默认返回 stdClass），可传 $assoc = false 覆盖。
 *
 * 常用 flags 组合：
 *  - 默认：JSON_UNESCAPED_UNICODE（中文不转义、斜杠仍转义为 \/）；
 *  - 对齐 fastjson / JS / Python 字节输出：JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES（仅在与官方 SDK 字节对齐时才显式加，避免签名/缓存 key 类疑难 bug）；
 *  - 结果内联进 HTML <script>：JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT（防 </script> 逃逸）。
 *
 * depth 语义（encode/decode 不对称，均为 PHP 原生行为）：
 *  - decode：$depth <= 0 由 PHP 前置抛 \ValueError；嵌套超过 $depth 抛 JsonException（code 为 JSON_ERROR_DEPTH）；
 *  - encode：不校验 $depth，标量（如 'x'）可正常返回，数组嵌套超过 $depth 抛 JsonException（code 为 JSON_ERROR_DEPTH）。
 */
class Json
{
    /**
     * JSON 编码，失败即抛异常.
     *
     * @throws JsonException 任何 JSON 编码错误（code 为 JSON_ERROR_* 常量）
     */
    public static function encode(mixed $value, int $flags = JSON_UNESCAPED_UNICODE, int $depth = 512): string
    {
        $result = json_encode($value, $flags, $depth);

        if (JSON_ERROR_NONE === json_last_error() && false !== $result) {
            return $result;
        }

        throw new JsonException(json_last_error_msg(), json_last_error());
    }

    /**
     * JSON 解码，失败即抛异常.
     *
     * @throws JsonException 任何 JSON 解码错误（code 为 JSON_ERROR_* 常量）
     */
    public static function decode(string $json, bool $assoc = true, int $depth = 512, int $flags = 0): mixed
    {
        $result = json_decode($json, $assoc, $depth, $flags);

        if (JSON_ERROR_NONE === json_last_error()) {
            return $result;
        }

        throw new JsonException(json_last_error_msg(), json_last_error());
    }
}
