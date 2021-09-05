<?php

namespace tsmd\flight\components;

use Yii;

/**
 * 从香港国际机场获取航班信息的组件
 *
 * @see https://www.hongkongairport.com
 */
class AirportHongkong extends FlightCrawler
{
    /**
     * @var array
     * @see https://www.ccra.com/airport-codes/
     */
    public $filterDests = [];
    /**
     * @var array
     */
    protected $cacheFlights = [];

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        parent::init();

        $this->setFormatKey('flightNo', 'flightNo');
        $this->setFormatKey('fltNoIATA', 'fltNoIATA');
        $this->setFormatKey('company', 'company');
        $this->setFormatKey('dept', 'dept');
        $this->setFormatKey('dest', 'dest');
        $this->setFormatKey('etd', 'etd');
        $this->setFormatKey('type', 'type');
        $this->setFormatKey('status', 'status');
        $this->setFormatKey('statusBrief', 'statusBrief');
        $this->setFormatKey('source', 'airporthongkong');
    }

    /**
     * @param string $flightNo
     * @param string $date
     * @return array
     */
    public function grabFlight(string $flightNo, string $date)
    {
        if (!isset($this->cacheFlights[$date])) {
            $this->grabDateFlights($date);
        }
        // 一航班号可能包含多个日期的航班信息
        foreach ($this->cacheFlights[$date][$flightNo] ?? [] as $info) {
            if (stripos($info['etd'], $date) !== false) {
                $info['flightNo'] = $flightNo;
                return $info;
            }
        }
        return [];
    }

    /**
     * 采集货机、客机航班信息
     *
     * @param string $date eg: 2021-08-08
     * @return array
     */
    public function grabDateFlights(string $date)
    {
        if (isset($this->cacheFlights[$date])) {
            return $this->cacheFlights[$date];
        }
        if ($this->cacheFlights[$date] = Yii::$app->cache->get($date)) {
            return $this->cacheFlights[$date];
        }

        $data = [];
        foreach (['true', 'false'] as $cargo) {
            $resp = $this->client->get('https://www.hongkongairport.com/flightinfo-rest/rest/flights', [
                'timeout' => 5,
                'query' => [
                    'span' => '1',
                    'date' => $date,
                    'lang' => 'zh_HK',
                    'cargo' => $cargo,
                    'arrival' => 'false',
                ],
            ]);
            $data = array_merge($data, json_decode($resp->getBody()->getContents(), true));
        }

        $flights = [];
        foreach ($data as $d) {
            $cargo = $d['cargo'] ? 'cargo' : 'airliner';
            $etdDate = date('Y-m-d', strtotime($d['date']));

            foreach ($d['list'] as $flight) {
                // 目的地過濾
                if ($this->filterDests && !array_intersect($flight['destination'], $this->filterDests)) {
                    continue;
                }
                // 一个航班可能有多个航班号
                foreach ($flight['flight'] as $no) {
                    $fno = preg_replace('#\s+#', '', $no['no']);
                    $flt = [
                        'flightNo'    => $fno,
                        'fltNoIATA'   => $fno,
                        'company'     => $no['airline'],
                        'dept'        => 'HKG',
                        'dest'        => implode(',', $flight['destination']),
                        'etd'         => "{$etdDate} {$flight['time']}:00",
                        'status'      => $flight['statusCode'],
                        'statusBrief' => $flight['status'],
                        'type'        => $cargo,
                    ];
                    $this->format($flt);

                    $flights[$fno][] = $flt;
                }
            }
        }
        unset($data);
        Yii::$app->cache->set($date, $flights, 3600);
        return $this->cacheFlights[$date] = $flights;
    }
}
