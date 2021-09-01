<?php

namespace tsmd\flight\components;

use Exception;

/**
 * 获取台北桃园国际机场航班信息组件
 *
 * @see https://www.taipei-airport.com
 */
class AirportTaipei extends FlightCrawler
{
    /**
     * @return array
     */
    protected function getPatterns()
    {
        return [
            // Status
            'flight-status__title">(?<status>.*?)</div>[\s\S]*?',
            // Air Company
            'flight-airline__text">(?<company>.*?)</div>[\s\S]*?',
            // Departure City
            'Departure</div>[\s\S]*?flight-info__city">(?<dept>[\s\S]*?)</div>[\s\S]*?',
            // Departure Scheduled Date
            'flight-info__date">(?<deptSchedDate>[\s\S]*?)</div>[\s\S]*?',
            // Departure Actual Time
            'flight-info__infobox-text--\w+">(?<deptActualTime>[\s\S]*?)</div>[\s\S]*?',
            // Departure Scheduled Time
            'Scheduled Departure Time:(?<deptSchedTime>[\s\S]*?)</div>[\s\S]*?',
            // Arrival City
            'Arrival</div>[\s\S]*?flight-info__city">(?<dest>[\s\S]*?)</div>[\s\S]*?',
            // Arrival Scheduled Date
            'flight-info__date">(?<arriSchedDate>[\s\S]*?)</div>[\s\S]*?',
            // Arrival Actual Time
            'flight-info__infobox-text--\w+">(?<arriActualTime>[\s\S]*?)</div>[\s\S]*?',
            // Arrival Scheduled Time
            'Scheduled Arrival Time:(?<arriSchedTime>[\s\S]*?)</div>',
        ];
    }

    /**
     * @param string $html
     * @return array
     */
    protected function parseHtmlPatterns(string &$html)
    {
        if (!preg_match('#' . implode('', $this->getPatterns()) . '#im', $html, $raw)) {
            return [];
        }
        array_walk($raw, function (&$val) {
            $val = trim($val);
        });
        // 實際出發時間 07:55 (2021-08-28)
        if (preg_match('#(\d\d:\d\d)(?:\s+\((\d{4}-\d\d-\d\d)\))?#', $raw['deptActualTime'], $m)) {
            $raw['deptActualTime'] = $m[1];
            $raw['deptActualDate'] = $m[2] ?? $raw['deptSchedDate'];
        }
        // 實際抵達時間 07:55 (2021-08-28)
        if (preg_match('#(\d\d:\d\d)(?:\s+\((\d{4}-\d\d-\d\d)\))?#', $raw['arriActualTime'], $m)) {
            $raw['arriActualTime'] = $m[1];
            $raw['arriActualDate'] = $m[2] ?? $raw['arriSchedDate'];
        }
        return $raw;
    }

    /**
     * @param string $html
     * @return array
     */
    protected function parseHtmlPatternsPart(string &$html)
    {
        $keepPattern = sprintf('#(?:%s)#', implode('|', ['<company>', '<dept>', '<deptSchedDate>', '<deptSchedTime>', '<dest>']));
        $patterns = array_filter($this->getPatterns(), function ($p) use (&$keepPattern) {
            return preg_match($keepPattern, $p);
        });
        if (!preg_match('#' . implode('', $patterns) . '#im', $html, $raw)) {
            return [];
        }
        array_walk($raw, function (&$val) {
            $val = trim($val);
        });
        // 表定出發時間 10:37 (2021-09-01)
        if (preg_match('#(\d\d:\d\d)(?:\s+\((\d{4}-\d\d-\d\d)\))?#', $raw['deptSchedTime'], $m)) {
            $raw['deptSchedTime'] = $m[1];
            $raw['deptSchedDate'] = $m[2] ?? $raw['deptSchedDate'];
        }
        $raw['deptActualDate'] = '';
        $raw['deptActualTime'] = '';
        $raw['arriSchedDate']  = '';
        $raw['arriSchedTime']  = '';
        $raw['arriActualTime'] = '';
        $raw['arriActualDate'] = '';
        $raw['status'] = '';
        return $raw;
    }

    /**
     * @param string $flightNo
     * @return array
     */
    public function grabFlightIATA(string $flightNo, $date)
    {
        try {
            $resp = $this->client->get("https://www.taipei-airport.com/taoyuan-flight-arrival/{$flightNo}");
            $html = $resp->getBody()->getContents();
            $raw = $this->parseHtmlPatterns($html) ?: $this->parseHtmlPatternsPart($html);
            if (empty($raw)) {
                return [];
            }
            // 日期是否與傳遞的日期一致
            if ($raw['deptSchedDate'] != $date && $raw['deptActualDate'] != $date
                && $raw['arriSchedDate'] != $date && $raw['arriActualDate'] != $date) {
                return [];
            }
            $flight = [
                'flightNo'    => $flightNo,
                'fltNoIATA'   => $flightNo,
                'company'     => preg_match('#^(\w{3}) Air#', $raw['company'], $m) ? $m[1] : '',
                'dept'        => preg_match('#.*?\((\w+)\)#', $raw['dept'], $m) ? $m[1] : '',
                'dest'        => preg_match('#.*?\((\w+)\)#', $raw['dest'], $m) ? $m[1] : '',
                'etd'         => sprintf('%s %s:00', $raw['deptSchedDate'], $raw['deptSchedTime']),
                'eta'         => $raw['arriSchedDate'] ? sprintf('%s %s:00', $raw['arriSchedDate'], $raw['arriSchedTime']) : null,
                'atd'         => $raw['deptActualDate'] ? sprintf('%s %s:00', $raw['deptActualDate'], $raw['deptActualTime']) : null,
                'ata'         => $raw['arriActualDate'] ? sprintf('%s %s:00', $raw['arriActualDate'], $raw['arriActualTime']) : null,
                'type'        => '',
                'status'      => strtolower(trim(preg_replace('# - .*$#', '', $raw['status']))),
                'statusBrief' => '',
                'source'      => 'airporttaipei',
            ];
            return $flight;

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @param string $flightNo CI0608
     * @param string $date eg: 2021-08-08
     * @return array
     */
    public function grabFlight(string $flightNo, string $date = '')
    {
        foreach ($this->possibleFltNoIATA($flightNo) as $pfno) {
            $info = $this->grabFlightIATA($pfno, $date);
            if ($info) {
                $info['flightNo'] = $flightNo;
                return $info;
            }
        }
        return [];
    }

    /**
     * @param string $date
     * @return array
     */
    public function grabDateFlights(string $date)
    {
        return [];
    }
}
