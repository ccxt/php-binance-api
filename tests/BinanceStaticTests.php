<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php-binance-api.php'; // Adjust path if needed

class MockBinanceAPI extends Binance\API
{
    public function curl_exec($handler)
    {
        return "";
    }

    protected function curl_set_url($curl, $endpoint) {
        BinanceStaticTests::$capturedUrl = $endpoint;
        // curl_setopt($curl, CURLOPT_URL, $endpoint);
    }

    protected function curl_set_body($curl, $option, $query) {
        // curl_setopt($curl, $option, $query);
        BinanceStaticTests::$capturedBody = $query;
    }
}

class BinanceStaticTests extends TestCase
{
    public static $capturedUrl = null;
    public static $capturedBody = null;
    private MockBinanceAPI $binance;

    private $SPOT_ORDER_PREFIX     = "x-B3AUXNYV";
	private $CONTRACT_ORDER_PREFIX = "x-ftGmvgAN";

    // Default values for the tests
    private $symbol = 'ETHUSDT';
    private $quantity = '1.234';
    private $price = '1000';
    private $stopprice = '1100';
    private $stoplimitprice = '900';
    private $type = 'LIMIT';
    private $orderid = '000';
    private $orderId = '1234567890';
    private $limit = 2;
    private $fromOrderId = 1;
    private $fromTradeId = 2;
    private $startTime = 1;
    private $endTime = 6;
    private $symbols = ['ETHUSDT','BTCUSDT'];
    private $asset = 'USDT';
    private $assets = ['IOST','AAVE','CHZ'];
    private $address = '0x1234567890abcdef1234567890abcdef12345678';
    private $amount = 10.1;
    private $addressTag = '123456';
    private $addressName = 'MyAddress';
    private $transactionFeeFlag =  true;
    private $network = 'TESTNetwork';
    private $fromSymbol = 'USDT';
    private $toSymbol = 'BNB';
    private $recvWindow = 1000;
    private $current = 2;
    private $tradeId = 3;
    private $side = 'BUY';
    private $test = false;
    private $interval = '15m';
    private $nbrDays = 6;
    private $baseAsset = 'ETH';
    private $quoteAsset = 'USDT';
    private $quoteQty = 10.3;
    private $fromId = 1;
    private $contractType = 'CURRENT_QUARTER';
    private $period =  '15m';
    private $origClientOrderId = 'test client order id';
    private $orderIdList = ['123456', '654321'];
    private $origClientOrderIdList = ['test client order id 1', 'test client order id 2'];
    private $countdownTime = 100;
    private $autoCloseType = 'LIQUIDATION';
    private $marginType = 'CROSSED';
    private $dualSidePosition = true;
    private $leverage = 10;
    private $multiAssetsMarginMode = true;
    private $positionSide = 'SHORT';
    private $incomeType = 'COMMISSION_REBATE';
    private $page = 3;
    private $downloadId = 'testDownloadId';
    private $fromAsset = 'USDT';
    private $toAsset = 'BNB';
    private $fromAmount = '100';
    private $validTime = '10s';
    private $quoteId = 'testQuoteId';

    public function setUp(): void {
        $this->binance = new MockBinanceAPI('api_key', 'api_secret');
    }

    public function testPricesSpot()
    {
        try  {
            $this->binance->prices();
        } catch(\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/ticker/price", self::$capturedUrl);
    }


    public function testSpotOrder()
    {
        try  {
            $this->binance->order('BUY', 'BTCUSDT', 1, 1000);
        } catch(\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/order", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals("BTCUSDT", $params['symbol']);
        $this->assertEquals("BUY", $params['side']);
        $this->assertEquals("LIMIT", $params['type']);
        $this->assertEquals(1, $params['quantity']);
        $this->assertEquals(1000, $params['price']);
        $this->assertEquals("GTC", $params['timeInForce']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->SPOT_ORDER_PREFIX));
    }

    public function testSpotSorOrder()
    {
        try  {
            $this->binance->sorOrder('BUY', 'BTCUSDT', 1, 1000);
        } catch(\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/sor/order", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals("BTCUSDT", $params['symbol']);
        $this->assertEquals("BUY", $params['side']);
        $this->assertEquals("LIMIT", $params['type']);
        $this->assertEquals(1, $params['quantity']);
        $this->assertEquals(1000, $params['price']);
        $this->assertEquals("GTC", $params['timeInForce']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->SPOT_ORDER_PREFIX));
    }

    public function testSpotReplaceOrder()
    {
        try  {
            $this->binance->replaceOrder('BUY', 'BTCUSDT', 1, 1000, 'LIMIT', '123456789');
        } catch(\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/order/cancelReplace", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals("BTCUSDT", $params['symbol']);
        $this->assertEquals("BUY", $params['side']);
        $this->assertEquals("LIMIT", $params['type']);
        $this->assertEquals(1, $params['quantity']);
        $this->assertEquals(1000, $params['price']);
        $this->assertEquals("GTC", $params['timeInForce']);
        $this->assertEquals('123456789', $params['cancelOrderId']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->SPOT_ORDER_PREFIX));
    }

    public function testSpotEditOrder()
    {
        try  {
            $this->binance->editOrder('ETHUSDT', 0.009, '123456789');
        } catch(\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/order/amend/keepPriority?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals("ETHUSDT", $params['symbol']);
        $this->assertEquals(0.009, $params['newQty']);
        $this->assertEquals('123456789', $params['orderId']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->SPOT_ORDER_PREFIX));
    }

    public function testSpotBuy()
    {
        try  {
            $this->binance->buy($this->symbol, $this->quantity, $this->price, $this->type);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/order", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->quantity, $params['quantity']);
        $this->assertEquals($this->price, $params['price']);
        $this->assertEquals($this->type, $params['type']);
        $this->assertEquals("GTC", $params['timeInForce']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->SPOT_ORDER_PREFIX));
    }

    public function testSpotBuyTest()
    {
        try  {
            $this->binance->buyTest($this->symbol, $this->quantity, $this->price, $this->type);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/order/test", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->quantity, $params['quantity']);
        $this->assertEquals($this->price, $params['price']);
        $this->assertEquals($this->type, $params['type']);
        $this->assertEquals("GTC", $params['timeInForce']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->SPOT_ORDER_PREFIX));
    }

    public function testSpotSell()
    {
        try  {
            $this->binance->sell($this->symbol, $this->quantity, $this->price, $this->type);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/order", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->quantity, $params['quantity']);
        $this->assertEquals($this->price, $params['price']);
        $this->assertEquals($this->type, $params['type']);
        $this->assertEquals("GTC", $params['timeInForce']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->SPOT_ORDER_PREFIX));
    }

    public function testSpotSellTest()
    {
        try  {
            $this->binance->sellTest($this->symbol, $this->quantity, $this->price, $this->type);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/order/test", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->quantity, $params['quantity']);
        $this->assertEquals($this->price, $params['price']);
        $this->assertEquals($this->type, $params['type']);
        $this->assertEquals("GTC", $params['timeInForce']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->SPOT_ORDER_PREFIX));
    }

    public function testSpotMarketQuoteBuy()
    {
        try  {
            $this->binance->marketQuoteBuy($this->symbol, $this->quantity);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/order", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->quantity, $params['quoteOrderQty']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->SPOT_ORDER_PREFIX));
    }

    public function testSpotMarketQuoteBuyTest()
    {
        try  {
            $this->binance->marketQuoteBuyTest($this->symbol, $this->quantity);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/order/test", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->quantity, $params['quoteOrderQty']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->SPOT_ORDER_PREFIX));
    }

    public function testSpotMarketBuy()
    {
        try  {
            $this->binance->marketBuy($this->symbol, $this->quantity);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/order", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->quantity, $params['quantity']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->SPOT_ORDER_PREFIX));
    }

    public function testSpotMarketBuyTest()
    {
        try  {
            $this->binance->marketBuyTest($this->symbol, $this->quantity);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/order/test", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->quantity, $params['quantity']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->SPOT_ORDER_PREFIX));
    }

    public function testSpotMarketQuoteSell()
    {
        try  {
            $this->binance->marketQuoteSell($this->symbol, '1');

        } catch (\Throwable $e) {

        }
        // warns here cuz method needs information fetched by exchangeInfo
        $this->assertEquals("https://api.binance.com/api/v3/order", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals('SELL', $params['side']);
        $this->assertEquals('MARKET', $params['type']);
        $this->assertEquals(1, $params['quoteOrderQty']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->SPOT_ORDER_PREFIX));
    }

    public function testSpotMarketQuoteSellTest()
    {
        try  {
            $this->binance->marketQuoteSellTest($this->symbol, 1);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/order/test", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals(1, $params['quoteOrderQty']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->SPOT_ORDER_PREFIX));
    }

    public function testSpotMarketSell()
    {
        try  {
            $this->binance->marketSell($this->symbol, 1);

        } catch (\Throwable $e) {

        }
        // warns here cuz method needs information fetched by exchangeInfo
        $this->assertEquals("https://api.binance.com/api/v3/order", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals(1, $params['quantity']);
        $this->assertEquals('SELL', $params['side']);
        $this->assertEquals('MARKET', $params['type']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->SPOT_ORDER_PREFIX));
    }

    public function testSpotMarketSellTest()
    {
        try  {
            $this->binance->marketSellTest($this->symbol, $this->quantity);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/order/test", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->quantity, $params['quantity']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->SPOT_ORDER_PREFIX));
    }

    public function testSpotCancel()
    {
        try  {
            $this->binance->cancel($this->symbol, $this->orderid);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/order?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->orderid, $params['orderId']);

    }

    public function testSpotOrderStatus()
    {
        try  {
            $this->binance->orderStatus($this->symbol, $this->orderid);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/order?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->orderid, $params['orderId']);

    }

    public function testSpotOpenOrders()
    {
        try  {
            $this->binance->openOrders($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/openOrders?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);

    }

    public function testSpotCancelOpenOrders()
    {
        try  {
            $this->binance->cancelOpenOrders($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/openOrders?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);

    }

    public function testSpotOrders()
    {
        try  {
            $this->binance->orders($this->symbol, $this->limit, $this->fromOrderId);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/allOrders?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->fromOrderId, $params['orderId']);

    }

    public function testSpotHistory()
    {
        try  {
            $this->binance->history($this->symbol, $this->limit, $this->fromTradeId, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/myTrades?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->fromTradeId, $params['fromId']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);

    }

    public function testSpotMyTrades()
    {
        try  {
            $this->binance->myTrades($this->symbol, $this->limit, $this->fromTradeId, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/myTrades?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->fromTradeId, $params['fromId']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);

    }

    public function testUseServerTime()
    {
        try  {
            $this->binance->useServerTime();

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/time", self::$capturedUrl);
    }

    public function testTime()
    {
        try  {
            $this->binance->time();

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/time", self::$capturedUrl);
    }

    public function testSpotExchangeInfo()
    {
        try  {
            $this->binance->exchangeInfo($this->symbols);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/exchangeInfo?";
        $this->assertTrue(str_starts_with (self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);
        $this->assertTrue(!empty($params['symbols']));
        $symbols = $params['symbols'];
        $this->assertTrue(str_contains($symbols, $this->symbols[0]));
        $this->assertTrue(str_contains($symbols, $this->symbols[1]));
    }

    public function testAssetDetail()
    {
        try  {
            $this->binance->assetDetail($this->asset);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/sapi/v1/asset/assetDetail?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->asset, $params['asset']);

    }

    public function testSpotDustLog()
    {
        try  {
            $this->binance->dustLog($this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/sapi/v1/asset/dribblet?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);

    }

    public function testSpotTradeFee()
    {
        try  {
            $this->binance->tradeFee($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/sapi/v1/asset/tradeFee?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);

    }

    public function testSpotCommissionFee()
    {
        try  {
            $this->binance->commissionFee($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/sapi/v1/asset/tradeFee?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);

    }

    public function testWithdraw()
    {
        try  {
            $this->binance->withdraw($this->asset, $this->address, $this->amount, $this->addressTag, $this->addressName, $this->transactionFeeFlag, $this->network, $this->orderId);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/sapi/v1/capital/withdraw/apply", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->asset, $params['coin']);
        $this->assertEquals($this->address, $params['address']);
        $this->assertEquals($this->amount, $params['amount']);
        $this->assertEquals($this->addressTag, $params['addressTag']);
        $this->assertEquals($this->addressName, $params['name']);
        $this->assertEquals($this->transactionFeeFlag, $params['transactionFeeFlag']);
        $this->assertEquals($this->network, $params['network']);
        $this->assertEquals($this->orderId, $params['withdrawOrderId']);
    }

    public function testDepositAddress()
    {
        try  {
            $this->binance->depositAddress($this->asset, $this->network);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/sapi/v1/capital/deposit/address?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->asset, $params['coin']);
        $this->assertEquals($this->network, $params['network']);
    }

    public function testDepositHistory()
    {
        try  {
            $this->binance->depositHistory($this->asset);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/sapi/v1/capital/deposit/hisrec?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->asset, $params['coin']);
    }

    public function testWithdrawHistory()
    {
        try  {
            $this->binance->withdrawHistory($this->asset);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/sapi/v1/capital/withdraw/history?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->asset, $params['coin']);

    }

    public function testWithdrawFee()
    {
        try  {
            $this->binance->withdrawFee($this->asset);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/sapi/v1/asset/assetDetail";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));
    }

    public function testTransfer()
    {
        try  {
            $this->binance->transfer($this->type, $this->asset, $this->amount, $this->fromSymbol, $this->toSymbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/sapi/v1/asset/transfer", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->type, $params['type']);
        $this->assertEquals($this->asset, $params['asset']);
        $this->assertEquals($this->amount, $params['amount']);
        $this->assertEquals($this->fromSymbol, $params['fromSymbol']);
        $this->assertEquals($this->toSymbol, $params['toSymbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testTransfersHistory()
    {
        try  {
            $this->binance->transfersHistory($this->type, $this->startTime, $this->endTime, $this->limit, $this->current, $this->fromSymbol, $this->toSymbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/sapi/v1/asset/transfer?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->type, $params['type']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->limit, $params['size']);
        $this->assertEquals($this->current, $params['current']);
        $this->assertEquals($this->fromSymbol, $params['fromSymbol']);
        $this->assertEquals($this->toSymbol, $params['toSymbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testSpotPrices()
    {
        try  {
            $this->binance->prices();

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/ticker/price", self::$capturedUrl);
    }

    public function testSpotPrice()
    {
        try  {
            $this->binance->price($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/ticker/price?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
    }

    public function testSpotBookPrices()
    {
        try  {
            $this->binance->bookPrices();

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/ticker/bookTicker", self::$capturedUrl);
    }

    public function testSpotAccount()
    {
        try  {
            $this->binance->account();

        } catch (\Throwable $e) {

        }
        $this->assertTrue(str_starts_with(self::$capturedUrl, "https://api.binance.com/api/v3/account"));
    }

    public function testSpotPrevDay()
    {
        try  {
            $this->binance->prevDay($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/ticker/24hr?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
    }

    public function testSpotTradingDay()
    {
        try  {
            $this->binance->tradingDay(null, $this->symbols);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/ticker/tradingDay?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals(json_encode($this->symbols), $params['symbols']);
    }

    public function testSpotRollingWindowPriceChange()
    {
        try  {
            $this->binance->rollingWindowPriceChange($this->symbol, null, '2d');

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/ticker?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals('2d', $params['windowSize']);
    }

    public function testSpotAggTrades()
    {
        try  {
            $this->binance->aggTrades($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/aggTrades?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);

    }

    public function testSpotHistoricalTrades()
    {
        try  {
            $this->binance->historicalTrades($this->symbol, $this->limit);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/trades?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->limit, $params['limit']);

    }

    public function testSpotHistoricalTradesWithTradeId()
    {
        try  {
            $this->binance->historicalTrades($this->symbol, $this->limit, $this->tradeId);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/historicalTrades?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->tradeId, $params['fromId']);

    }

    public function testSpotDepth()
    {
        try  {
            $this->binance->depth($this->symbol, $this->limit);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/depth?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->limit, $params['limit']);
    }

    public function testSpotBalances()
    {
        try  {
            $this->binance->balances();

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/account";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        parse_str(self::$capturedBody, $params);
    }

    public function testFuturesBalances()
    {
        try  {
            $this->binance->balances('futures', [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v2/balance?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testFuturesBalancesV3()
    {
        try  {
            $this->binance->balances('futures', [ 'recvWindow' => $this->recvWindow ], 'v3');

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v3/balance?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testCoins()
    {
        try  {
            $this->binance->coins();

        } catch (\Throwable $e) {

        }
        $this->assertTrue(str_starts_with(self::$capturedUrl, "https://api.binance.com/sapi/v1/capital/config/getall"));
    }

    public function testSpotCandlesticks()
    {
        try  {
            $this->binance->candlesticks($this->symbol, $this->interval, $this->limit, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/klines?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->interval, $params['interval']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
    }

    public function testSpotUiCandlesticks()
    {
        try  {
            $this->binance->uiCandlesticks($this->symbol, $this->interval, $this->limit, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/uiKlines?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->interval, $params['interval']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
    }

    public function testSpotAccountSnapshot()
    {
        try  {
            $this->binance->accountSnapshot('SPOT', $this->nbrDays, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/sapi/v1/accountSnapshot?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals('SPOT', $params['type']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->nbrDays, $params['limit']);

    }

    public function testMarginAccountSnapshot()
    {
        try  {
            $this->binance->accountSnapshot('MARGIN', $this->nbrDays, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/sapi/v1/accountSnapshot?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals('MARGIN', $params['type']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->nbrDays, $params['limit']);

    }

    public function testPreventedMatches()
    {
        try  {
            $this->binance->preventedMatches($this->symbol, null, $this->orderId);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/myPreventedMatches?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->orderId, $params['orderId']);

    }

    public function testAllocations()
    {
        try  {
            $this->binance->allocations($this->symbol, $this->startTime, $this->endTime, $this->fromId, $this->limit, $this->orderId);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/myAllocations?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->fromId, $params['fromAllocationId']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->orderId, $params['orderId']);
    }

    public function testCommissionRate()
    {
        try  {
            $this->binance->commissionRate($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/account/commission?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
    }

    public function testOrderAmendments()
    {
        try  {
            $this->binance->orderAmendments($this->symbol, $this->orderId);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/order/amendments?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->orderId, $params['orderId']);
    }

    public function testFuturesAccountSnapshot()
    {
        try  {
            $this->binance->accountSnapshot('FUTURES', $this->nbrDays, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/sapi/v1/accountSnapshot?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals('FUTURES', $params['type']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->nbrDays, $params['limit']);

    }

    public function testAccountStatus()
    {
        try  {
            $this->binance->accountStatus();

        } catch (\Throwable $e) {

        }
        $this->assertTrue(str_starts_with(self::$capturedUrl, "https://api.binance.com/sapi/v1/account/status"));
    }

    public function testApiRestrictions()
    {
        try  {
            $this->binance->apiRestrictions();

        } catch (\Throwable $e) {

        }
        $this->assertTrue(str_starts_with(self::$capturedUrl, "https://api.binance.com/sapi/v1/account/apiRestrictions"));
    }

    public function testApiTradingStatus()
    {
        try  {
            $this->binance->apiTradingStatus();

        } catch (\Throwable $e) {

        }
        $this->assertTrue(str_starts_with(self::$capturedUrl, "https://api.binance.com/sapi/v1/account/apiTradingStatus"));
    }

    public function testOcoOrder()
    {
        try  {
            $this->binance->ocoOrder($this->side, $this->symbol, $this->quantity, $this->price, $this->stopprice, $this->stoplimitprice);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://api.binance.com/api/v3/order/oco", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->side, $params['side']);
        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->quantity, $params['quantity']);
        $this->assertEquals($this->price, $params['price']);
        $this->assertEquals($this->stopprice, $params['stopPrice']);
        $this->assertEquals($this->stoplimitprice, $params['stopLimitPrice']);
        $this->assertEquals('GTC', $params['stopLimitTimeInForce']);
    }

    public function testSpotAvgPrice()
    {
        try  {
            $this->binance->avgPrice($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/avgPrice?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
    }

    public function testBswapQuote()
    {
        try  {
            $this->binance->bswapQuote($this->baseAsset, $this->quoteAsset, $this->quoteQty);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/sapi/v1/bswap/quote?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->quoteAsset, $params['quoteAsset']);
        $this->assertEquals($this->baseAsset, $params['baseAsset']);
        $this->assertEquals($this->quoteQty, $params['quoteQty']);

    }

    public function testFuturesTime()
    {
        try  {
            $this->binance->futuresTime();

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://fapi.binance.com/fapi/v1/time", self::$capturedUrl);
    }

    public function testFuturesExchangeInfo()
    {
        try  {
            $this->binance->futuresExchangeInfo();

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://fapi.binance.com/fapi/v1/exchangeInfo", self::$capturedUrl);
    }

    public function testFuturesDepth()
    {
        try  {
            $this->binance->futuresDepth($this->symbol, $this->limit);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/depth?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->limit, $params['limit']);
    }

    public function testFuturesRecentTrades()
    {
        try  {
            $this->binance->futuresRecentTrades($this->symbol, $this->limit);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/trades?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->limit, $params['limit']);

    }

    public function testFuturesHistoricalTrades()
    {
        try  {
            $this->binance->futuresHistoricalTrades($this->symbol, $this->limit, $this->tradeId);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/historicalTrades?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->tradeId, $params['fromId']);

    }

    public function testFuturesAggTrades()
    {
        try  {
            $this->binance->futuresAggTrades($this->symbol, $this->fromId, $this->startTime, $this->endTime, $this->limit);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/aggTrades?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->fromId, $params['fromId']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->limit, $params['limit']);

    }

    public function testFuturesCandlesticks()
    {
        try  {
            $this->binance->futuresCandlesticks($this->symbol, $this->interval, $this->limit, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/klines?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->interval, $params['interval']);
        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);

    }

    public function testFuturesContinuousCandlesticks()
    {
        try  {
            $this->binance->futuresContinuousCandlesticks($this->symbol, $this->interval, $this->limit, $this->startTime, $this->endTime, $this->contractType);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/continuousKlines?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->interval, $params['interval']);
        $this->assertEquals($this->symbol, $params['pair']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->contractType, $params['contractType']);

    }

    public function testFuturesIndexPriceCandlesticks()
    {
        try  {
            $this->binance->futuresIndexPriceCandlesticks($this->symbol, $this->interval, $this->limit, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/indexPriceKlines?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->interval, $params['interval']);
        $this->assertEquals($this->symbol, $params['pair']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);

    }

    public function testFuturesMarkPriceCandlesticks()
    {
        try  {
            $this->binance->futuresMarkPriceCandlesticks($this->symbol, $this->interval, $this->limit, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/markPriceKlines?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->interval, $params['interval']);
        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);

    }

    public function testFuturesPremiumIndexCandlesticks()
    {
        try  {
            $this->binance->futuresPremiumIndexCandlesticks($this->symbol, $this->interval, $this->limit, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/premiumIndexKlines?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->interval, $params['interval']);
        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
    }

    public function testFuturesMarkPrice()
    {
        try  {
            $this->binance->futuresMarkPrice($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/premiumIndex?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);

    }

    public function testFuturesFundingRateHistory()
    {
        try  {
            $this->binance->futuresFundingRateHistory($this->symbol, $this->limit, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/fundingRate?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);

    }

    public function testFuturesFundingInfo()
    {
        try  {
            $this->binance->futuresFundingInfo();

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://fapi.binance.com/fapi/v1/fundingInfo", self::$capturedUrl);
    }

    public function testFuturesPrevDay()
    {
        try  {
            $this->binance->futuresPrevDay($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/ticker/24hr?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);

    }

    public function testFuturesPrice()
    {
        try  {
            $this->binance->futuresPrice($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/ticker/price?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);

    }

    public function testFuturesPrices()
    {
        try  {
            $this->binance->futuresPrices();

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://fapi.binance.com/fapi/v1/ticker/price", self::$capturedUrl);
    }

    public function testFuturesPriceV2()
    {
        try  {
            $this->binance->futuresPriceV2($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v2/ticker/price?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
    }

    public function testFuturesSymbolOrderBookTicker()
    {
        try  {
            $this->binance->futuresSymbolOrderBookTicker($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/ticker/bookTicker?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);

    }

    public function testFuturesDeliveryPrice()
    {
        try  {
            $this->binance->futuresDeliveryPrice($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/futures/data/delivery-price?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['pair']);

    }

    public function testFuturesOpenInterest()
    {
        try  {
            $this->binance->futuresOpenInterest($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/openInterest?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);

    }

    public function testFuturesOpenInterestHistory()
    {
        try  {
            $this->binance->futuresOpenInterestHistory($this->symbol, $this->period, $this->limit, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/futures/data/openInterestHist?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->period, $params['period']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);

    }

    public function testFuturesTopLongShortPositionRatio()
    {
        try  {
            $this->binance->futuresTopLongShortPositionRatio($this->symbol, $this->period, $this->limit, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/futures/data/topLongShortPositionRatio?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->period, $params['period']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);

    }

    public function testFuturesTopLongShortAccountRatio()
    {
        try  {
            $this->binance->futuresTopLongShortAccountRatio($this->symbol, $this->period, $this->limit, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/futures/data/topLongShortAccountRatio?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->period, $params['period']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);

    }

    public function testFuturesGlobalLongShortAccountRatio()
    {
        try  {
            $this->binance->futuresGlobalLongShortAccountRatio($this->symbol, $this->period, $this->limit, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/futures/data/globalLongShortAccountRatio?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->period, $params['period']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);

    }

    public function testFuturesTakerLongShortRatio()
    {
        try  {
            $this->binance->futuresTakerLongShortRatio($this->symbol, $this->period, $this->limit, $this->startTime, $this->endTime);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/futures/data/takerlongshortRatio?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->period, $params['period']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);

    }

    public function testFuturesBasis()
    {
        try  {
            $this->binance->futuresBasis($this->symbol, $this->period, $this->limit, $this->startTime, $this->endTime, $this->contractType);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/futures/data/basis?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['pair']);
        $this->assertEquals($this->period, $params['period']);
        $this->assertEquals($this->contractType, $params['contractType']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);

    }

    public function testFuturesIndexInfo()
    {
        try  {
            $this->binance->futuresIndexInfo($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/indexInfo?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);

    }

    public function testFuturesAssetIndex()
    {
        try  {
            $this->binance->futuresAssetIndex($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/assetIndex?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);

    }

    public function testFuturesConstituents()
    {
        try  {
            $this->binance->futuresConstituents($this->symbol);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/constituents?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);

    }

    public function testFuturesOrder()
    {
        try  {
            $this->binance->futuresOrder('BUY', 'BTCUSDT', 1, 1000);
        } catch(\Throwable $e) {

        }
        $this->assertEquals("https://fapi.binance.com/fapi/v1/order", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals("BTCUSDT", $params['symbol']);
        $this->assertEquals("BUY", $params['side']);
        $this->assertEquals("LIMIT", $params['type']);
        $this->assertEquals(1, $params['quantity']);
        $this->assertEquals(1000, $params['price']);
        $this->assertEquals("GTC", $params['timeInForce']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->CONTRACT_ORDER_PREFIX));
    }

    public function testFuturesAlgoOrder()
    {
        try {
            $this->binance->futuresOrder('BUY', 'BTCUSDT', 1, 1000, 'STOP', [
                'stopPrice' => 900,
            ]);
        }
        catch(\Throwable $e) {
            print_r($e->getMessage());
        }
        $this->assertEquals("https://fapi.binance.com/fapi/v1/algoOrder", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals("BTCUSDT", $params['symbol']);
        $this->assertEquals("BUY", $params['side']);
        $this->assertEquals("STOP", $params['type']);
        $this->assertEquals(1, $params['quantity']);
        $this->assertEquals(1000, $params['price']);
        $this->assertEquals(900, $params['triggerPrice']);
        $this->assertEquals("CONDITIONAL", $params['algoType']);
        $this->assertTrue(str_starts_with($params['clientAlgoId'], $this->CONTRACT_ORDER_PREFIX));
    }

    public function testFuturesBuy()
    {
        try  {
            $this->binance->futuresBuy($this->symbol, $this->quantity, $this->price, $this->type);

        } catch (\Throwable $e) {

        }

        $endpoint = "https://fapi.binance.com/fapi/v1/order";
        $this->assertEquals($endpoint, self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->quantity, $params['quantity']);
        $this->assertEquals($this->price, $params['price']);
        $this->assertEquals($this->type, $params['type']);
        $this->assertEquals("BUY", $params['side']);
        $this->assertEquals("GTC", $params['timeInForce']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->CONTRACT_ORDER_PREFIX));
    }

    public function testFuturesBuyTest()
    {
        try  {
            $this->binance->futuresBuyTest($this->symbol, $this->quantity, $this->price, $this->type);

        } catch (\Throwable $e) {

        }

        $endpoint = "https://fapi.binance.com/fapi/v1/order/test";
        $this->assertEquals($endpoint, self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->quantity, $params['quantity']);
        $this->assertEquals($this->price, $params['price']);
        $this->assertEquals($this->type, $params['type']);
        $this->assertEquals("BUY", $params['side']);
        $this->assertEquals("GTC", $params['timeInForce']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->CONTRACT_ORDER_PREFIX));
    }

    public function testFuturesSell()
    {
        try  {
            $this->binance->futuresSell($this->symbol, $this->quantity, $this->price, $this->type);

        } catch (\Throwable $e) {

        }

        $endpoint = "https://fapi.binance.com/fapi/v1/order";
        $this->assertEquals($endpoint, self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->quantity, $params['quantity']);
        $this->assertEquals($this->price, $params['price']);
        $this->assertEquals($this->type, $params['type']);
        $this->assertEquals("SELL", $params['side']);
        $this->assertEquals("GTC", $params['timeInForce']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->CONTRACT_ORDER_PREFIX));
    }

    public function testFuturesSellTest()
    {
        try  {
            $this->binance->futuresSellTest($this->symbol, $this->quantity, $this->price, $this->type);

        } catch (\Throwable $e) {

        }

        $endpoint = "https://fapi.binance.com/fapi/v1/order/test";
        $this->assertEquals($endpoint, self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->quantity, $params['quantity']);
        $this->assertEquals($this->price, $params['price']);
        $this->assertEquals($this->type, $params['type']);
        $this->assertEquals("SELL", $params['side']);
        $this->assertEquals("GTC", $params['timeInForce']);
        $this->assertTrue(str_starts_with($params['newClientOrderId'], $this->CONTRACT_ORDER_PREFIX));
    }

    public function testFuturesBatchOrders()
    {
        $order = [
            'symbol' => $this->symbol,
            'side' => $this->side,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'type' => $this->type,
        ];

        try  {
            $this->binance->futuresBatchOrders([ $order ], [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/batchOrders?batchOrders=";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $ordersString = substr(self::$capturedUrl, strlen($endpoint));

        $orders = json_decode(urldecode($ordersString), true);
        $this->assertEquals($this->symbol, $orders[0]['symbol']);
        $this->assertEquals($this->side, $orders[0]['side']);
        $this->assertEquals($this->quantity, $orders[0]['quantity']);
        $this->assertEquals($this->price, $orders[0]['price']);
        $this->assertEquals($this->type, $orders[0]['type']);
        $this->assertEquals("GTC", $orders[0]['timeInForce']);
        $this->assertTrue(str_starts_with($orders[0]['newClientOrderId'], $this->CONTRACT_ORDER_PREFIX));

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesEditOrder()
    {
        try  {
            $this->binance->futuresEditOrder($this->symbol, $this->side, $this->quantity, $this->price, $this->orderId);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/order?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $orderString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($orderString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->side, $params['side']);
        $this->assertEquals($this->quantity, $params['quantity']);
        $this->assertEquals($this->price, $params['price']);
        $this->assertEquals($this->orderId, $params['orderId']);
        $this->assertEquals('GTC', $params['timeInForce']);
    }

    public function testFuturesEditOrders()
    {
        $order = [
            'symbol' => $this->symbol,
            'side' => $this->side,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'orderId' => $this->orderId,
        ];

        try  {
            $this->binance->futuresEditOrders([ $order ], [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/batchOrders?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);

        $orders = json_decode($params['batchOrders'], true);
        $this->assertEquals($this->symbol, $orders[0]['symbol']);
        $this->assertEquals($this->side, $orders[0]['side']);
        $this->assertEquals($this->quantity, $orders[0]['quantity']);
        $this->assertEquals($this->price, $orders[0]['price']);
        $this->assertEquals("GTC", $orders[0]['timeInForce']);
    }

    public function testFuturesOrderAmendment()
    {
        try  {
            $this->binance->futuresOrderAmendment($this->symbol, $this->orderId, $this->origClientOrderId, $this->startTime, $this->endTime, $this->limit, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/orderAmendment?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->orderId, $params['orderId']);
        $this->assertEquals($this->origClientOrderId, $params['origClientOrderId']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testFuturesCancel()
    {
        try  {
            $this->binance->futuresCancel($this->symbol, $this->orderid);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/order?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->orderid, $params['orderId']);

    }

    public function testFuturesCancelAlgo()
    {
        try  {
            $this->binance->futuresCancelAlgo($this->orderid);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/algoOrder?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->orderid, $params['algoId']);

    }

    public function testFuturesCancelBatchOrdersByOrderIds()
    {
        try  {
            $this->binance->futuresCancelBatchOrders($this->symbol, $this->orderIdList, null, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/batchOrders?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals('[123456,654321]', $params['orderIdList']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesCancelBatchOrdersByClientOrderIds()
    {
        try  {
            $this->binance->futuresCancelBatchOrders($this->symbol, null, $this->origClientOrderIdList, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/batchOrders?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals('["test client order id 1","test client order id 2"]', $params['origClientOrderIdList']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesCancelOpenOrders()
    {
        try  {
            $this->binance->futuresCancelOpenOrders($this->symbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/allOpenOrders?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testFuturesCancelOpenAlgoOrders()
    {
        try {
            $this->binance->futuresCancelOpenAlgoOrders($this->symbol, [ 'recvWindow' => $this->recvWindow ]);
        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/algoOpenOrders?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testFuturesCountdownCancelAllOrders()
    {
        try  {
            $this->binance->futuresCountdownCancelAllOrders($this->symbol, $this->countdownTime, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://fapi.binance.com/fapi/v1/countdownCancelAll", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->countdownTime, $params['countdownTime']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesOrderStatusByOrderId()
    {
        try  {
            $this->binance->futuresOrderStatus($this->symbol, $this->orderId, null, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/order?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->orderId, $params['orderId']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testFuturesAlgoOrderStatusByOrderId()
    {
        try  {
            $this->binance->futuresAlgoOrderStatus($this->orderId, null, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/algoOrder?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->orderId, $params['algoId']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testFuturesOrderStatusByClientOrderId()
    {
        try  {
            $this->binance->futuresOrderStatus($this->symbol, null, $this->origClientOrderId, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/order?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->origClientOrderId, $params['origClientOrderId']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testFuturesAlgoOrderStatusByClientOrderId()
    {
        try  {
            $this->binance->futuresAlgoOrderStatus(null, $this->origClientOrderId, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/algoOrder?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->origClientOrderId, $params['clientAlgoId']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testFuturesAllOrders()
    {
        try  {
            $this->binance->futuresAllOrders($this->symbol, $this->startTime, $this->endTime, $this->limit, $this->orderId, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/allOrders?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->orderId, $params['orderId']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testFuturesAllAlgoOrders()
    {
        try  {
            $this->binance->futuresAllAlgoOrders($this->symbol, $this->startTime, $this->endTime, $this->limit, $this->orderId, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/allAlgoOrders?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->orderId, $params['algoId']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testFuturesOpenOrders()
    {
        try  {
            $this->binance->futuresOpenOrders($this->symbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/openOrders?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testFuturesOpenAlgoOrders()
    {
        try  {
            $this->binance->futuresOpenAlgoOrders($this->symbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/openAlgoOrders?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testFuturesOpenOrderByOrderId()
    {
        try  {
            $this->binance->futuresOpenOrder($this->symbol, $this->orderId, null, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/openOrder?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->orderId, $params['orderId']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testFuturesOpenOrderByClientOrderId()
    {
        try  {
            $this->binance->futuresOpenOrder($this->symbol, null, $this->origClientOrderId, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/openOrder?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->origClientOrderId, $params['origClientOrderId']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testFuturesForceOrders()
    {
        try  {
            $this->binance->futuresForceOrders($this->symbol, $this->startTime, $this->endTime, $this->limit, $this->autoCloseType, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }

        $endpoint = "https://fapi.binance.com/fapi/v1/forceOrders?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->autoCloseType, $params['autoCloseType']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesMyTrades()
    {
        try  {
            $this->binance->futuresMyTrades($this->symbol, $this->startTime, $this->endTime, $this->limit, $this->orderId, $this->fromId, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/userTrades?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->orderId, $params['orderId']);
        $this->assertEquals($this->fromId, $params['fromId']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesHistory()
    {
        try  {
            $this->binance->futuresHistory($this->symbol, $this->startTime, $this->endTime, $this->limit, $this->orderId, $this->fromId, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/userTrades?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->orderId, $params['orderId']);
        $this->assertEquals($this->fromId, $params['fromId']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesSetMarginMode()
    {
        try  {
            $this->binance->futuresSetMarginMode($this->symbol, $this->marginType, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://fapi.binance.com/fapi/v1/marginType", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->marginType, $params['marginType']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesPositionMode()
    {
        try  {
            $this->binance->futuresPositionMode([ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/positionSide/dual?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesSetPositionMode()
    {
        try  {
            $this->binance->futuresSetPositionMode($this->dualSidePosition, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://fapi.binance.com/fapi/v1/positionSide/dual", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->dualSidePosition, $params['dualSidePosition']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesSetLeverage()
    {
        try  {
            $this->binance->futuresSetLeverage($this->leverage, $this->symbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://fapi.binance.com/fapi/v1/leverage", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->leverage, $params['leverage']);
        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesMultiAssetsMarginMode()
    {
        try  {
            $this->binance->futuresMultiAssetsMarginMode([ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/multiAssetsMargin?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->recvWindow, $params['recvWindow']);

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->recvWindow, $params['recvWindow']);

    }

    public function testFuturesSetMultiAssetsMarginMode()
    {
        try  {
            $this->binance->futuresSetMultiAssetsMarginMode($this->multiAssetsMarginMode, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://fapi.binance.com/fapi/v1/multiAssetsMarginMode", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->multiAssetsMarginMode, $params['multiAssetsMarginMode']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesAddMargin()
    {
        try  {
            $this->binance->futuresAddMargin($this->symbol, $this->amount, $this->positionSide, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/positionMargin";
        $this->assertEquals($endpoint, self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->amount, $params['amount']);
        $this->assertEquals($this->positionSide, $params['positionSide']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
        $this->assertEquals(1, $params['type']);
    }

    public function testFuturesReduceMargin()
    {
        try  {
            $this->binance->futuresReduceMargin($this->symbol, $this->amount, $this->positionSide, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/positionMargin";
        $this->assertEquals($endpoint, self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->amount, $params['amount']);
        $this->assertEquals($this->positionSide, $params['positionSide']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
        $this->assertEquals(2, $params['type']);
    }

    public function testFuturesPositions()
    {
        try  {
            $this->binance->futuresPositions($this->symbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v3/positionRisk?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesPositionsV2()
    {
        try  {
            $this->binance->futuresPositionsV2($this->symbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v2/positionRisk?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesPositionsV3()
    {
        try  {
            $this->binance->futuresPositionsV3($this->symbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v3/positionRisk?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesPosition()
    {
        try  {
            $this->binance->futuresPosition($this->symbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v3/positionRisk?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesPositionV2()
    {
        try  {
            $this->binance->futuresPositionV2($this->symbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v2/positionRisk?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesPositionV3()
    {
        try  {
            $this->binance->futuresPositionV3($this->symbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v3/positionRisk?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesAdlQuantile()
    {
        try  {
            $this->binance->futuresAdlQuantile($this->symbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/adlQuantile?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesPositionMarginChangeHistory()
    {
        try  {
            $this->binance->futuresPositionMarginChangeHistory($this->symbol, $this->startTime, $this->endTime, $this->limit, 'ADD', [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/positionMargin/history?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals(1, $params['addOrReduce']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesAccount()
    {
        try  {
            $this->binance->futuresAccount([ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v3/account?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesAccountV2()
    {
        try  {
            $this->binance->futuresAccountV2([ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v2/account?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesAccountV3()
    {
        try  {
            $this->binance->futuresAccountV3([ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v3/account?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesTradeFee()
    {
        try  {
            $this->binance->futuresTradeFee($this->symbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/commissionRate?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesAccountConfig()
    {
        try  {
            $this->binance->futuresAccountConfig([ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/accountConfig?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesMarginModes()
    {
        try  {
            $this->binance->futuresMarginModes($this->symbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/symbolConfig?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesOrderRateLimit()
    {
        try  {
            $this->binance->futuresOrderRateLimit([ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/rateLimit/order?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testOrderRateLimit()
    {
        try  {
            $this->binance->orderRateLimit([ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://api.binance.com/api/v3/rateLimit/order?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesLeverages()
    {
        try  {
            $this->binance->futuresLeverages($this->symbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/leverageBracket?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesLedger()
    {
        try  {
            $this->binance->futuresLedger($this->symbol, $this->incomeType, $this->startTime, $this->endTime, $this->limit, $this->page, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/income?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->incomeType, $params['incomeType']);
        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->limit, $params['limit']);
        $this->assertEquals($this->page, $params['page']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesTradingStatus()
    {
        try  {
            $this->binance->futuresTradingStatus($this->symbol, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/apiTradingStatus?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->symbol, $params['symbol']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesDownloadIdForTransactions()
    {
        try  {
            $this->binance->futuresDownloadIdForTransactions($this->startTime, $this->endTime, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/income/asyn?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesDownloadTransactionsByDownloadId()
    {
        try  {
            $this->binance->futuresDownloadTransactionsByDownloadId($this->downloadId, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/income/asyn/id?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->downloadId, $params['downloadId']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesDownloadIdForOrders()
    {
        try  {
            $this->binance->futuresDownloadIdForOrders($this->startTime, $this->endTime, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/order/asyn?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesDownloadOrdersByDownloadId()
    {
        try  {
            $this->binance->futuresDownloadOrdersByDownloadId($this->downloadId, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/order/asyn/id?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->downloadId, $params['downloadId']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesDownloadIdForTrades()
    {
        try  {
            $this->binance->futuresDownloadIdForTrades($this->startTime, $this->endTime, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/trade/asyn?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->startTime, $params['startTime']);
        $this->assertEquals($this->endTime, $params['endTime']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesDownloadTradesByDownloadId()
    {
        try  {
            $this->binance->futuresDownloadTradesByDownloadId($this->downloadId, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/trade/asyn/id?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->downloadId, $params['downloadId']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesFeeBurn()
    {
        try  {
            $this->binance->futuresFeeBurn(true, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://fapi.binance.com/fapi/v1/feeBurn", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals('true', $params['feeBurn']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testFuturesFeeBurnStatus()
    {
        try  {
            $this->binance->futuresFeeBurnStatus([ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/feeBurn?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testConvertExchangeInfo()
    {
        try  {
            $this->binance->convertExchangeInfo($this->fromAsset, $this->toAsset);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/convert/exchangeInfo?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->fromAsset, $params['fromAsset']);
        $this->assertEquals($this->toAsset, $params['toAsset']);
    }

    public function testConvertSend()
    {
        try  {
            $this->binance->convertSend($this->fromAsset, $this->toAsset, $this->fromAmount, null, $this->validTime, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://fapi.binance.com/fapi/v1/convert/getQuote", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->fromAsset, $params['fromAsset']);
        $this->assertEquals($this->toAsset, $params['toAsset']);
        $this->assertEquals($this->fromAmount, $params['fromAmount']);
        $this->assertEquals($this->validTime, $params['validTime']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testConvertAccept()
    {
        try  {
            $this->binance->convertAccept($this->quoteId, [ 'recvWindow' => $this->recvWindow ]);

        } catch (\Throwable $e) {

        }
        $this->assertEquals("https://fapi.binance.com/fapi/v1/convert/acceptQuote", self::$capturedUrl);

        parse_str(self::$capturedBody, $params);

        $this->assertEquals($this->quoteId, $params['quoteId']);
        $this->assertEquals($this->recvWindow, $params['recvWindow']);
    }

    public function testConvertStatusByOrderId()
    {
        try  {
            $this->binance->convertStatus($this->orderId, null);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/convert/orderStatus?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->orderId, $params['orderId']);
    }

    public function testConvertStatusByQuoteId()
    {
        try  {
            $this->binance->convertStatus(null, $this->quoteId);

        } catch (\Throwable $e) {

        }
        $endpoint = "https://fapi.binance.com/fapi/v1/convert/orderStatus?";
        $this->assertTrue(str_starts_with(self::$capturedUrl, $endpoint));

        $queryString = substr(self::$capturedUrl, strlen($endpoint));
        parse_str($queryString, $params);

        $this->assertEquals($this->quoteId, $params['quoteId']);
    }
}
