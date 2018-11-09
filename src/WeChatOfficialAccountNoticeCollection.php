<?php

namespace Yansongda\Supports;

/**
 * @property-read string $appid 公众账号ID
 * @property-read string $bank_type 付款银行
 * @property-read int $cash_fee 现金支付金额
 * @property-read string $fee_type 货币种类
 * @property-read string $is_subscribe 是否关注公众账号
 * @property-read string $mch_id 商户号
 * @property-read string $nonce_str 随机字符串
 * @property-read string $openid 用户标识
 * @property-read string $out_trade_no 商户订单号
 * @property-read string $result_code 业务结果
 * @property-read string $return_code 返回状态码
 * @property-read string $sign 签名
 * @property-read string $time_end 支付完成时间
 * @property-read int $total_fee 订单金额
 * @property-read string $trade_type 交易类型
 * @property-read string $transaction_id 微信支付订单号
 * @mixin         Collection
 */
class WeChatOfficialAccountNoticeCollection
{
}
