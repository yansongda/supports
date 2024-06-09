<?php

namespace Yansongda\Supports\Tests;

use PHPUnit\Framework\TestCase;
use stdClass;
use Yansongda\Supports\Arr;

class ArrTest extends TestCase
{
    public function testSnakeCaseKey()
    {
        $a = [
            'myName' => 'yansongda',
            'myAge' => 27,
            'family' => [
                'hasChildren' => false,
            ]
        ];
        $expect = [
            'my_name' => 'yansongda',
            'my_age' => 27,
            'family' => [
                'has_children' => false,
            ]
        ];
        self::assertEqualsCanonicalizing($expect, Arr::snakeCaseKey($a));

        $obj =  new stdClass();
        $a = [
            'myName' => 'yansongda',
            'myAge' => 27,
            'family' => [
                'hasChildren' => $obj,
            ]
        ];
        $expect = [
            'my_name' => 'yansongda',
            'my_age' => 27,
            'family' => [
                'has_children' => $obj,
            ]
        ];
        self::assertEqualsCanonicalizing($expect, Arr::snakeCaseKey($a));
    }

    public function testCamelCaseKey()
    {
        $a = [
            'my_name' => 'yansongda',
            'my_age' => 27,
            'family' => [
                'has_children' => false,
            ]
        ];
        $expect = [
            'myName' => 'yansongda',
            'myAge' => 27,
            'family' => [
                'hasChildren' => false,
            ]
        ];
        self::assertEqualsCanonicalizing($expect, Arr::camelCaseKey($a));
    }

    public function testCamelCaseKeyWithObject()
    {
        $obj = new Class {
            public function toArray(): array
            {
                return ['name' => 'yansongda'];
            }
        };

        $a = [
            'my_name' => 'yansongda',
            'my_age' => 27,
            'family' => [
                'has_children' => false,
            ],
            'objs' => [
                $obj,
            ]
        ];
        $expect = [
            'myName' => 'yansongda',
            'myAge' => 27,
            'family' => [
                'hasChildren' => false,
            ],
            'objs' => [
                ['name' => 'yansongda'],
            ]
        ];
        self::assertEqualsCanonicalizing($expect, Arr::camelCaseKey($a));
    }

    public function testToString()
    {
        $a = [
            'my_name' => 'yansongda',
            'my_age' => 27,
        ];

        self::assertEquals('my_name=yansongda&my_age=27', Arr::toString($a));
    }

    public function testWrapJson()
    {
        $array = ['name' => 'yansongda', 'age' => 29];
        $str = '{"name":"yansongda","age":29}';
        self::assertEquals($array, Arr::wrapJson($str));

        $str = '"json string"';
        self::assertNull(Arr::wrapJson($str));
    }

    public function testWrapXml()
    {
        $array = ['name' => 'yansongda', 'age' => 29];
        $str = '<xml><name><![CDATA[yansongda]]></name><age>29</age></xml>';

        self::assertEquals($array, Arr::wrapXml($str));
    }

    public function testWrapQuery()
    {
        $array = ['name' => 'yansongda', 'age' => 29];
        $str = 'name=yansongda&age=29';

        self::assertEquals($array, Arr::wrapQuery($str));
    }

    public function testWrapQuerySpace()
    {
        $array = ['name' => 'yan+song+da', 'age' => 29];
        $str = 'name=yan+song+da&age=29';

        self::assertEquals($array, Arr::wrapQuery($str, false, true));
    }

    public function testWrapQueryRaw()
    {
        $str = "accessType=0&bizType=000000&encoding=utf-8&merId=777290058167151&orderId=refundpay20240105165842&origQryId=052401051658427862748&queryId=052401051658427863998&respCode=00&respMsg=成功[0000000]&signMethod=01&txnAmt=1&txnSubType=00&txnTime=20240105165842&txnType=04&version=5.1.0&signPubKeyCert=-----BEGIN CERTIFICATE-----\r\nMIIEYzCCA0ugAwIBAgIFEDkwhTQwDQYJKoZIhvcNAQEFBQAwWDELMAkGA1UEBhMC\r\nQ04xMDAuBgNVBAoTJ0NoaW5hIEZpbmFuY2lhbCBDZXJ0aWZpY2F0aW9uIEF1dGhv\r\ncml0eTEXMBUGA1UEAxMOQ0ZDQSBURVNUIE9DQTEwHhcNMjAwNzMxMDExOTE2WhcN\r\nMjUwNzMxMDExOTE2WjCBljELMAkGA1UEBhMCY24xEjAQBgNVBAoTCUNGQ0EgT0NB\r\nMTEWMBQGA1UECxMNTG9jYWwgUkEgT0NBMTEUMBIGA1UECxMLRW50ZXJwcmlzZXMx\r\nRTBDBgNVBAMMPDA0MUA4MzEwMDAwMDAwMDgzMDQwQOS4reWbvemTtuiBlOiCoeS7\r\nveaciemZkOWFrOWPuEAwMDAxNjQ5NTCCASIwDQYJKoZIhvcNAQEBBQADggEPADCC\r\nAQoCggEBAMHNa81t44KBfUWUgZhb1YTx3nO9DeagzBO5ZEE9UZkdK5+2IpuYi48w\r\neYisCaLpLuhrwTced19w2UR5hVrc29aa2TxMvQH9s74bsAy7mqUJX+mPd6KThmCr\r\nt5LriSQ7rDlD0MALq3yimLvkEdwYJnvyzA6CpHntP728HIGTXZH6zOL0OAvTnP8u\r\nRCHZ8sXJPFUkZcbG3oVpdXQTJVlISZUUUhsfSsNdvRDrcKYY+bDWTMEcG8ZuMZzL\r\ng0N+/spSwB8eWz+4P87nGFVlBMviBmJJX8u05oOXPyIcZu+CWybFQVcS2sMWDVZy\r\nsPeT3tPuBDbFWmKQYuu+gT83PM3G6zMCAwEAAaOB9DCB8TAfBgNVHSMEGDAWgBTP\r\ncJ1h6518Lrj3ywJA9wmd/jN0gDBIBgNVHSAEQTA/MD0GCGCBHIbvKgEBMDEwLwYI\r\nKwYBBQUHAgEWI2h0dHA6Ly93d3cuY2ZjYS5jb20uY24vdXMvdXMtMTQuaHRtMDkG\r\nA1UdHwQyMDAwLqAsoCqGKGh0dHA6Ly91Y3JsLmNmY2EuY29tLmNuL1JTQS9jcmw3\r\nNTAwMy5jcmwwCwYDVR0PBAQDAgPoMB0GA1UdDgQWBBTmzk7XEM/J/sd+wPrMils3\r\n9rJ2/DAdBgNVHSUEFjAUBggrBgEFBQcDAgYIKwYBBQUHAwQwDQYJKoZIhvcNAQEF\r\nBQADggEBAJLbXxbJaFngROADdNmNUyVxPtbAvK32Ia0EjgDh/vjn1hpRNgvL4flH\r\nNsGNttCy8afLJcH8UnFJyGLas8v/P3UKXTJtgrOj1mtothv7CQa4LUYhzrVw3UhL\r\n4L1CTtmE6D1Kf3+c2Fj6TneK+MoK9AuckySjK5at6a2GQi18Y27gVF88Nk8bp1lJ\r\nvzOwPKd8R7iGFotuF4/8GGhBKR4k46EYnKCodyIhNpPdQfpaN5AKeS7xeLSbFvPJ\r\nHYrtBsI48jUK/WKtWBJWhFH+Gty+GWX0e5n2QHXHW6qH62M0lDo7OYeyBvG1mh9u\r\nQ0C300Eo+XOoO4M1WvsRBAF13g9RPSw=\r\n-----END CERTIFICATE-----&signature=c++EAuubwRkvr2MVyM9zyjbdH3RMRK/L1ttftpJ4fkl4ZSY1BjyRbTj5fx/2+Z/eH4dqPNfFEQt8egVVWhF/k7PaD8tLTaueeUIPwyjnEIWmqNtVbJtzKexCouGc8wtYDHZYxTJTgo6BW7GEgO5xD6Qpxq801Bb9Zto8uhn4BUP4HI7UsxHHIzP9JYhL2cqz2B8gb3AJHpLMEBpYv+Kb3mwq8ZFgpGaieCAFFGGWImUx1+MgCzLFoe3SKlTF13nbr39Cd3AHuDJnbN+uG1N6AwUtLu12Zzq/6SM+/dqiE0v5SpvB/PeRj9KQeiGDRg/ho9larqB+D3y0FjU13EeHng==";

        $wrapQuery = Arr::wrapQuery($str, true);

        self::assertIsObject(openssl_pkey_get_public($wrapQuery['signPubKeyCert']));
    }
}
