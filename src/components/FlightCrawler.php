<?php

namespace tsmd\flight\components;

use yii\base\BaseObject;
use yii\base\InvalidValueException;
use GuzzleHttp\Client;

/**
 * 航班抓取抽象类
 *
 * @see https://www.ccra.com/airport-codes/
 */
abstract class FlightCrawler extends BaseObject implements FlightCrawlerInterface
{
    /**
     * @var array 将获取到的数据键名转换成固定键名 []
     */
    private $_formatKeys = [
        'flightNo'    => 'eg. 航班号',
        'fltNoIATA'   => 'eg. 航班号 IATA',
        'company'     => 'eg. 航空公司',
        'dept'        => 'eg. 出发地',
        'dest'        => 'eg. 目的地',
        'etd'         => 'eg. 预计离开时间',
        'eta'         => 'eg. 预计抵达时间',
        'atd'         => 'eg. 实际离开时间',
        'ata'         => 'eg. 实际抵达时间',
        'type'        => 'eg. 类型',
        'status'      => 'eg. 状态',
        'statusBrief' => 'eg. 状态摘要',
        'source'      => 'eg. 数据来源',
    ];

    /**
     * @var Client
     */
    protected $client;
    /**
     * @var array
     */
    protected $reqHeaders = [];
    /**
     * @var array
     */
    protected $reqCookies = [];

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        $this->client = new Client();
    }

    /**
     * @param string $formatKey
     * @param string $grabKey
     */
    public function setFormatKey($formatKey, $grabKey)
    {
        if (!isset($this->_formatKeys[$formatKey])) {
            throw new InvalidValueException("Format key `{$formatKey}` doesn't exist.");
        }
        $this->_formatKeys[$formatKey] = $grabKey;
    }

    /**
     * @param array $raw
     * @param callable $func
     */
    public function format(array &$raw, $func = null)
    {
        $formatted = [];
        foreach ($this->_formatKeys as $formatKey => $grabKey) {
            if (stripos($grabKey, 'eg.') !== false) {
                continue;
            }
            $formatted[$formatKey] = key_exists($grabKey, $raw)
                ? (string) $raw[$grabKey]
                : ($grabKey ?: '');

            // 日期格式化
            if (in_array($formatKey, ['etd', 'eta', 'atd', 'ata']) && $formatted[$formatKey]) {
                $formatted[$formatKey] = strtotime($formatted[$formatKey])
                    ? date('Y-m-d H:i:s', strtotime($formatted[$formatKey]))
                    : null;
            }
        }
        if (is_callable($func)) {
            $func($formatted, $raw);
        }
        $raw = $formatted;
    }

    /**
     * 部分输入航班号与 IATA 定义的航班号可能存在差异，如：
     * - CI0608 -> CI608 (IATA)
     * - HX1274 -> HX274 (IATA)
     *
     * @param string $flightNo
     * @return array
     */
    public function possibleFltNoIATA($flightNo)
    {
        return array_unique([
            $flightNo,
            preg_replace('#^(\w{2})\d(\w+)$#', '$1$2', $flightNo),
        ]);
    }

    /**
     * @param array $flightNos
     * @param string $date
     * @return array
     */
    public function grabFlights(array $flightNos, string $date)
    {
        $flights = [];
        foreach ($flightNos as $flightNo) {
            if ($info = $this->grabFlight($flightNo, $date)) {
                $flights[$flightNo] = $info;
            }
        }
        return $flights;
    }

    /**
     * 添加 Cookie
     * @param array $respCookies GuzzleHttp\Client 的响应头中的 Set-Cookie
     * @param bool $clear
     */
    public function addCookies(array $respCookies, $clear = false)
    {
        if ($clear) {
            $this->reqCookies = [];
        }
        foreach ($respCookies as $name => $cookie) {
            if (preg_match('#([^=]+)=([^;]*)#', $cookie, $m)) {
                $this->reqCookies[$m[1]] = $m[2];
            } else {
                $this->reqCookies[$name] = $cookie;
            }
        }
    }

    /**
     * 生成字符串 Cookie
     * @param array $cookies
     * @return string
     */
    public function buildCookies(array $cookies = [])
    {
        $out = '';
        foreach (array_merge($this->reqCookies ?: [], $cookies) as $key => $val) {
            $out .= "{$key}={$val}; ";
        }
        return rtrim($out, '; ');
    }

    /**
     * 生成請求頭
     * @param array $headers
     * @param array $cookies
     * @return array
     */
    public function buildHeaders(array $headers = [], array $cookies = [])
    {
        return array_merge($this->reqHeaders ?: [], ['Cookie' => $this->buildCookies($cookies)], $headers);
    }
}
