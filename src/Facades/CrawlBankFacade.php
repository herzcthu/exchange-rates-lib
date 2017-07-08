<?php
/**
 * Created by PhpStorm.
 * User: sithu
 * Date: 7/8/17
 * Time: 6:31 PM
 */

namespace Herzcthu\ExchangeRates\Facades;

use Illuminate\Support\Facades\Facade;

class CrawlBankFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'crawlbank';
    }
}