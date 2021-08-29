# Grab Flight Info for Yii2 

该模块用于抓取航班基本信息，可抓取到的信息如下：

- 航班号
- 航空公司
- 出发地
- 目的地
- 预计离开时间
- 预计抵达时间
- 实际离开时间
- 实际抵达时间
- 状态

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist thirsight/yii2-tsmd-flight
```

or add

```
"thirsight/yii2-tsmd-flight": "~2.0.0"
```

to the require section of your `composer.json` file.

Usage
-----

抓取单个航班信息

```php
Yii::$app->get('flight')->grabFlight('CX0400');
```

```php
Yii::$app->get('flight')->grabFlight('CX0400', '2021-08-28');
```

抓取多个航班信息

```php
Yii::$app->get('flight')->grabFlights(['CX0474', 'CI5834', 'BR0852']);
```

目前抓取网站顺序如下：

- https://www.taipei-airport.com
- https://www.taoyuan-airport.com
- https://airportinfo.live
- https://www.hongkongairport.com