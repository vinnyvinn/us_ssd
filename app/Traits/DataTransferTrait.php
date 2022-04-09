<?php

namespace App\Traits;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;

trait DataTransferTrait
{

    protected $client;

    public function __construct()
    {
        $this->client = new Client(['http_errors' => true,]);
    }

    public function guzzlePost($url, $data)
    {
        try {
            $this->client = new Client(['http_errors' => true,]);
            $request = new Request('POST', $url, ['Content-Type' => 'application/x-www-form-urlencoded'], http_build_query($data, null, '&'));
            $response = $this->client->send($request, ['timeout' => 20]);
            Log::info(serialize($response->getBody()->getContents()));
            return $response->getBody();
        } catch (RequestException $e) {
            Log::info($e->getRequest()->getBody()->getContents());
            if ($e->hasResponse()) {
                Log::error(serialize($e->getResponse()->getBody()->getContents()));
            } else {
                Log::error($e->getMessage());
            }
        }
    }

    public function guzzlePostJson($url, $data)
    {
        try {
            $this->client = new Client(['http_errors' => true,]);
            $request = new Request('POST', $url, ['Content-Type' => 'application/json'], $data);
            $response = $this->client->send($request, ['timeout' => 20]);
            Log::error(serialize($response->getBody()->getContents()));
            return $response->getBody();
        } catch (RequestException $e) {
            Log::info($e->getRequest()->getBody()->getContents());
            if ($e->hasResponse()) {
                Log::error(serialize($e->getResponse()->getBody()->getContents()));
            } else {
                Log::error($e->getMessage());
            }
        }
    }

    public function guzzleGet($url)
    {
        try {
            $this->client = new Client(['http_errors' => true,]);
            $request = new Request('GET', $url);
            $response = $this->client->send($request, ['timeout' => 20]);
            Log::debug(serialize($response->getBody()->getContents()));
            return $response->getBody();
        } catch (RequestException $e) {
            Log::info($e->getRequest()->getBody()->getContents());
            if ($e->hasResponse()) {
                Log::error(serialize($e->getResponse()->getBody()->getContents()));
            } else {
                Log::error($e->getMessage());
            }
        } catch (ClientException $e) {
            Log::error($e->getRequest());
            Log::error($e->getResponse());
            Log::error($e->getMessage());
        }
    }
}
