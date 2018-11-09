<?php

namespace Yansongda\Supports;

/**
 * @property-read string $out_refund_no 商户退款单号
 * @property-read string $out_trade_no 商户订单号
 * @property-read string $refund_account 退款资金来源
 * @property-read int $refund_fee 申请退款金额(分)
 * @property-read string $refund_id 微信退款单号
 * @property-read string $refund_recv_accout 退款入账账户
 * @property-read string $refund_request_source 退款发起来源
 * @property-read string $refund_status 退款状态
 * @property-read int $settlement_refund_fee 退款金额
 * @property-read int $settlement_total_fee 应结订单金额
 * @property-read string $success_time 退款成功时间
 * @property-read int $total_fee 订单金额
 * @property-read string $transaction_id 微信订单号
 * @property-read string $return_code 返回状态码
 * @property-read string $appid 公众账号ID
 * @property-read string $mch_id 退款的商户号
 * @property-read string $nonce_str 随机字符串
 * @property-read string $req_info 加密信息
 * @mixin         Collection
 */
class WeChatPayRefundCollection
{
}
