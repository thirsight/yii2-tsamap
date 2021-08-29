<?php

namespace tsmd\flight\components;

/**
 * 航班信息抓取接口
 *
 * @author Haisen <thirsight@gmail.com>
 * @since 1.0
 */
interface FlightCrawlerInterface
{
    /**
     * 获取单个航班信息
     *
     * @param string $flightNo
     * @param string $date
     * @return array
     */
    public function grabFlight(string $flightNo, string $date);

    /**
     * 获取多个航班信息
     *
     * @param array $flightNos
     * @param string $date
     * @return array
     */
    public function grabFlights(array $flightNos, string $date);

    /**
     * 获取某一天的所有航班信息
     *
     * @param string $date
     * @return array
     */
    public function grabDateFlights(string $date);

    /**
     * 格式化单个航班信息
     *
     * @param array $flight
     * @param callable $func
     */
    public function format(array &$flight, $func = null);
}
