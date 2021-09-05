<?php

namespace tsmd\flight\components;

use Yii;
use yii\base\BaseObject;

/**
 * @author Haisen <thirsight@gmail.com>
 * @since 1.0
 */
class Flight extends BaseObject
{
    /**
     * @var FlightCrawler[]
     */
    public $crawlers;

    /**
     * @inheritDoc
     */
    public function init()
    {
        foreach ($this->crawlers as &$class) {
            $class = Yii::createObject($class);
        }
    }

    /**
     * @param string $flightNo
     * @param string $date
     * @return array
     */
    public function grabFlight(string $flightNo, string $date = '')
    {
        $date = date('Y-m-d', strtotime($date) ?: time());

        foreach ($this->crawlers as $crawler) {
            if ($info = $crawler->grabFlightPossible($flightNo, $date)) {
                return $info;
            }
        }
        return [];
    }

    /**
     * @param array $flightNos
     * @param string $date
     * @return array
     */
    public function grabFlights(array $flightNos, string $date = '')
    {
        $date = date('Y-m-d', strtotime($date) ?: time());
        $all = [];
        foreach ($this->crawlers as $crawler) {
            $all = array_merge($all, $crawler->grabFlights($flightNos, $date));

            $flightNos = array_diff($flightNos, array_keys($all));
            if (empty($flightNos)) break;
        }
        return $all;
    }
}
