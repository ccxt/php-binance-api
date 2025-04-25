<?php

use Binance\API;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php-binance-api.php'; // Adjust path if needed



class BinanceLiveTests extends TestCase
{
    private Binance\API $spotBinance;
    private Binance\API $futuresBinance;

    public function setUp(): void {
        $this->spotBinance = new API('X4BHNSimXOK6RKs2FcKqExquJtHjMxz5hWqF0BBeVnfa5bKFMk7X0wtkfEz0cPrJ', 'x8gLihunpNq0d46F2q0TWJmeCDahX5LMXSlv3lSFNbMI3rujSOpTDKdhbcmPSf2i');
        $this->spotBinance->useTestnet = true;

        $this->futuresBinance = new API('227719da8d8499e8d3461587d19f259c0b39c2b462a77c9b748a6119abd74401', 'b14b935f9cfacc5dec829008733c40da0588051f29a44625c34967b45c11d73c');
        $this->futuresBinance->useTestnet = true;
    }

    // Default values for the tests
    private $symbol = 'ETHUSDT';
    private $quantity = '1.23400000';
    private $price = '1000.00000000';
    private $stopprice = '1100.00000000';
    private $stoplimitprice = '900.00000000';
    private $type = 'LIMIT';
    private $limit = 2;
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
    private $timeInForce = 'GTC';

    private $SPOT_ORDER_PREFIX     = "x-HNA2TXFJ";
	private $CONTRACT_ORDER_PREFIX = "x-Cb7ytekJ";

    public function testPricesSpot()
    {
        $res = $this->spotBinance->prices();
        $this->assertIsArray($res);
        $this->assertArrayHasKey('BTCUSDT', $res);
        $this->assertIsString($res['BTCUSDT']);
    }

    public function testBalanceSpot()
    {
        $res = $this->spotBinance->balances();
        $this->assertIsArray($res);
        // $this->assertArrayHasKey('USDT', $res);
        // $this->assertIsString($res['USDT']['free']);
    }

    public function testBalanceFutures()
    {
        $res = $this->futuresBinance->futuresAccount();
        $this->assertIsArray($res);
        $assets = $res['assets'];
        $first = $assets[0];
        $this->assertArrayHasKey('asset', $first);
        $this->assertArrayHasKey('walletBalance', $first);
    }

    public function testBuyTestSpot()
    {
        $res = $this->spotBinance->buyTest($this->symbol, $this->quantity, $this->price, $this->type);
        $this->assertIsArray($res);
    }

    public function testSellTestSpot()
    {
        $res = $this->spotBinance->sellTest($this->symbol, $this->quantity, $this->price, $this->type);
        $this->assertIsArray($res);
    }

    public function testMarketQuoteBuyTestSpot()
    {
        $res = $this->spotBinance->marketQuoteBuyTest($this->symbol, 10);
        $this->assertIsArray($res);
    }

    public function testMarketBuyTestSpot()
    {
        $res = $this->spotBinance->marketBuyTest($this->symbol, $this->quantity);
        $this->assertIsArray($res);
    }

    public function testMarketQuoteSellTestSpot()
    {
        $res = $this->spotBinance->marketQuoteSellTest($this->symbol, 10);
        $this->assertIsArray($res);
    }

    public function testUseServerTimeSpot()
    {
        $this->spotBinance->useServerTime();
        $offset = $this->spotBinance->info['timeOffset'];
        $this->assertTrue(0 !== $offset);
    }

    public function testTimeSpot()
    {
        $res = $this->spotBinance->time();
        $this->assertIsArray($res);
        $this->assertArrayHasKey('serverTime', $res);
        $this->assertIsInt($res['serverTime']);
    }

    public function testExchangeInfoSpot()
    {
        $res = $this->spotBinance->exchangeInfo($this->symbols);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('timezone', $res);
        $this->assertArrayHasKey('serverTime', $res);
        $this->assertIsInt($res['serverTime']);
        $this->assertArrayHasKey('rateLimits', $res);
        $this->assertIsArray($res['rateLimits']);
        $this->assertArrayHasKey('exchangeFilters', $res);
        $this->assertIsArray($res['exchangeFilters']);
        $this->assertArrayHasKey('symbols', $res);
        $this->assertIsArray($res['symbols']);

        // Check if the symbols are present in the exchange info
        $symbol1 = $this->symbols[0];
        $symbol2 = $this->symbols[1];
        $symbolsInfo = $this->spotBinance->exchangeInfo['symbols'];
        $this->assertArrayHasKey($symbol1, $symbolsInfo);
        $this->assertArrayHasKey($symbol2, $symbolsInfo);
        $this->assertIsArray($symbolsInfo[$symbol1]);
        $this->assertIsArray($symbolsInfo[$symbol2]);
    }

    public function testAccountSpot()
    {
        $res = $this->spotBinance->account();
        $this->assertIsArray($res);
        $this->assertArrayHasKey('makerCommission', $res);
        $this->assertArrayHasKey('takerCommission', $res);
        $this->assertArrayHasKey('buyerCommission', $res);
        $this->assertArrayHasKey('sellerCommission', $res);
        $this->assertArrayHasKey('commissionRates', $res);
        $this->assertIsArray($res['commissionRates']);
        $this->assertArrayHasKey('canTrade', $res);
        $this->assertArrayHasKey('canWithdraw', $res);
        $this->assertArrayHasKey('canDeposit', $res);
        $this->assertArrayHasKey('brokered', $res);
        $this->assertArrayHasKey('requireSelfTradePrevention', $res);
        $this->assertArrayHasKey('preventSor', $res);
        $this->assertArrayHasKey('updateTime', $res);
        $this->assertArrayHasKey('accountType', $res);
        $this->assertEquals('SPOT', $res['accountType']);
        $this->assertArrayHasKey('balances', $res);
        $this->assertIsArray($res['balances']);
    }

    public function testPrevDaySpot()
    {
        $res = $this->spotBinance->prevDay($this->symbol);
        $this->assertIsArray($res);
        $this->assertEquals($this->symbol, $res['symbol']);
        $this->assertArrayHasKey('priceChange', $res);
        $this->assertIsNumeric($res['priceChange']);
        $this->assertArrayHasKey('priceChangePercent', $res);
        $this->assertIsNumeric($res['priceChangePercent']);
        $this->assertArrayHasKey('weightedAvgPrice', $res);
        $this->assertIsNumeric($res['weightedAvgPrice']);
        $this->assertArrayHasKey('prevClosePrice', $res);
        $this->assertIsNumeric($res['prevClosePrice']);
        $this->assertArrayHasKey('lastPrice', $res);
        $this->assertIsNumeric($res['lastPrice']);
        $this->assertArrayHasKey('lastQty', $res);
        $this->assertIsNumeric($res['lastQty']);
        $this->assertArrayHasKey('bidPrice', $res);
        $this->assertIsNumeric($res['bidPrice']);
        $this->assertArrayHasKey('bidQty', $res);
        $this->assertIsNumeric($res['bidQty']);
        $this->assertArrayHasKey('askPrice', $res);
        $this->assertIsNumeric($res['askPrice']);
        $this->assertArrayHasKey('askQty', $res);
        $this->assertIsNumeric($res['askQty']);
        $this->assertArrayHasKey('openPrice', $res);
        $this->assertIsNumeric($res['openPrice']);
        $this->assertArrayHasKey('highPrice', $res);
        $this->assertIsNumeric($res['highPrice']);
        $this->assertArrayHasKey('lowPrice', $res);
        $this->assertIsNumeric($res['lowPrice']);
        $this->assertArrayHasKey('volume', $res);
        $this->assertIsNumeric($res['volume']);
        $this->assertArrayHasKey('quoteVolume', $res);
        $this->assertIsNumeric($res['quoteVolume']);
        $this->assertArrayHasKey('openTime', $res);
        $this->assertIsInt($res['openTime']);
        $this->assertArrayHasKey('closeTime', $res);
        $this->assertIsInt($res['closeTime']);
        $this->assertArrayHasKey('firstId', $res);
        $this->assertIsInt($res['firstId']);
        $this->assertArrayHasKey('lastId', $res);
        $this->assertIsInt($res['lastId']);
        $this->assertArrayHasKey('count', $res);
        $this->assertIsInt($res['count']);
    }

    public function testAggTradesSpot()
    {
        $res = $this->spotBinance->aggTrades($this->symbol);
        $this->assertIsArray($res);
        $this->assertIsArray($res[0]);
        $trade = $res[0];
        $this->assertArrayHasKey('price', $trade);
        $this->assertIsNumeric($trade['price']);
        $this->assertArrayHasKey('quantity', $trade);
        $this->assertIsNumeric($trade['quantity']);
        $this->assertArrayHasKey('timestamp', $trade);
        $this->assertIsInt($trade['timestamp']);
        $this->assertArrayHasKey('maker', $trade);
        $this->assertIsString($trade['maker']);
    }

    public function testHistoricalTradesSpot()
    {
        $res = $this->spotBinance->historicalTrades($this->symbol, $this->limit);
        $this->assertIsArray($res);
        $this->assertIsArray($res[0]);
        $this->assertArrayHasKey('id', $res[0]);
        $this->assertIsNumeric($res[0]['id']);
        $this->assertArrayHasKey('price', $res[0]);
        $this->assertIsNumeric($res[0]['price']);
        $this->assertArrayHasKey('qty', $res[0]);
        $this->assertIsNumeric($res[0]['qty']);
        $this->assertArrayHasKey('time', $res[0]);
        $this->assertIsNumeric($res[0]['time']);
        $this->assertArrayHasKey('isBuyerMaker', $res[0]);
        $this->assertIsBool($res[0]['isBuyerMaker']);
        $this->assertArrayHasKey('isBestMatch', $res[0]);
        $this->assertIsBool($res[0]['isBestMatch']);
    }

    public function testDepthSpot()
    {
        $res = $this->spotBinance->depth($this->symbol, $this->limit);
        $this->assertIsArray($res);

        $this->assertArrayHasKey('bids', $res);
        $this->assertIsArray($res['bids']);
        $this->assertArrayHasKey('asks', $res);
        $this->assertIsArray($res['asks']);
    }

    public function testCandlesticksSpot()
    {
        $res = $this->spotBinance->candlesticks($this->symbol, $this->interval, $this->limit);
        $this->assertIsArray($res);
        $firstKey = array_key_first($res);
        $this->assertIsNumeric($firstKey);
        $candle = $res[$firstKey];
        $this->assertArrayHasKey('open', $candle);
        $this->assertIsNumeric($candle['open']);
        $this->assertArrayHasKey('high', $candle);
        $this->assertIsNumeric($candle['high']);
        $this->assertArrayHasKey('low', $candle);
        $this->assertIsNumeric($candle['low']);
        $this->assertArrayHasKey('close', $candle);
        $this->assertIsNumeric($candle['close']);
        $this->assertArrayHasKey('volume', $candle);
        $this->assertIsNumeric($candle['volume']);
        $this->assertArrayHasKey('openTime', $candle);
        $this->assertIsInt($candle['openTime']);
        $this->assertArrayHasKey('closeTime', $candle);
        $this->assertIsInt($candle['closeTime']);
        $this->assertArrayHasKey('assetVolume', $candle);
        $this->assertIsNumeric($candle['assetVolume']);
        $this->assertArrayHasKey('baseVolume', $candle);
        $this->assertIsNumeric($candle['baseVolume']);
        $this->assertArrayHasKey('trades', $candle);
        $this->assertIsInt($candle['trades']);
        $this->assertArrayHasKey('assetBuyVolume', $candle);
        $this->assertIsNumeric($candle['assetBuyVolume']);
        $this->assertArrayHasKey('takerBuyVolume', $candle);
        $this->assertIsNumeric($candle['takerBuyVolume']);
    }

    public function testUiCandlesticksSpot()
    {
        $res = $this->spotBinance->uiCandlesticks($this->symbol, $this->interval, $this->limit);
        $this->assertIsArray($res);
        $candle = $res[0];
        $this->assertIsInt($candle[0]); // Kline open time
        $this->assertIsNumeric($candle[1]); // Open price
        $this->assertIsNumeric($candle[2]); // High price
        $this->assertIsNumeric($candle[3]); // Low price
        $this->assertIsNumeric($candle[4]); // Close price
        $this->assertIsNumeric($candle[5]); // Volume
        $this->assertIsInt($candle[6]); // Kline close time
        $this->assertIsNumeric($candle[7]); // Quote asset volume
        $this->assertIsInt($candle[8]); // Number of trades
        $this->assertIsNumeric($candle[9]); // Taker buy base asset volume
        $this->assertIsNumeric($candle[10]); // Taker buy quote asset volume
    }

    // could throw an error: https://github.com/ccxt/php-binance-api/actions/runs/14491775733/job/40649647274?pr=511
    // public function testSystemStatusSpot()
    // {
    //     $this->spotBinance->useTestnet = false; // set to false for sapi request
    //     $res = $this->spotBinance->systemStatus();
    //     $this->assertIsArray($res);
    //     $this->assertArrayHasKey('api', $res);
    //     $this->assertIsArray($res['api']);
    //     $this->assertArrayHasKey('status', $res['api']);
    //     $this->assertArrayHasKey('fapi', $res);
    //     $this->assertIsArray($res['fapi']);
    //     $this->assertArrayHasKey('status', $res['fapi']);
    //     $this->assertArrayHasKey('sapi', $res);
    //     $this->assertIsArray($res['sapi']);
    //     $this->assertArrayHasKey('status', $res['sapi']);
    //     $this->spotBinance->useTestnet = true; // reset to true for other tests
    // }

    public function testAvgPriceSpot()
    {
        $res = $this->spotBinance->avgPrice($this->symbol);
        $this->assertIsNumeric($res);
    }

    public function testTimeFutures()
    {
        $res = $this->futuresBinance->futuresTime();
        $this->assertIsArray($res);
        $this->assertArrayHasKey('serverTime', $res);
        $this->assertIsInt($res['serverTime']);
    }

    public function testExchangeInfoFutures()
    {
        $res = $this->futuresBinance->futuresExchangeInfo();
        $this->assertIsArray($res);
        $this->assertArrayHasKey('timezone', $res);
        $this->assertArrayHasKey('serverTime', $res);
        $this->assertIsInt($res['serverTime']);
        $this->assertArrayHasKey('futuresType', $res);
        $this->assertArrayHasKey('rateLimits', $res);
        $this->assertIsArray($res['rateLimits']);
        $this->assertArrayHasKey('exchangeFilters', $res);
        $this->assertIsArray($res['exchangeFilters']);
        $this->assertArrayHasKey('assets', $res);
        $this->assertIsArray($res['assets']);
        $this->assertArrayHasKey('symbols', $res);
        $this->assertIsArray($res['symbols']);
    }

    public function testDepthFutures()
    {
        $res = $this->futuresBinance->futuresDepth($this->symbol, 5);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('bids', $res);
        $this->assertIsArray($res['bids']);
        $this->assertArrayHasKey('asks', $res);
        $this->assertIsArray($res['asks']);
    }

    public function testRecentTradesFutures()
    {
        $res = $this->futuresBinance->futuresRecentTrades($this->symbol, $this->limit);
        $this->assertIsArray($res);
        $this->assertIsArray($res[0]);
        $this->assertArrayHasKey('id', $res[0]);
        $this->assertIsNumeric($res[0]['id']);
        $this->assertArrayHasKey('price', $res[0]);
        $this->assertIsNumeric($res[0]['price']);
        $this->assertArrayHasKey('qty', $res[0]);
        $this->assertIsNumeric($res[0]['qty']);
        $this->assertArrayHasKey('quoteQty', $res[0]);
        $this->assertIsNumeric($res[0]['quoteQty']);
        $this->assertArrayHasKey('time', $res[0]);
        $this->assertIsNumeric($res[0]['time']);
        $this->assertArrayHasKey('isBuyerMaker', $res[0]);
        $this->assertIsBool($res[0]['isBuyerMaker']);
    }

    public function testHistoricalTradesFutures()
    {
        $res = $this->futuresBinance->futuresHistoricalTrades($this->symbol, $this->limit);
        $this->assertIsArray($res);
        $this->assertIsArray($res[0]);
        $this->assertArrayHasKey('id', $res[0]);
        $this->assertIsNumeric($res[0]['id']);
        $this->assertArrayHasKey('price', $res[0]);
        $this->assertIsNumeric($res[0]['price']);
        $this->assertArrayHasKey('qty', $res[0]);
        $this->assertIsNumeric($res[0]['qty']);
        $this->assertArrayHasKey('quoteQty', $res[0]);
        $this->assertIsNumeric($res[0]['quoteQty']);
        $this->assertArrayHasKey('time', $res[0]);
        $this->assertIsNumeric($res[0]['time']);
        $this->assertArrayHasKey('isBuyerMaker', $res[0]);
        $this->assertIsBool($res[0]['isBuyerMaker']);
    }

    public function testAggTradesFutures()
    {
        $res = $this->futuresBinance->futuresAggTrades($this->symbol);
        $this->assertIsArray($res);
        $this->assertIsArray($res[0]);
        $this->assertArrayHasKey('price', $res[0]);
        $this->assertIsNumeric($res[0]['price']);
        $this->assertArrayHasKey('quantity', $res[0]);
        $this->assertIsNumeric($res[0]['quantity']);
        $this->assertArrayHasKey('timestamp', $res[0]);
        $this->assertIsInt($res[0]['timestamp']);
        $this->assertArrayHasKey('maker', $res[0]);
        $this->assertIsString($res[0]['maker']);
    }

    public function testCandlesticksFutures()
    {
        $res = $this->futuresBinance->futuresCandlesticks($this->symbol, $this->interval, $this->limit);
        $this->assertIsArray($res);
        $firstKey = array_key_first($res);
        $this->assertIsNumeric($firstKey);
        $candle = $res[$firstKey];
        $this->assertArrayHasKey('open', $candle);
        $this->assertIsNumeric($candle['open']);
        $this->assertArrayHasKey('high', $candle);
        $this->assertIsNumeric($candle['high']);
        $this->assertArrayHasKey('low', $candle);
        $this->assertIsNumeric($candle['low']);
        $this->assertArrayHasKey('close', $candle);
        $this->assertIsNumeric($candle['close']);
        $this->assertArrayHasKey('volume', $candle);
        $this->assertIsNumeric($candle['volume']);
        $this->assertArrayHasKey('openTime', $candle);
        $this->assertIsInt($candle['openTime']);
        $this->assertArrayHasKey('closeTime', $candle);
        $this->assertIsInt($candle['closeTime']);
        $this->assertArrayHasKey('assetVolume', $candle);
        $this->assertIsNumeric($candle['assetVolume']);
        $this->assertArrayHasKey('baseVolume', $candle);
        $this->assertIsNumeric($candle['baseVolume']);
        $this->assertArrayHasKey('trades', $candle);
        $this->assertIsInt($candle['trades']);
        $this->assertArrayHasKey('assetBuyVolume', $candle);
        $this->assertIsNumeric($candle['assetBuyVolume']);
        $this->assertArrayHasKey('takerBuyVolume', $candle);
        $this->assertIsNumeric($candle['takerBuyVolume']);
    }

    public function testContinuousCandlesticksFutures()
    {
        $res = $this->futuresBinance->futuresContinuousCandlesticks($this->symbol, $this->interval, $this->limit, null, null, $this->contractType);
        $this->assertIsArray($res);
        $firstKey = array_key_first($res);
        $this->assertIsNumeric($firstKey);
        $candle = $res[$firstKey];
        $this->assertArrayHasKey('open', $candle);
        $this->assertIsNumeric($candle['open']);
        $this->assertArrayHasKey('high', $candle);
        $this->assertIsNumeric($candle['high']);
        $this->assertArrayHasKey('low', $candle);
        $this->assertIsNumeric($candle['low']);
        $this->assertArrayHasKey('close', $candle);
        $this->assertIsNumeric($candle['close']);
        $this->assertArrayHasKey('volume', $candle);
        $this->assertIsNumeric($candle['volume']);
        $this->assertArrayHasKey('openTime', $candle);
        $this->assertIsInt($candle['openTime']);
        $this->assertArrayHasKey('closeTime', $candle);
        $this->assertIsInt($candle['closeTime']);
        $this->assertArrayHasKey('assetVolume', $candle);
        $this->assertIsNumeric($candle['assetVolume']);
        $this->assertArrayHasKey('baseVolume', $candle);
        $this->assertIsNumeric($candle['baseVolume']);
        $this->assertArrayHasKey('trades', $candle);
        $this->assertIsInt($candle['trades']);
        $this->assertArrayHasKey('assetBuyVolume', $candle);
        $this->assertIsNumeric($candle['assetBuyVolume']);
        $this->assertArrayHasKey('takerBuyVolume', $candle);
        $this->assertIsNumeric($candle['takerBuyVolume']);
    }

    public function testIndexPriceCandlesticksFutures()
    {
        $res = $this->futuresBinance->futuresIndexPriceCandlesticks($this->symbol, $this->interval, $this->limit);
        $this->assertIsArray($res);
        $firstKey = array_key_first($res);
        $this->assertIsNumeric($firstKey);
        $candle = $res[$firstKey];
        $this->assertArrayHasKey('open', $candle);
        $this->assertIsNumeric($candle['open']);
        $this->assertArrayHasKey('high', $candle);
        $this->assertIsNumeric($candle['high']);
        $this->assertArrayHasKey('low', $candle);
        $this->assertIsNumeric($candle['low']);
        $this->assertArrayHasKey('close', $candle);
        $this->assertIsNumeric($candle['close']);
        $this->assertArrayHasKey('volume', $candle);
        $this->assertIsNumeric($candle['volume']);
        $this->assertArrayHasKey('openTime', $candle);
        $this->assertIsInt($candle['openTime']);
        $this->assertArrayHasKey('closeTime', $candle);
        $this->assertIsInt($candle['closeTime']);
        $this->assertArrayHasKey('assetVolume', $candle);
        $this->assertIsNumeric($candle['assetVolume']);
        $this->assertArrayHasKey('baseVolume', $candle);
        $this->assertIsNumeric($candle['baseVolume']);
        $this->assertArrayHasKey('trades', $candle);
        $this->assertIsInt($candle['trades']);
        $this->assertArrayHasKey('assetBuyVolume', $candle);
        $this->assertIsNumeric($candle['assetBuyVolume']);
        $this->assertArrayHasKey('takerBuyVolume', $candle);
        $this->assertIsNumeric($candle['takerBuyVolume']);
    }

    public function testMarkPriceCandlesticksFutures()
    {
        $res = $this->futuresBinance->futuresMarkPriceCandlesticks($this->symbol, $this->interval, $this->limit);
        $this->assertIsArray($res);
        $firstKey = array_key_first($res);
        $this->assertIsNumeric($firstKey);
        $candle = $res[$firstKey];
        $this->assertArrayHasKey('open', $candle);
        $this->assertIsNumeric($candle['open']);
        $this->assertArrayHasKey('high', $candle);
        $this->assertIsNumeric($candle['high']);
        $this->assertArrayHasKey('low', $candle);
        $this->assertIsNumeric($candle['low']);
        $this->assertArrayHasKey('close', $candle);
        $this->assertIsNumeric($candle['close']);
        $this->assertArrayHasKey('volume', $candle);
        $this->assertIsNumeric($candle['volume']);
        $this->assertArrayHasKey('openTime', $candle);
        $this->assertIsInt($candle['openTime']);
        $this->assertArrayHasKey('closeTime', $candle);
        $this->assertIsInt($candle['closeTime']);
        $this->assertArrayHasKey('assetVolume', $candle);
        $this->assertIsNumeric($candle['assetVolume']);
        $this->assertArrayHasKey('baseVolume', $candle);
        $this->assertIsNumeric($candle['baseVolume']);
        $this->assertArrayHasKey('trades', $candle);
        $this->assertIsInt($candle['trades']);
        $this->assertArrayHasKey('assetBuyVolume', $candle);
        $this->assertIsNumeric($candle['assetBuyVolume']);
        $this->assertArrayHasKey('takerBuyVolume', $candle);
        $this->assertIsNumeric($candle['takerBuyVolume']);
    }

    public function testPremiumIndexCandlesticksFutures()
    {
        $res = $this->futuresBinance->futuresPremiumIndexCandlesticks($this->symbol, $this->interval, $this->limit);
        $this->assertIsArray($res);
        $firstKey = array_key_first($res);
        $this->assertIsNumeric($firstKey);
        $candle = $res[$firstKey];
        $this->assertArrayHasKey('open', $candle);
        $this->assertIsNumeric($candle['open']);
        $this->assertArrayHasKey('high', $candle);
        $this->assertIsNumeric($candle['high']);
        $this->assertArrayHasKey('low', $candle);
        $this->assertIsNumeric($candle['low']);
        $this->assertArrayHasKey('close', $candle);
        $this->assertIsNumeric($candle['close']);
        $this->assertArrayHasKey('volume', $candle);
        $this->assertIsNumeric($candle['volume']);
        $this->assertArrayHasKey('openTime', $candle);
        $this->assertIsInt($candle['openTime']);
        $this->assertArrayHasKey('closeTime', $candle);
        $this->assertIsInt($candle['closeTime']);
        $this->assertArrayHasKey('assetVolume', $candle);
        $this->assertIsNumeric($candle['assetVolume']);
        $this->assertArrayHasKey('baseVolume', $candle);
        $this->assertIsNumeric($candle['baseVolume']);
        $this->assertArrayHasKey('trades', $candle);
        $this->assertIsInt($candle['trades']);
        $this->assertArrayHasKey('assetBuyVolume', $candle);
        $this->assertIsNumeric($candle['assetBuyVolume']);
        $this->assertArrayHasKey('takerBuyVolume', $candle);
        $this->assertIsNumeric($candle['takerBuyVolume']);
    }

    public function testMarkPriceFutures()
    {
        $res = $this->futuresBinance->futuresMarkPrice($this->symbol);
        $this->assertIsArray($res);
        $this->assertEquals($this->symbol, $res['symbol']);
        $this->assertArrayHasKey('markPrice', $res);
        $this->assertIsNumeric($res['markPrice']);
        $this->assertArrayHasKey('indexPrice', $res);
        $this->assertIsNumeric($res['indexPrice']);
        $this->assertArrayHasKey('estimatedSettlePrice', $res);
        $this->assertIsNumeric($res['estimatedSettlePrice']);
        $this->assertArrayHasKey('lastFundingRate', $res);
        $this->assertIsNumeric($res['lastFundingRate']);
        $this->assertArrayHasKey('interestRate', $res);
        $this->assertIsNumeric($res['interestRate']);
        $this->assertArrayHasKey('nextFundingTime', $res);
        $this->assertIsInt($res['nextFundingTime']);
        $this->assertArrayHasKey('time', $res);
        $this->assertIsInt($res['time']);
    }

    public function testFundingRateHistoryFutures()
    {
        $res = $this->futuresBinance->futuresFundingRateHistory($this->symbol, $this->limit);
        $this->assertIsArray($res);
        $this->assertIsArray($res[0]);
        $entry = $res[0];
        $this->assertArrayHasKey('symbol', $entry);
        $this->assertEquals($this->symbol, $entry['symbol']);
        $this->assertArrayHasKey('fundingTime', $entry);
        $this->assertIsInt($entry['fundingTime']);
        $this->assertArrayHasKey('fundingRate', $entry);
        $this->assertIsNumeric($entry['fundingRate']);
        $this->assertArrayHasKey('markPrice', $entry);
        $this->assertIsNumeric($entry['markPrice']);
    }

    public function testPrevDayFutures()
    {
        $res = $this->futuresBinance->futuresPrevDay($this->symbol);
        $this->assertIsArray($res);
        $this->assertEquals($this->symbol, $res['symbol']);
        $this->assertArrayHasKey('priceChange', $res);
        $this->assertIsNumeric($res['priceChange']);
        $this->assertArrayHasKey('priceChangePercent', $res);
        $this->assertIsNumeric($res['priceChangePercent']);
        $this->assertArrayHasKey('weightedAvgPrice', $res);
        $this->assertIsNumeric($res['weightedAvgPrice']);
        $this->assertArrayHasKey('lastPrice', $res);
        $this->assertIsNumeric($res['lastPrice']);
        $this->assertArrayHasKey('lastQty', $res);
        $this->assertIsNumeric($res['lastQty']);
        $this->assertArrayHasKey('openPrice', $res);
        $this->assertIsNumeric($res['openPrice']);
        $this->assertArrayHasKey('highPrice', $res);
        $this->assertIsNumeric($res['highPrice']);
        $this->assertArrayHasKey('lowPrice', $res);
        $this->assertIsNumeric($res['lowPrice']);
        $this->assertArrayHasKey('volume', $res);
        $this->assertIsNumeric($res['volume']);
        $this->assertArrayHasKey('quoteVolume', $res);
        $this->assertIsNumeric($res['quoteVolume']);
        $this->assertArrayHasKey('openTime', $res);
        $this->assertIsInt($res['openTime']);
        $this->assertArrayHasKey('closeTime', $res);
        $this->assertIsInt($res['closeTime']);
        $this->assertArrayHasKey('firstId', $res);
        $this->assertIsInt($res['firstId']);
        $this->assertArrayHasKey('lastId', $res);
        $this->assertIsInt($res['lastId']);
        $this->assertArrayHasKey('count', $res);
        $this->assertIsInt($res['count']);
    }

    public function testPriceFutures()
    {
        $res = $this->futuresBinance->futuresPrice($this->symbol);
        $this->assertIsNumeric($res);
    }

    public function testPricesFutures()
    {
        $res = $this->futuresBinance->futuresPrices();
        $this->assertIsArray($res);
        $this->assertArrayHasKey($this->symbol, $res);
        $this->assertIsNumeric($res[$this->symbol]);
    }

    public function testPriceV2Futures()
    {
        $res = $this->futuresBinance->futuresPriceV2($this->symbol);
        $this->assertIsNumeric($res);
    }

    public function testPricesV2Futures()
    {
        $res = $this->futuresBinance->futuresPricesV2();
        $this->assertIsArray($res);
        $this->assertArrayHasKey($this->symbol, $res);
        $this->assertIsNumeric($res[$this->symbol]);
    }

    public function testSymbolOrderBookTickerFutures()
    {
        $res = $this->futuresBinance->futuresSymbolOrderBookTicker($this->symbol);
        $this->assertIsArray($res);
        $this->assertEquals($this->symbol, $res['symbol']);
        $this->assertArrayHasKey('bidPrice', $res);
        $this->assertIsNumeric($res['bidPrice']);
        $this->assertArrayHasKey('bidQty', $res);
        $this->assertIsNumeric($res['bidQty']);
        $this->assertArrayHasKey('askPrice', $res);
        $this->assertIsNumeric($res['askPrice']);
        $this->assertArrayHasKey('askQty', $res);
        $this->assertIsNumeric($res['askQty']);
        $this->assertArrayHasKey('time', $res);
        $this->assertIsInt($res['time']);
    }


    // public function testDeliveryPriceFutures() // Could throw an error if useTestnet is set to false
    // {
    //     $this->futuresBinance->useTestnet = false; // set to false for fapiData request
    //     $res = $this->futuresBinance->futuresDeliveryPrice($this->symbol);
    //     $this->assertIsArray($res);
    //     $this->assertIsArray($res[0]);
    //     $this->assertArrayHasKey('deliveryTime', $res[0]);
    //     $this->assertIsInt($res[0]['deliveryTime']);
    //     $this->assertArrayHasKey('deliveryPrice', $res[0]);
    //     $this->assertIsNumeric($res[0]['deliveryPrice']);
    //     $this->futuresBinance->useTestnet = true; // reset to true for other tests
    // }

    // public function testOpenInterestFutures()
    // {
    //     $res = $this->futuresBinance->futuresOpenInterest($this->symbol); // for now the exchange returns null
    //     $this->assertIsArray($res);
    //     $this->assertEquals($this->symbol, $res['symbol']);
    //     $this->assertArrayHasKey('openInterest', $res);
    //     $this->assertIsNumeric($res['openInterest']);
    //     $this->assertArrayHasKey('time', $res);
    //     $this->assertIsInt($res['time']);
    // }

    // public function testOpenInterestHistoryFutures() // Could throw an error if useTestnet is set to false
    // {
    //     $this->futuresBinance->useTestnet = false; // set to false for fapiData request
    //     $res = $this->futuresBinance->futuresOpenInterestHistory($this->symbol, $this->period, $this->limit);
    //     $this->assertIsArray($res);
    //     $entry = $res[0];
    //     $this->assertIsArray($entry);
    //     $this->assertArrayHasKey('symbol', $entry);
    //     $this->assertEquals($this->symbol, $entry['symbol']);
    //     $this->assertArrayHasKey('sumOpenInterest', $entry);
    //     $this->assertIsNumeric($entry['sumOpenInterest']);
    //     $this->assertArrayHasKey('sumOpenInterestValue', $entry);
    //     $this->assertIsNumeric($entry['sumOpenInterestValue']);
    //     $this->assertArrayHasKey('timestamp', $entry);
    //     $this->assertIsInt($entry['timestamp']);
    //     $this->futuresBinance->useTestnet = true; // reset to true for other tests
    // }

    // public function testTopLongShortPositionRatioFutures() // Could throw an error if useTestnet is set to false
    // {
    //     $this->futuresBinance->useTestnet = false; // set to false for fapiData request
    //     $res = $this->futuresBinance->futuresTopLongShortPositionRatio($this->symbol, $this->period, $this->limit);
    //     $this->assertIsArray($res);
    //     $entry = $res[0];
    //     $this->assertIsArray($entry);
    //     $this->assertArrayHasKey('symbol', $entry);
    //     $this->assertEquals($this->symbol, $entry['symbol']);
    //     $this->assertArrayHasKey('longAccount', $entry);
    //     $this->assertIsNumeric($entry['longAccount']);
    //     $this->assertArrayHasKey('longShortRatio', $entry);
    //     $this->assertIsNumeric($entry['longShortRatio']);
    //     $this->assertArrayHasKey('shortAccount', $entry);
    //     $this->assertIsNumeric($entry['shortAccount']);
    //     $this->assertArrayHasKey('timestamp', $entry);
    //     $this->assertIsInt($entry['timestamp']);
    //     $this->futuresBinance->useTestnet = true; // reset to true for other tests
    // }

    // public function testTopLongShortAccountRatioFutures() // Could throw an error if useTestnet is set to false
    // {
    //     $this->futuresBinance->useTestnet = false; // set to false for fapiData request
    //     $res = $this->futuresBinance->futuresTopLongShortAccountRatio($this->symbol, $this->period, $this->limit);
    //     $this->assertIsArray($res);
    //     $entry = $res[0];
    //     $this->assertIsArray($entry);
    //     $this->assertArrayHasKey('symbol', $entry);
    //     $this->assertEquals($this->symbol, $entry['symbol']);
    //     $this->assertArrayHasKey('longAccount', $entry);
    //     $this->assertIsNumeric($entry['longAccount']);
    //     $this->assertArrayHasKey('longShortRatio', $entry);
    //     $this->assertIsNumeric($entry['longShortRatio']);
    //     $this->assertArrayHasKey('shortAccount', $entry);
    //     $this->assertIsNumeric($entry['shortAccount']);
    //     $this->assertArrayHasKey('timestamp', $entry);
    //     $this->assertIsInt($entry['timestamp']);
    //     $this->futuresBinance->useTestnet = true; // reset to true for other tests
    // }

    // public function testGlobalLongShortAccountRatioFutures() // Could throw an error if useTestnet is set to false
    // {
    //     $this->futuresBinance->useTestnet = false; // set to false for fapiData request
    //     $res = $this->futuresBinance->futuresGlobalLongShortAccountRatio($this->symbol, $this->period, $this->limit);
    //     $this->assertIsArray($res);
    //     $entry = $res[0];
    //     $this->assertIsArray($entry);
    //     $this->assertArrayHasKey('symbol', $entry);
    //     $this->assertEquals($this->symbol, $entry['symbol']);
    //     $this->assertArrayHasKey('longAccount', $entry);
    //     $this->assertIsNumeric($entry['longAccount']);
    //     $this->assertArrayHasKey('longShortRatio', $entry);
    //     $this->assertIsNumeric($entry['longShortRatio']);
    //     $this->assertArrayHasKey('shortAccount', $entry);
    //     $this->assertIsNumeric($entry['shortAccount']);
    //     $this->assertArrayHasKey('timestamp', $entry);
    //     $this->assertIsInt($entry['timestamp']);
    //     $this->futuresBinance->useTestnet = true; // reset to true for other tests
    // }

    // public function testTakerLongShortRatioFutures() // Could throw an error if useTestnet is set to false
    // {
    //     $this->futuresBinance->useTestnet = false; // set to false for fapiData request
    //     $res = $this->futuresBinance->futuresTakerLongShortRatio($this->symbol, $this->period, $this->limit);
    //     $this->assertIsArray($res);
    //     $entry = $res[0];
    //     $this->assertIsArray($entry);
    //     $this->assertArrayHasKey('buySellRatio', $entry);
    //     $this->assertIsNumeric($entry['buySellRatio']);
    //     $this->assertArrayHasKey('sellVol', $entry);
    //     $this->assertIsNumeric($entry['sellVol']);
    //     $this->assertArrayHasKey('buyVol', $entry);
    //     $this->assertIsNumeric($entry['buyVol']);
    //     $this->assertArrayHasKey('timestamp', $entry);
    //     $this->assertIsInt($entry['timestamp']);
    //     $this->futuresBinance->useTestnet = true; // reset to true for other tests
    // }

    // public function testBasisFutures() // Could throw an error if useTestnet is set to false
    // {
    //     $this->futuresBinance->useTestnet = false; // set to false for fapiData request
    //     $res = $this->futuresBinance->futuresBasis($this->symbol, $this->period, $this->limit, null, null, $this->contractType);
    //     $this->assertIsArray($res);
    //     $entry = $res[0];
    //     $this->assertIsArray($entry);
    //     $this->assertArrayHasKey('indexPrice', $entry);
    //     $this->assertIsNumeric($entry['indexPrice']);
    //     $this->assertArrayHasKey('contractType', $entry);
    //     $this->assertEquals($this->contractType, $entry['contractType']);
    //     $this->assertArrayHasKey('basisRate', $entry);
    //     $this->assertIsNumeric($entry['basisRate']);
    //     $this->assertArrayHasKey('futuresPrice', $entry);
    //     $this->assertIsNumeric($entry['futuresPrice']);
    //     $this->assertArrayHasKey('annualizedBasisRate', $entry);
    //     $this->assertIsNumeric($entry['annualizedBasisRate']);
    //     $this->assertArrayHasKey('basis', $entry);
    //     $this->assertIsNumeric($entry['basis']);
    //     $this->assertArrayHasKey('pair', $entry);
    //     $this->assertEquals($this->symbol, $entry['pair']);
    //     $this->assertArrayHasKey('timestamp', $entry);
    //     $this->assertIsInt($entry['timestamp']);
    //     $this->futuresBinance->useTestnet = true; // reset to true for other tests
    // }

    public function testIndexInfoFutures()
    {
        $compositeIndex = 'DEFIUSDT';
        $res = $this->futuresBinance->futuresIndexInfo($compositeIndex);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('symbol', $res);
        $this->assertEquals($compositeIndex, $res['symbol']);
        $this->assertArrayHasKey('time', $res);
        $this->assertIsInt($res['time']);
        $this->assertArrayHasKey('component', $res);
        $this->assertArrayHasKey('baseAssetList', $res);
        $this->assertIsArray($res['baseAssetList']);
    }

    public function testAssetIndexFutures()
    {
        $res = $this->futuresBinance->futuresAssetIndex();
        $this->assertIsArray($res);
        $entry = $res[0];
        $this->assertIsArray($entry);
        $this->assertArrayHasKey('symbol', $entry);
        $this->assertIsString($entry['symbol']);
        $this->assertArrayHasKey('time', $entry);
        $this->assertIsInt($entry['time']);
        $this->assertArrayHasKey('index', $entry);
        $this->assertIsNumeric($entry['index']);
        $this->assertArrayHasKey('bidBuffer', $entry);
        $this->assertIsNumeric($entry['bidBuffer']);
        $this->assertArrayHasKey('askBuffer', $entry);
        $this->assertIsNumeric($entry['askBuffer']);
        $this->assertArrayHasKey('bidRate', $entry);
        $this->assertIsNumeric($entry['bidRate']);
        $this->assertArrayHasKey('askRate', $entry);
        $this->assertIsNumeric($entry['askRate']);
        $this->assertArrayHasKey('autoExchangeBidBuffer', $entry);
        $this->assertIsNumeric($entry['autoExchangeBidBuffer']);
        $this->assertArrayHasKey('autoExchangeAskBuffer', $entry);
        $this->assertIsNumeric($entry['autoExchangeAskBuffer']);
        $this->assertArrayHasKey('autoExchangeBidRate', $entry);
        $this->assertIsNumeric($entry['autoExchangeBidRate']);
        $this->assertArrayHasKey('autoExchangeAskRate', $entry);
        $this->assertIsNumeric($entry['autoExchangeAskRate']);
    }

    public function testConstituentsFutures()
    {
        $res = $this->futuresBinance->futuresConstituents($this->symbol);
        $this->assertIsArray($res);
        $this->assertEquals($this->symbol, $res['symbol']);
        $this->assertArrayHasKey('time', $res);
        $this->assertIsInt($res['time']);
        $this->assertArrayHasKey('constituents', $res);
        $this->assertIsArray($res['constituents']);
    }

    public function testOrderAmendmentFutures()
    {
        $res = $this->futuresBinance->futuresOrderAmendment($this->symbol);
        $this->assertIsArray($res);
    }

    public function testCountdownCancelAllOrdersFutures()
    {
        $countdownTime = 1000;
        $res = $this->futuresBinance->futuresCountdownCancelAllOrders($this->symbol, $countdownTime);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('symbol', $res);
        $this->assertEquals($this->symbol, $res['symbol']);
        $this->assertArrayHasKey('countdownTime', $res);
        $this->assertEquals($countdownTime, $res['countdownTime']);
    }

    public function testAllOrdersFutures()
    {
        $res = $this->futuresBinance->futuresAllOrders($this->symbol);
        $this->assertIsArray($res);
    }

    public function testOpenOrdersFutures()
    {
        $res = $this->futuresBinance->futuresOpenOrders($this->symbol);
        $this->assertIsArray($res);
    }

    public function testForceOrdersFutures()
    {
        $res = $this->futuresBinance->futuresForceOrders();
        $this->assertIsArray($res);
    }

    public function testMyTradesFutures()
    {
        $res = $this->futuresBinance->futuresMyTrades($this->symbol);
        $this->assertIsArray($res);
    }

    public function testHistoryFutures()
    {
        $res = $this->futuresBinance->futuresHistory($this->symbol);
        $this->assertIsArray($res);
    }

    public function testPositionModeFutures()
    {
        $res = $this->futuresBinance->futuresPositionMode();
        $this->assertIsArray($res);
        $this->assertArrayHasKey('dualSidePosition', $res);
        $this->assertIsBool($res['dualSidePosition']);
    }

    public function testMultiAssetsMarginModeFutures()
    {
        $res = $this->futuresBinance->futuresMultiAssetsMarginMode();
        $this->assertIsArray($res);
        $this->assertArrayHasKey('multiAssetsMargin', $res);
        $this->assertIsBool($res['multiAssetsMargin']);
    }

    public function testPositionsFutures()
    {
        $res = $this->futuresBinance->futuresPositions($this->symbol);
        $this->assertIsArray($res);
    }

    public function testPositionsV2Futures()
    {
        $res = $this->futuresBinance->futuresPositionsV2($this->symbol);
        $this->assertIsArray($res);
    }

    public function testPositionsV3Futures()
    {
        $res = $this->futuresBinance->futuresPositionsV3($this->symbol);
        $this->assertIsArray($res);
    }

    public function testPositionFutures()
    {
        $res = $this->futuresBinance->futuresPosition($this->symbol);
        $this->assertIsArray($res);
    }

    public function testPositionV2Futures()
    {
        $res = $this->futuresBinance->futuresPositionV2($this->symbol);
        $this->assertIsArray($res);
    }

    public function testPositionV3Futures()
    {
        $res = $this->futuresBinance->futuresPositionV3($this->symbol);
        $this->assertIsArray($res);
    }

    public function testAdlQuantileFutures()
    {
        $res = $this->futuresBinance->futuresAdlQuantile($this->symbol);
        $this->assertIsArray($res);
        $this->assertEquals($this->symbol, $res['symbol']);
        $this->assertArrayHasKey('adlQuantile', $res);
        $this->assertIsArray($res['adlQuantile']);
    }

    public function testPositionMarginChangeHistoryFutures()
    {
        $res = $this->futuresBinance->futuresPositionMarginChangeHistory($this->symbol);
        $this->assertIsArray($res);
    }

    public function testBalancesFutures()
    {
        $res = $this->futuresBinance->futuresBalances();
        $this->assertIsArray($res);
        $firstKey = array_key_first($res);
        $this->assertIsString($firstKey);
        $firstValue = $res[$firstKey];
        $this->assertIsArray($firstValue);
        $this->assertArrayHasKey('available', $firstValue);
        $this->assertIsNumeric($firstValue['available']);
        $this->assertArrayHasKey('onOrder', $firstValue);
        $this->assertIsNumeric($firstValue['onOrder']);
        $this->assertArrayHasKey('total', $firstValue);
        $this->assertIsNumeric($firstValue['total']);
        $this->assertArrayHasKey('info', $firstValue);
        $info = $firstValue['info'];
        $this->assertIsArray($info);
        $this->assertArrayHasKey('accountAlias', $info);
        $this->assertIsString($info['accountAlias']);
        $this->assertArrayHasKey('asset', $info);
        $this->assertIsString($info['asset']);
        $this->assertArrayHasKey('balance', $info);
        $this->assertIsNumeric($info['balance']);
        $this->assertArrayHasKey('crossWalletBalance', $info);
        $this->assertIsNumeric($info['crossWalletBalance']);
        $this->assertArrayHasKey('crossUnPnl', $info);
        $this->assertIsNumeric($info['crossUnPnl']);
        $this->assertArrayHasKey('availableBalance', $info);
        $this->assertIsNumeric($info['availableBalance']);
        $this->assertArrayHasKey('maxWithdrawAmount', $info);
        $this->assertIsNumeric($info['maxWithdrawAmount']);
        $this->assertArrayHasKey('marginAvailable', $info);
        $this->assertIsBool($info['marginAvailable']);
        $this->assertArrayHasKey('updateTime', $info);
        $this->assertIsInt($info['updateTime']);
    }

    public function testBalancesV2Futures()
    {
        $res = $this->futuresBinance->futuresBalancesV2();
        $this->assertIsArray($res);
        $firstKey = array_key_first($res);
        $this->assertIsString($firstKey);
        $firstValue = $res[$firstKey];
        $this->assertIsArray($firstValue);
        $this->assertArrayHasKey('available', $firstValue);
        $this->assertIsNumeric($firstValue['available']);
        $this->assertArrayHasKey('onOrder', $firstValue);
        $this->assertIsNumeric($firstValue['onOrder']);
        $this->assertArrayHasKey('total', $firstValue);
        $this->assertIsNumeric($firstValue['total']);
        $this->assertArrayHasKey('info', $firstValue);
        $info = $firstValue['info'];
        $this->assertIsArray($info);
        $this->assertArrayHasKey('accountAlias', $info);
        $this->assertIsString($info['accountAlias']);
        $this->assertArrayHasKey('asset', $info);
        $this->assertIsString($info['asset']);
        $this->assertArrayHasKey('balance', $info);
        $this->assertIsNumeric($info['balance']);
        $this->assertArrayHasKey('crossWalletBalance', $info);
        $this->assertIsNumeric($info['crossWalletBalance']);
        $this->assertArrayHasKey('crossUnPnl', $info);
        $this->assertIsNumeric($info['crossUnPnl']);
        $this->assertArrayHasKey('availableBalance', $info);
        $this->assertIsNumeric($info['availableBalance']);
        $this->assertArrayHasKey('maxWithdrawAmount', $info);
        $this->assertIsNumeric($info['maxWithdrawAmount']);
        $this->assertArrayHasKey('marginAvailable', $info);
        $this->assertIsBool($info['marginAvailable']);
        $this->assertArrayHasKey('updateTime', $info);
        $this->assertIsInt($info['updateTime']);
    }

    public function testBalancesV3Futures()
    {
        $res = $this->futuresBinance->futuresBalancesV3();
        $this->assertIsArray($res);
        $firstKey = array_key_first($res);
        $this->assertIsString($firstKey);
        $firstValue = $res[$firstKey];
        $this->assertIsArray($firstValue);
        $this->assertArrayHasKey('available', $firstValue);
        $this->assertIsNumeric($firstValue['available']);
        $this->assertArrayHasKey('onOrder', $firstValue);
        $this->assertIsNumeric($firstValue['onOrder']);
        $this->assertArrayHasKey('total', $firstValue);
        $this->assertIsNumeric($firstValue['total']);
        $this->assertArrayHasKey('info', $firstValue);
        $info = $firstValue['info'];
        $this->assertIsArray($info);
        $this->assertArrayHasKey('accountAlias', $info);
        $this->assertIsString($info['accountAlias']);
        $this->assertArrayHasKey('asset', $info);
        $this->assertIsString($info['asset']);
        $this->assertArrayHasKey('balance', $info);
        $this->assertIsNumeric($info['balance']);
        $this->assertArrayHasKey('crossWalletBalance', $info);
        $this->assertIsNumeric($info['crossWalletBalance']);
        $this->assertArrayHasKey('crossUnPnl', $info);
        $this->assertIsNumeric($info['crossUnPnl']);
        $this->assertArrayHasKey('availableBalance', $info);
        $this->assertIsNumeric($info['availableBalance']);
        $this->assertArrayHasKey('maxWithdrawAmount', $info);
        $this->assertIsNumeric($info['maxWithdrawAmount']);
        $this->assertArrayHasKey('marginAvailable', $info);
        $this->assertIsBool($info['marginAvailable']);
        $this->assertArrayHasKey('updateTime', $info);
        $this->assertIsInt($info['updateTime']);
    }

    public function testAccountFutures()
    {
        $res = $this->futuresBinance->futuresAccount();
        $this->assertIsArray($res);
        $this->assertArrayHasKey('totalInitialMargin', $res);
        $this->assertIsNumeric($res['totalInitialMargin']);
        $this->assertArrayHasKey('totalMaintMargin', $res);
        $this->assertIsNumeric($res['totalMaintMargin']);
        $this->assertArrayHasKey('totalWalletBalance', $res);
        $this->assertIsNumeric($res['totalWalletBalance']);
        $this->assertArrayHasKey('totalUnrealizedProfit', $res);
        $this->assertIsNumeric($res['totalUnrealizedProfit']);
        $this->assertArrayHasKey('totalMarginBalance', $res);
        $this->assertIsNumeric($res['totalMarginBalance']);
        $this->assertArrayHasKey('totalPositionInitialMargin', $res);
        $this->assertIsNumeric($res['totalPositionInitialMargin']);
        $this->assertArrayHasKey('totalOpenOrderInitialMargin', $res);
        $this->assertIsNumeric($res['totalOpenOrderInitialMargin']);
        $this->assertArrayHasKey('totalCrossWalletBalance', $res);
        $this->assertIsNumeric($res['totalCrossWalletBalance']);
        $this->assertArrayHasKey('totalCrossUnPnl', $res);
        $this->assertIsNumeric($res['totalCrossUnPnl']);
        $this->assertArrayHasKey('availableBalance', $res);
        $this->assertIsNumeric($res['availableBalance']);
        $this->assertArrayHasKey('maxWithdrawAmount', $res);
        $this->assertIsNumeric($res['maxWithdrawAmount']);
        $this->assertArrayHasKey('assets', $res);
        $this->assertIsArray($res['assets']);
        $this->assertArrayHasKey('positions', $res);
        $this->assertIsArray($res['positions']);
    }

    public function testAccountV2Futures()
    {
        $res = $this->futuresBinance->futuresAccountV2();
        $this->assertIsArray($res);
        $this->assertArrayHasKey('totalInitialMargin', $res);
        $this->assertIsNumeric($res['totalInitialMargin']);
        $this->assertArrayHasKey('totalMaintMargin', $res);
        $this->assertIsNumeric($res['totalMaintMargin']);
        $this->assertArrayHasKey('totalWalletBalance', $res);
        $this->assertIsNumeric($res['totalWalletBalance']);
        $this->assertArrayHasKey('totalUnrealizedProfit', $res);
        $this->assertIsNumeric($res['totalUnrealizedProfit']);
        $this->assertArrayHasKey('totalMarginBalance', $res);
        $this->assertIsNumeric($res['totalMarginBalance']);
        $this->assertArrayHasKey('totalPositionInitialMargin', $res);
        $this->assertIsNumeric($res['totalPositionInitialMargin']);
        $this->assertArrayHasKey('totalOpenOrderInitialMargin', $res);
        $this->assertIsNumeric($res['totalOpenOrderInitialMargin']);
        $this->assertArrayHasKey('totalCrossWalletBalance', $res);
        $this->assertIsNumeric($res['totalCrossWalletBalance']);
        $this->assertArrayHasKey('totalCrossUnPnl', $res);
        $this->assertIsNumeric($res['totalCrossUnPnl']);
        $this->assertArrayHasKey('availableBalance', $res);
        $this->assertIsNumeric($res['availableBalance']);
        $this->assertArrayHasKey('maxWithdrawAmount', $res);
        $this->assertIsNumeric($res['maxWithdrawAmount']);
        $this->assertArrayHasKey('assets', $res);
        $this->assertIsArray($res['assets']);
        $this->assertArrayHasKey('positions', $res);
        $this->assertIsArray($res['positions']);
    }

    public function testAccountV3Futures()
    {
        $res = $this->futuresBinance->futuresAccountV3();
        $this->assertIsArray($res);
        $this->assertArrayHasKey('totalInitialMargin', $res);
        $this->assertIsNumeric($res['totalInitialMargin']);
        $this->assertArrayHasKey('totalMaintMargin', $res);
        $this->assertIsNumeric($res['totalMaintMargin']);
        $this->assertArrayHasKey('totalWalletBalance', $res);
        $this->assertIsNumeric($res['totalWalletBalance']);
        $this->assertArrayHasKey('totalUnrealizedProfit', $res);
        $this->assertIsNumeric($res['totalUnrealizedProfit']);
        $this->assertArrayHasKey('totalMarginBalance', $res);
        $this->assertIsNumeric($res['totalMarginBalance']);
        $this->assertArrayHasKey('totalPositionInitialMargin', $res);
        $this->assertIsNumeric($res['totalPositionInitialMargin']);
        $this->assertArrayHasKey('totalOpenOrderInitialMargin', $res);
        $this->assertIsNumeric($res['totalOpenOrderInitialMargin']);
        $this->assertArrayHasKey('totalCrossWalletBalance', $res);
        $this->assertIsNumeric($res['totalCrossWalletBalance']);
        $this->assertArrayHasKey('totalCrossUnPnl', $res);
        $this->assertIsNumeric($res['totalCrossUnPnl']);
        $this->assertArrayHasKey('availableBalance', $res);
        $this->assertIsNumeric($res['availableBalance']);
        $this->assertArrayHasKey('maxWithdrawAmount', $res);
        $this->assertIsNumeric($res['maxWithdrawAmount']);
        $this->assertArrayHasKey('assets', $res);
        $this->assertIsArray($res['assets']);
        $this->assertArrayHasKey('positions', $res);
        $this->assertIsArray($res['positions']);
    }

    public function testTradeFeeFutures()
    {
        $res = $this->futuresBinance->futuresTradeFee($this->symbol);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('symbol', $res);
        $this->assertEquals($this->symbol, $res['symbol']);
        $this->assertArrayHasKey('makerCommissionRate', $res);
        $this->assertIsNumeric($res['makerCommissionRate']);
        $this->assertArrayHasKey('takerCommissionRate', $res);
        $this->assertIsNumeric($res['takerCommissionRate']);
    }

    public function testAccountConfigFutures()
    {
        $res = $this->futuresBinance->futuresAccountConfig();
        $this->assertIsArray($res);
        $this->assertArrayHasKey('feeTier', $res);
        $this->assertIsInt($res['feeTier']);
        $this->assertArrayHasKey('canTrade', $res);
        $this->assertIsBool($res['canTrade']);
        $this->assertArrayHasKey('canDeposit', $res);
        $this->assertIsBool($res['canDeposit']);
        $this->assertArrayHasKey('canWithdraw', $res);
        $this->assertIsBool($res['canWithdraw']);
        $this->assertArrayHasKey('dualSidePosition', $res);
        $this->assertIsBool($res['dualSidePosition']);
        $this->assertArrayHasKey('updateTime', $res);
        $this->assertIsInt($res['updateTime']);
        $this->assertArrayHasKey('multiAssetsMargin', $res);
        $this->assertIsBool($res['multiAssetsMargin']);
        $this->assertArrayHasKey('tradeGroupId', $res);
    }

    public function testMarginModesFutures()
    {
        $res = $this->futuresBinance->futuresMarginModes($this->symbol);
        $this->assertIsArray($res);
        $firstEntry = $res[0];
        $this->assertIsArray($firstEntry);
        $this->assertArrayHasKey('symbol', $firstEntry);
        $this->assertEquals($this->symbol, $firstEntry['symbol']);
        $this->assertArrayHasKey('marginType', $firstEntry);
        $this->assertIsString($firstEntry['marginType']);
        $this->assertArrayHasKey('isAutoAddMargin', $firstEntry);
        $this->assertIsBool($firstEntry['isAutoAddMargin']);
        $this->assertArrayHasKey('leverage', $firstEntry);
        $this->assertIsNumeric($firstEntry['leverage']);
        $this->assertArrayHasKey('maxNotionalValue', $firstEntry);
        $this->assertIsNumeric($firstEntry['maxNotionalValue']);
    }

    public function testOrderRateLimitFutures()
    {
        $res = $this->futuresBinance->futuresOrderRateLimit();
        $this->assertIsArray($res);
        $firstEntry = $res[0];
        $this->assertIsArray($firstEntry);
        $this->assertArrayHasKey('rateLimitType', $firstEntry);
        $this->assertEquals('ORDERS', $firstEntry['rateLimitType']);
        $this->assertArrayHasKey('interval', $firstEntry);
        $this->assertIsString($firstEntry['interval']);
        $this->assertArrayHasKey('intervalNum', $firstEntry);
        $this->assertIsNumeric($firstEntry['intervalNum']);
        $this->assertArrayHasKey('limit', $firstEntry);
        $this->assertIsInt($firstEntry['limit']);
    }

    public function testLeveragesFutures()
    {
        $res = $this->futuresBinance->futuresLeverages($this->symbol);
        $this->assertIsArray($res);
        $firstEntry = $res[0];
        $this->assertIsArray($firstEntry);
        $this->assertArrayHasKey('symbol', $firstEntry);
        $this->assertEquals($this->symbol, $firstEntry['symbol']);
        $this->assertArrayHasKey('brackets', $firstEntry);
        $this->assertIsArray($firstEntry['brackets']);
        $firstBracket = $firstEntry['brackets'][0];
        $this->assertIsArray($firstBracket);
        $this->assertArrayHasKey('bracket', $firstBracket);
        $this->assertIsInt($firstBracket['bracket']);
        $this->assertArrayHasKey('initialLeverage', $firstBracket);
        $this->assertIsNumeric($firstBracket['initialLeverage']);
        $this->assertArrayHasKey('notionalCap', $firstBracket);
        $this->assertIsNumeric($firstBracket['notionalCap']);
        $this->assertArrayHasKey('notionalFloor', $firstBracket);
        $this->assertIsNumeric($firstBracket['notionalFloor']);
        $this->assertArrayHasKey('maintMarginRatio', $firstBracket);
        $this->assertIsNumeric($firstBracket['maintMarginRatio']);
        $this->assertArrayHasKey('cum', $firstBracket);
        $this->assertIsNumeric($firstBracket['cum']);
    }

    public function testLedgerFutures()
    {
        $res = $this->futuresBinance->futuresLedger();
        $this->assertIsArray($res);
    }

    public function testTradingStatusFutures()
    {
        $res = $this->futuresBinance->futuresTradingStatus();
        $this->assertIsArray($res);
        $this->assertArrayHasKey('indicators', $res);
        $this->assertIsArray($res['indicators']);
        $this->assertArrayHasKey('updateTime', $res);
        $this->assertIsInt($res['updateTime']);
    }

    public function testFeeBurnStatusFutures()
    {
        $res = $this->futuresBinance->futuresFeeBurnStatus();
        $this->assertIsArray($res);
        $this->assertArrayHasKey('feeBurn', $res);
        $this->assertIsBool($res['feeBurn']);
    }
}
