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

class CrawlBank
{
    protected $client;

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
        $content = file_get_contents('http://forex.cbm.gov.mm/api/latest');

        $cbm_rate = json_decode($content, true);

        return $this->response('cbm', $cbm_rate);
    }

    private function mcb($type)
    {
        $bank = 'Myanmar Citizen';
        try {
            $crawler = $this->client->request('GET', 'http://www.mcb.com.mm/');
        } catch (ConnectException $e) {
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

        try {
            $timestamp = $crawler->filter('tr:nth-child(1)')->text();
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

        $usdbuy = $crawler->filter('tr:nth-child(3) td:nth-child(2)')->text();

        $usdsell = $crawler->filter('tr:nth-child(3) td:nth-child(3)')->text();

        $eubuy = $crawler->filter('tr:nth-child(4) td:nth-child(2)')->text();
        $eusell = $crawler->filter('tr:nth-child(4) td:nth-child(3)')->text();

        $sgdbuy = $crawler->filter('tr:nth-child(5) td:nth-child(2)')->text();
        $sgdsell = $crawler->filter('tr:nth-child(5) td:nth-child(3)')->text();

        $myrbuy = $crawler->filter('tr:nth-child(6) td:nth-child(2)')->text();
        $myrsell = $crawler->filter('tr:nth-child(6) td:nth-child(3)')->text();

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

        $exrate = $crawler->filter('div.row.exchange-rate div')->children()->each(function (Crawler $node, $i) {

            $response = [];

            if (strpos($node->filter('span')->text(), 'EXCHANGE') !== false) {
                $response['timestamp'] = $node->filter('strong')->text();
            }

            if (strpos($node->filter('span')->text(), 'USD') !== false) {
                preg_match('/(?<=BUY\s)([0-9]+)/', $node->text(), $buy);
                $response['buy']['USD'] = $buy[1];

                preg_match('/(?<=SELL\s)([0-9]+)/', $node->text(), $sell);
                $response['sell']['USD'] = $sell[1];
            }

            if (strpos($node->filter('span')->text(), 'SGD') !== false) {
                preg_match('/(?<=BUY\s)([0-9]+)/', $node->text(), $buy);
                $response['buy']['SGD'] = $buy[1];

                preg_match('/(?<=SELL\s)([0-9]+)/', $node->text(), $sell);
                $response['sell']['SGD'] = $sell[1];
            }

            if (strpos($node->filter('span')->text(), 'EUR') !== false) {
                preg_match('/(?<=BUY\s)([0-9]+)/', $node->text(), $buy);
                $response['buy']['EUR'] = $buy[1];

                preg_match('/(?<=SELL\s)([0-9]+)/', $node->text(), $sell);
                $response['sell']['EUR'] = $sell[1];
            }

            if (strpos($node->filter('span')->text(), 'THB') !== false) {
                preg_match('/(?<=BUY\s)([0-9]+)/', $node->text(), $buy);
                $response['buy']['THB'] = $buy[1];

                preg_match('/(?<=SELL\s)([0-9]+)/', $node->text(), $sell);
                $response['sell']['THB'] = $sell[1];
            }

            return $response;

        });

        $timestamp = strtotime($exrate[0]['timestamp']);

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

        try {

            $crawler = $this->client->request('GET', 'http://www.cbbank.com.mm/exchange_rate.aspx');
        } catch (ConnectException $e) {

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

        $timestamp = $crawler->filter('tr:nth-child(7)')->text();
        $timestamp = strtotime(preg_replace('/[^0-9a-zA-Z]\s+/S', " ", $timestamp));

        $usdbuy = $crawler->filter('tr:nth-child(2) td:nth-child(2)')->text();

        $usdsell = $crawler->filter('tr:nth-child(2) td:nth-child(3)')->text();

        $eubuy = $crawler->filter('tr:nth-child(3) td:nth-child(2)')->text();
        $eusell = $crawler->filter('tr:nth-child(3) td:nth-child(3)')->text();

        $sgdbuy = $crawler->filter('tr:nth-child(4) td:nth-child(2)')->text();
        $sgdsell = $crawler->filter('tr:nth-child(4) td:nth-child(3)')->text();

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

    public function __call($method, $parameters)
    {
        $response['status'] = 'Error';
        $response['message'] = 'Method (' . $method . ') not defined!';
        return Response::json($response);
    }
}
