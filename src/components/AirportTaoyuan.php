<?php

namespace tsmd\flight\components;

/**
 * 获取桃园国际机场航班信息组件
 *
 * 香港机场与桃园机场航班信息对比
 * - 都有接口
 * - 香港机场有的航班信息查不到，如：RH570
 *
 * @see https://www.taoyuan-airport.com
 */
class AirportTaoyuan extends FlightCrawler
{
    /**
     * @param string $flightNo
     * @param string $date
     * @return array
     */
    public function grabFlight(string $flightNo, string $date)
    {
        foreach($this->possibleFltNoIATA($flightNo) as $fno) {
            $flights = $this->grabDateFlights($date, $fno);
            if ($flights) {
                $flights[0]['flightNo'] = $flightNo;
                return $flights[0];
            }
        }
        return [];
    }

    /**
     * 采集货机、客机航班信息
     *
     * @param string $date eg: 2021-08-08
     * @param string $fltNo eg: CF209
     * @return array
     */
    public function grabDateFlights($date, $fltNo = '')
    {
        $date = date('Y/m/d', strtotime($date));
        $forms = [
            [
                'ft' => 'cargoA', 'uid' => '325', 'pid' => '12',
                's' => $fltNo, 'f' => '', 'c' => '', 'a' => '', 'dt' => 'all', 'dd' => $date, 'tm' => '',
            ],
            [
                'ft' => 'arrival', 'uid' => '154', 'pid' => '12',
                's' => $fltNo, 'f' => '', 'c' => '', 'a' => '', 'dt' => 'all', 'dd' => $date, 'tm' => '',
            ],
        ];
        // 获取航班信息
        $raw = [];
        foreach ($forms as $form) {
            $resp = $this->client->post('https://www.taoyuan-airport.com/main_en/airData.aspx', [
                'form_params' => $form,
            ]);
            $rows = json_decode(json_decode($resp->getBody()->getContents(), true), true);
            $raw = array_merge($raw, $rows['fd'] ?? []);
        }
        $flights = [];
        foreach ($raw as $r) {
            // 表定抵达日期
            $odate = str_ireplace('/', '-', $r['ODate']);
            // 实际抵达日期、时间
            if (preg_match('#^(\d\d:\d\d)(?:<br/>\((\d\d/\d\d)\))?#', $r['Rtime'], $m)) {
                $rtime = $m[1];
                $rdate = !empty($m[2]) ? date('Y-m-d', strtotime($m[2])) : $odate;
            } else {
                $rtime = null;
                $rdate = null;
            }
            // 状态
            $memo = preg_replace('#^(.*)<br.*#', '$1', $r['memo']);
            $memo = strtolower(preg_replace('#^([a-zA-Z]+).*#', '$1', strip_tags($memo)));

            $flights[] = [
                'flightNo'  => $fno = str_ireplace(['-', ' '], '', strip_tags($r['FlightNo'])),
                'fltNoIATA' => $fno,
                'dest'      => 'TPE',
                'eta'       => sprintf('%s %s:00', $odate, $r['OTime']),
                'ata'       => $rdate ? sprintf('%s %s:00', $rdate, $rtime) : null,
                'status'    => $memo,
                'source'    => 'airporttaoyuan',
            ];
        }
        return $flights;
    }
}
