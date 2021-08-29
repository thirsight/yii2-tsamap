<?php

namespace tsmd\flight\components;

use Exception;

/**
 * 从 airportinfo.live 获取航班信息的组件
 *
 * @see https://airportinfo.live
 */
class AirportInfoLive extends FlightCrawler
{
    /**
     * {@inheritDoc}
     */
    public function init()
    {
        parent::init();

        $this->setFormatKey('flightNo', 'flight_iata_number');
        $this->setFormatKey('fltNoIATA', 'flight_iata_number');
        $this->setFormatKey('company', 'airline_icao_code');
        $this->setFormatKey('dept', 'departure_iata_code');
        $this->setFormatKey('dest', 'arrival_iata_code');
        $this->setFormatKey('etd', 'departure_scheduled_time');
        $this->setFormatKey('eta', 'arrival_scheduled_time');
        $this->setFormatKey('atd', 'departure_actual_time');
        $this->setFormatKey('ata', 'arrival_actual_time');
        $this->setFormatKey('type', '');
        $this->setFormatKey('status', 'status');
        $this->setFormatKey('statusBrief', '');
        $this->setFormatKey('source', 'airportinfo');
    }

    /**
     * @param string $flightNo CI0608
     * @param string $date eg: 2021-08-08
     * @return array
     */
    public function grabFlightIATA(string $flightNo, string $date)
    {
        $airline = substr($flightNo, 0, 2);
        $fnum = ltrim(substr($flightNo, 2), '0');

        try {
            $resp = $this->client->post("https://airportinfo.live/flightdata/get_flight.php", [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:90.0) Gecko/20100101 Firefox/90.0',
                    'Referer' => "https://airportinfo.live/flight/{$airline}{$fnum}",
                ],
                'form_params' => [
                    'lang'         => 'en',
                    'type'         => 'refresh',
                    'from_gmt'     => '',
                    'from_iata'    => '',
                    'airline'      => $airline,
                    'flightNumber' => $fnum,
                    'date'         => $date,
                ],
            ]);
            $raw = json_decode($resp->getBody()->getContents(), true);
            if (empty($raw)) return [];

        } catch (Exception $e) {
            return [];
        }

        $this->format($raw);
        return $raw;
    }

    /**
     * @param string $flightNo CI0608
     * @param string $date eg: 2021-08-08
     * @return array
     */
    public function grabFlight(string $flightNo, string $date)
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
