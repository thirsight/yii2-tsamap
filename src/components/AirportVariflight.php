<?php

namespace tsmd\flight\components;

use Exception;

/**
 * 从飞常准获取航班信息的组件
 *
 * @see https://flightadsb.variflight.com/
 */
class AirportVariflight extends FlightCrawler
{
    /**
     * @var string[]
     */
    public $statuses = [
        '0' => 'schedule',
        '2' => 'arrived',
    ];

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        parent::init();

        $this->setFormatKey('flightNo', 'fnum');
        $this->setFormatKey('fltNoIATA', 'fnum');
        $this->setFormatKey('company', 'airline');
        $this->setFormatKey('dept', 'forg');
        $this->setFormatKey('dest', 'fdst');
        $this->setFormatKey('etd', 'scheduledDepAt');
        $this->setFormatKey('eta', 'scheduledArrAt');
        $this->setFormatKey('atd', 'actualDepAt');
        $this->setFormatKey('ata', 'actualArrAt');
        $this->setFormatKey('status', 'flightStatusCode');
        $this->setFormatKey('source', 'variflight');
    }

    /**
     * @param string $flightNo
     * @param string $date
     * @return array
     */
    public function grabFlight(string $flightNo, string $date)
    {
        try {
            $resp = $this->client->post('https://adsbapi.variflight.com/adsb/index/fuzzySearch', [
                'timeout' => 5,
                'query' => ['lang' => 'en_US', 'searchText' => $flightNo],
            ]);
            $data = json_decode($resp->getBody()->getContents(), true)['data']['flights'];

            $date = date('Y-m-d', strtotime($date));
            $tzUTCOffset = $this->getTimezoneOffset();
            foreach ($data as $key => &$d) {
                $orgTzOffset = $tzUTCOffset - $d['orgTinezone'];
                $dstTzOffset = $tzUTCOffset - $d['dstTinezone'];
                $d['scheduledDepAt'] = isset($d['scheduledDeptime']) ? date('Y-m-d H:i:s', $d['scheduledDeptime'] + $orgTzOffset) : null;
                $d['scheduledArrAt'] = isset($d['scheduledArrtime']) ? date('Y-m-d H:i:s', $d['scheduledArrtime'] + $dstTzOffset) : null;
                $d['actualDepAt'] = isset($d['actualDeptime']) ? date('Y-m-d H:i:s', $d['actualDeptime'] + $orgTzOffset) : null;
                $d['actualArrAt'] = isset($d['actualArrtime']) ? date('Y-m-d H:i:s', $d['actualArrtime'] + $dstTzOffset) : null;
                $d['flightStatusCode'] = $this->statuses[$d['flightStatusCode']] ?? $d['flightStatusCode'];

                if (stripos($d['scheduledArrAt'], $date) !== false || stripos($d['actualArrAt'], $date) !== false) {
                    $this->format($d);
                    return $d;
                }
            }
            return [];

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @param string $date eg: 2021-08-08
     * @return array
     */
    public function grabDateFlights(string $date)
    {
        return [];
    }
}
