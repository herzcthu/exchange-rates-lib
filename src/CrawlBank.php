<?php
/**
 * Created by PhpStorm.
 * User: sithu
 * Date: 7/8/17
 * Time: 10:40 AM
 */

namespace Herzcthu\ExchangeRates;

use Goutte\Client;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Response;
use Symfony\Component\DomCrawler\Crawler;
use JonnyW\PhantomJs\Client as PhantomClient;
use Illuminate\Support\Facades\Log;

class CrawlBank
{
    protected $client;

    protected $error_rates = [
        'sell_rates' => [
            'USD' => 'Error',
            'EUR' => 'Error',
            'SGD' => 'Error',
            'MYR' => 'Error',
            'THB' => 'Error',
        ],
        'buy_rates' => [
            'USD' => 'Error',
            'EUR' => 'Error',
            'SGD' => 'Error',
            'MYR' => 'Error',
            'THB' => 'Error',
        ]
    ];

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getRates($bank, $type = false)
    {
        $bankname = strtolower($bank);
        $response = $this->$bankname($type);
        return Response::json($response);
    }

    public function getRatesArr($bank, $type = false)
    {
        $bankname = strtolower($bank);
        return $this->$bankname($type);
    }

    private function response($type, $rates, $bank = '', $timestamp = false, $status = true)
    {
        $base_info = [
            'status' => ($status) ? 'Success' : 'Failed',
            'type' => strtoupper($type),
            'info' => $bank . ' Bank Exchange Rate',
            'description' => $bank . ' Bank Exchange Rate extracted',
            'timestamp' => $timestamp,
        ];

        $response = array_merge($base_info, $rates);
        return $response;
    }

    private function cbm($type)
    {
        $content = file_get_contents('https://forex.cbm.gov.mm/api/latest');

        $cbm_rate = json_decode($content, true);

        return $this->response('cbm', $cbm_rate);
    }

    private function mcb($type)
    {
        $bank = 'Myanmar Citizen';
        try {
            $crawler = $this->client->request('GET', 'http://www.mcb.com.mm/');
        } catch (ConnectException $e) {
            return $this->response('error', $this->error_rates, $bank, false, false);
        }

        try {
            $timestamp = $crawler->filter('div.rate-title-wrap span')->text();
            Log::info($timestamp);
        } catch (\InvalidArgumentException $e) {
            $error_rates['sell_rates'] = [
                'USD' => 'Error',
                'EUR' => 'Error',
                'SGD' => 'Error',
                'MYR' => 'Error',
            ];
            $error_rates['buy_rates'] = [
                'USD' => 'Error',
                'EUR' => 'Error',
                'SGD' => 'Error',
                'MYR' => 'Error',
            ];
            return $this->response('error', $error_rates, $bank, false, false);
        }
        preg_match('/([0-9]{1,2})[^0-9]*([0-9]{1,2})[^0-9]*([0-9]{4})[^0-9]*/', $timestamp, $matches);

        $date = sprintf('%02d', $matches[1]);
        $month = sprintf('%02d', $matches[2]);
        $year = sprintf('%02d', $matches[3]);

        $timestamp = $date . '-' . $month . '-' . $year;

        $timestamp = strtotime($timestamp);

        $rate_table = $crawler->filter('div.rate-data-wrap tbody')->text();
        Log::info($rate_table);

        $usdbuy = $crawler->filter('div.rate-data-wrap tbody tr:nth-child(1) td:nth-child(2)')->text();

        $usdsell = $crawler->filter('div.rate-data-wrap tbody tr:nth-child(1) td:nth-child(3)')->text();

        $eubuy = $crawler->filter('div.rate-data-wrap tbody tr:nth-child(2) td:nth-child(2)')->text();
        $eusell = $crawler->filter('div.rate-data-wrap tbody tr:nth-child(2) td:nth-child(3)')->text();

        $sgdbuy = $crawler->filter('div.rate-data-wrap tbody tr:nth-child(3) td:nth-child(2)')->text();
        $sgdsell = $crawler->filter('div.rate-data-wrap tbody tr:nth-child(3) td:nth-child(3)')->text();

        $myrbuy = $crawler->filter('div.rate-data-wrap tbody tr:nth-child(4) td:nth-child(2)')->text();
        $myrsell = $crawler->filter('div.rate-data-wrap tbody tr:nth-child(4) td:nth-child(3)')->text();

        $sell_rates['sell_rates'] = [
            'USD' => $usdsell,
            'EUR' => $eusell,
            'SGD' => $sgdsell,
            'MYR' => $myrsell,
        ];

        $buy_rates['buy_rates'] = [
            'USD' => $usdbuy,
            'EUR' => $eubuy,
            'SGD' => $sgdbuy,
            'MYR' => $myrbuy,
        ];

        switch ($type) {
            case 'sell':
                $rate = $sell_rates;
                break;
            case 'buy':
                $rate = $buy_rates;
                break;
            default:
                $rate = array_merge($sell_rates, $buy_rates);
                $type = 'both';
                break;
        }

        return $this->response($type, $rate, $bank, $timestamp);
    }

    private function kbz($type)
    {
        $bank = 'KBZ Bank';
        try {
            $crawler = $this->client->request('GET', 'https://www.kbzbank.com/en/');
        } catch (ConnectException $e) {
            return $this->response('error', $this->error_rates, $bank, false, false);
        }

        Log::info($crawler->filter('div')->text());

        preg_match('/(?<=Date – )([0-9]{2}\/[0-9]{2}\/[0-9]{4})/', $crawler->filter('div')->text(), $match);
        Log::info($match);
        $timestamp = $match;

        $exrate = $crawler->filter('div.exchange-rate-row ')->children()->each(function (Crawler $node, $i) {

            $response = [];

            Log::info($node->text());

//            if (strpos($node->filter('span')->text(), 'EXCHANGE') !== false) {
//                $response['timestamp'] = $node->filter('strong')->text();
//            }

            if (strpos($node->filter('strong')->text(), 'USD') !== false) {

                preg_match('/(?<=Buy – )([0-9]+)/', $node->text(), $usdbuy);

                $response['buy']['USD'] = $usdbuy[0];

                preg_match('/(?<=Sell – )([0-9]+)/', $node->text(), $usdsell);
                $response['sell']['USD'] = $usdsell[0];
            }

            if (strpos($node->filter('strong')->text(), 'SGD') !== false) {
                preg_match('/(?<=Buy – )([0-9]+)/', $node->text(), $sgdbuy);
                $response['buy']['SGD'] = $sgdbuy[0];

                preg_match('/(?<=Sell – )([0-9]+)/', $node->text(), $sgdsell);
                $response['sell']['SGD'] = $sgdsell[0];
            }

            if (strpos($node->filter('strong')->text(), 'EUR') !== false) {
                preg_match('/(?<=Buy – )([0-9]+)/', $node->text(), $eurbuy);
                $response['buy']['EUR'] = $eurbuy[0];

                preg_match('/(?<=Sell – )([0-9]+)/', $node->text(), $eursell);
                $response['sell']['EUR'] = $eursell[0];
            }

            if (strpos($node->filter('strong')->text(), 'THB') !== false) {
                preg_match('/(?<=Buy – )([0-9]+)/', $node->text(), $thbbuy);
                $response['buy']['THB'] = $thbbuy[0];

                preg_match('/(?<=Sell – )([0-9]+)/', $node->text(), $thbsell);
                $response['sell']['THB'] = $thbsell[0];
            }

            return $response;

        });

        //$timestamp = strtotime($exrate[0]['timestamp']);

        $sell_rates = [];

        $buy_rates = [];

        $sell = array_column($exrate, 'sell');
        foreach ($sell as $rates) {
            foreach ($rates as $currency => $rate) {
                $sell_rates['sell_rates'][$currency] = $rate;
            }
        }

        $buy = array_column($exrate, 'buy');
        foreach ($buy as $rates) {
            foreach ($rates as $currency => $rate) {
                $buy_rates['buy_rates'][$currency] = $rate;
            }
        }

        switch ($type) {
            case 'sell':
                $rate = $sell_rates;
                break;
            case 'buy':
                $rate = $buy_rates;
                break;
            default:
                $rate = array_merge($sell_rates, $buy_rates);
                $type = 'both';
                break;
        }

        return $this->response($type, $rate, $bank, $timestamp);
    }

    private function aya($type)
    {
        $bank = 'AYA';

        try {
            $crawler = $this->client->request('GET', 'http://www.ayabank.com/en_US/');
        } catch (ConnectException $e) {
            return $this->response('error', $this->error_rates, $bank, false, false);
        }

        $timestamp = $crawler->filter('tr.row-1 td.column-1')->text();

        $timestamp = strtotime(preg_replace('/[^0-9a-zA-Z:]\s?/s', " ", $timestamp));

        $usdbuy = $crawler->filter('tr.row-2 td.column-2')->text();
        $usdsell = $crawler->filter('tr.row-2 td.column-3')->text();

        $eubuy = $crawler->filter('tr.row-3 td.column-2')->text();
        $eusell = $crawler->filter('tr.row-3 td.column-3')->text();

        $sgdbuy = $crawler->filter('tr.row-4 td.column-2')->text();
        $sgdsell = $crawler->filter('tr.row-4 td.column-3')->text();

        $sell_rates['sell_rates'] = [
            'USD' => $usdsell,
            'EUR' => $eusell,
            'SGD' => $sgdsell,
        ];

        $buy_rates['buy_rates'] = [
            'USD' => $usdbuy,
            'EUR' => $eubuy,
            'SGD' => $sgdbuy,
        ];

        switch ($type) {
            case 'sell':
                $rate = $sell_rates;
                break;
            case 'buy':
                $rate = $buy_rates;
                break;
            default:
                $rate = array_merge($sell_rates, $buy_rates);
                $type = 'both';
                break;
        }

        return $this->response($type, $rate, $bank, $timestamp);
    }

    private function agd($type)
    {
        $bank = 'AGD';

        try {
            $content = file_get_contents('http://otcservice.agdbank.com.mm/utility/rateinfo?callback=?');
        } catch (\ErrorException $e) {
            return $this->response('error', $this->error_rates, $bank, false, false);
        }
        $agdrates = json_decode(substr($content, 2, -2));

        $sell_rates = [];
        $buy_rates = [];
        foreach ($agdrates->ExchangeRates as $rates) {
            if ($rates->From == 'KYT') {
                $sell_rates['sell_rates'][$rates->To] = $rates->Rate;
            }
            if ($rates->To == 'KYT') {
                $buy_rates['buy_rates'][$rates->From] = $rates->Rate;
            }
        }

        switch ($type) {
            case 'sell':
                $rate = $sell_rates;
                break;
            case 'buy':
                $rate = $buy_rates;
                break;
            default:
                $rate = array_merge($sell_rates, $buy_rates);
                $type = 'both';
                break;
        }

        return $this->response($type, $rate, $bank);
    }

    private function cbbank($type)
    {
        $bank = 'CB';

        $phantomclient = PhantomClient::getInstance();
        $phantomclient->getEngine()->setPath(base_path('vendor/bin') . '/phantomjs');
        $phantomclient->isLazy();
        $request = $phantomclient->getMessageFactory()->createRequest('https://www.cbbank.com.mm/en', 'GET');
        $response = $phantomclient->getMessageFactory()->createResponse();

        // Send the request
        $phantomclient->send($request, $response);

        if ($response->getStatus() === 200) {

            // Dump the requested page content
            $html = $response->getContent();
            // Log::info($html);
        }

        try {

            $crawler = new Crawler($html);
        } catch (ConnectException $e) {
            return $this->response('error', $this->error_rates, $bank, false, false);
        }

        $timestamp = $crawler->filter('div.update-date > span')->text();
        // $timestamp = strtotime(preg_replace('/[^0-9a-zA-Z]\s+/S', " ", $timestamp));
        //Log::info($timestamp);
        $usdbuy = $crawler->filter('div.currency-info:nth-child(2) > div.currency-buy')->text();
        //Log:info($usdbuy);
        $usdsell = $crawler->filter('div.currency-info:nth-child(2) > div.currency-sell')->text();

        $eubuy = $crawler->filter('div.currency-info:nth-child(3) > div.currency-buy')->text();
        $eusell = $crawler->filter('div.currency-info:nth-child(3) > div.currency-sell')->text();

        $sgdbuy = $crawler->filter('div.currency-info:nth-child(4) > div.currency-buy')->text();
        $sgdsell = $crawler->filter('div.currency-info:nth-child(4) > div.currency-sell')->text();


        $thbbuy = $crawler->filter('div.currency-info:nth-child(5) > div.currency-buy')->text();
        $thbsell = $crawler->filter('div.currency-info:nth-child(5) > div.currency-sell')->text();

        $myrbuy = $crawler->filter('div.currency-info:nth-child(6) > div.currency-buy')->text();
        $myrsell = $crawler->filter('div.currency-info:nth-child(6) > div.currency-sell')->text();


        $sell_rates['sell_rates'] = [
            'USD' => $usdsell,
            'EUR' => $eusell,
            'SGD' => $sgdsell,
            'THB' => $thbsell,
            'MYR' => $myrsell,
        ];

        $buy_rates['buy_rates'] = [
            'USD' => $usdbuy,
            'EUR' => $eubuy,
            'SGD' => $sgdbuy,
            'THB' => $thbbuy,
            'MYR' => $myrbuy,
        ];

        switch ($type) {
            case 'sell':
                $rate = $sell_rates;
                break;
            case 'buy':
                $rate = $buy_rates;
                break;
            default:
                $rate = array_merge($sell_rates, $buy_rates);
                $type = 'both';
                break;
        }

        return $this->response($type, $rate, $bank, $timestamp);
    }

    private function yoma($type) {
        $bank = 'yoma';

        try {
            $crawler = $this->client->request('GET', 'https://www.yomabank.com/en/business/rates');
        } catch (ConnectException $e) {
            return $this->response('error', $this->error_rates, $bank, false, false);
        }

        $timestamp = $crawler->filter('span.exrate-date:nth-child(1)')->text();

        $usdbuy = $crawler->filter('div.exratedetailTlb:nth-child(1) tbody tr:nth-child(1) > td.buyrate')->text();
        $usdsell = $crawler->filter('div.exratedetailTlb:nth-child(1) tbody tr:nth-child(1) > td.sellrate')->text();

        $eubuy = $crawler->filter('div.exratedetailTlb:nth-child(1) tbody tr:nth-child(2) > td.buyrate')->text();
        $eusell = $crawler->filter('div.exratedetailTlb:nth-child(1) tbody tr:nth-child(2) > td.sellrate')->text();

        $sgdbuy = $crawler->filter('div.exratedetailTlb:nth-child(1) tbody tr:nth-child(3) > td.buyrate')->text();
        $sgdsell = $crawler->filter('div.exratedetailTlb:nth-child(1) tbody tr:nth-child(3) > td.sellrate')->text();

        $myrbuy = $crawler->filter('div.exratedetailTlb:nth-child(1) tbody tr:nth-child(5) > td.buyrate')->text();
        $myrsell = $crawler->filter('div.exratedetailTlb:nth-child(1) tbody tr:nth-child(5) > td.sellrate')->text();

        $thbbuy = $crawler->filter('div.exratedetailTlb:nth-child(1) tbody tr:nth-child(6) > td.buyrate')->text();
        $thbsell = $crawler->filter('div.exratedetailTlb:nth-child(1) tbody tr:nth-child(6) > td.sellrate')->text();

        $sell_rates['sell_rates'] = [
            'USD' => $usdsell,
            'EUR' => $eusell,
            'SGD' => $sgdsell,
            'MYR' => $myrsell,
            'THB' => $thbsell,
        ];

        $buy_rates['buy_rates'] = [
            'USD' => $usdbuy,
            'EUR' => $eubuy,
            'SGD' => $sgdbuy,
            'MYR' => $myrbuy,
            'THB' => $thbbuy
        ];

        switch ($type) {
            case 'sell':
                $rate = $sell_rates;
                break;
            case 'buy':
                $rate = $buy_rates;
                break;
            default:
                $rate = array_merge($sell_rates, $buy_rates);
                $type = 'both';
                break;
        }
    }

    public function __call($method, $parameters)
    {
        $response['status'] = 'Error';
        $response['message'] = 'Method (' . $method . ') not defined!';
        return Response::json($response);
    }
}
