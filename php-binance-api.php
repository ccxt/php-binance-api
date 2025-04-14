<?php
/*
 * ============================================================
 * @package php-binance-api
 * @link https://github.com/jaggedsoft/php-binance-api
 * ============================================================
 * @copyright 2017-2018
 * @author Jon Eyrick
 * @license MIT License
 * ============================================================
 * A curl HTTP REST wrapper for the binance currency exchange
 */
namespace Binance;

use Exception;

// PHP version check
if (version_compare(phpversion(), '7.0', '<=')) {
    fwrite(STDERR, "Hi, PHP " . phpversion() . " support will be removed very soon as part of continued development.\n");
    fwrite(STDERR, "Please consider upgrading.\n");
}

/**
 * Main Binance class
 *
 * Eg. Usage:
 * require 'vendor/autoload.php';
 * $api = new Binance\\API();
 */
class API
{
    protected $base = 'https://api.binance.com/api/'; // /< REST endpoint for the currency exchange
    protected $baseTestnet = 'https://testnet.binance.vision/api/'; // /< Testnet REST endpoint for the currency exchange
    protected $wapi = 'https://api.binance.com/wapi/'; // /< REST endpoint for the withdrawals
    protected $sapi = 'https://api.binance.com/sapi/'; // /< REST endpoint for the supporting network API
    protected $fapi = 'https://fapi.binance.com/fapi/'; // /< REST endpoint for the futures API
    protected $fapiData = 'https://fapi.binance.com/futures/data/'; // /< REST endpoint for the futures API
    protected $fapiTestnet = 'https://testnet.binancefuture.com/fapi/'; // /< Testnet REST endpoint for the futures API
    protected $dapi = 'https://dapi.binance.com/dapi/'; // /< REST endpoint for the delivery API
    protected $dapiData = 'https://dapi.binance.com/futures/data/'; // /< REST endpoint for the delivery API
    protected $dapiTestnet = 'https://testnet.binancefuture.com/dapi/'; // /< Testnet REST endpoint for the delivery API
    protected $papi = 'https://papi.binance.com/papi/'; // /< REST endpoint for the options API
    protected $bapi = 'https://www.binance.com/bapi/'; // /< REST endpoint for the internal Binance API
    protected $stream = 'wss://stream.binance.com:9443/ws/'; // /< Endpoint for establishing websocket connections
    protected $streamTestnet = 'wss://testnet.binance.vision/ws/'; // /< Testnet endpoint for establishing websocket connections
    protected $api_key; // /< API key that you created in the binance website member area
    protected $api_secret; // /< API secret that was given to you when you created the api key
    protected $useTestnet = false; // /< Enable/disable testnet (https://testnet.binance.vision/)
    protected $depthCache = []; // /< Websockets depth cache
    protected $depthQueue = []; // /< Websockets depth queue
    protected $chartQueue = []; // /< Websockets chart queue
    protected $charts = []; // /< Websockets chart data
    protected $curlOpts = []; // /< User defined curl coptions
    protected $info = [
        "timeOffset" => 0,
    ]; // /< Additional connection options
    protected $proxyConf = null; // /< Used for story the proxy configuration
    protected $caOverride = false; // /< set this if you donnot wish to use CA bundle auto download feature
    protected $transfered = 0; // /< This stores the amount of bytes transfered
    protected $requestCount = 0; // /< This stores the amount of API requests
    protected $httpDebug = false; // /< If you enable this, curl will output debugging information
    protected $subscriptions = []; // /< View all websocket subscriptions

    // /< value of available onOrder assets

    protected $exchangeInfo = null;
    protected $futuresExchangeInfo = null;
    protected $lastRequest = [];

    protected $xMbxUsedWeight = 0;
    protected $xMbxUsedWeight1m = 0;

    public $headers = [];

    private $SPOT_ORDER_PREFIX     = "x-HNA2TXFJ";
	private $CONTRACT_ORDER_PREFIX = "x-Cb7ytekJ";

    /**
     * Constructor for the class,
     * send as many argument as you want.
     *
     * No arguments - use file setup
     * 1 argument - file to load config from
     * 2 arguments - api key and api secret
     * 3 arguments - api key, api secret and use testnet flag
     *
     * @return null
     */
    public function __construct()
    {
        $param = func_get_args();
        switch (count($param)) {
            case 0:
                $this->setupApiConfigFromFile();
                $this->setupProxyConfigFromFile();
                $this->setupCurlOptsFromFile();
                break;
            case 1:
                $this->setupApiConfigFromFile($param[0]);
                $this->setupProxyConfigFromFile($param[0]);
                $this->setupCurlOptsFromFile($param[0]);
                break;
            case 2:
                $this->api_key = $param[0];
                $this->api_secret = $param[1];
                break;
            case 3:
                $this->api_key = $param[0];
                $this->api_secret = $param[1];
                $this->useTestnet = (bool)$param[2];
                break;
            default:
                echo 'Please see valid constructors here: https://github.com/jaggedsoft/php-binance-api/blob/master/examples/constructor.php';
        }
    }

    /**
     * magic get for protected and protected members
     *
     * @param $file string the name of the property to return
     * @return null
     */
    public function __get(string $member)
    {
        if (property_exists($this, $member)) {
            return $this->$member;
        }
        return null;
    }

    /**
     * magic set for protected and protected members
     *
     * @param $member string the name of the member property
     * @param $value the value of the member property
     */
    public function __set(string $member, $value)
    {
        $this->$member = $value;
    }

    /**
     * If no paramaters are supplied in the constructor, this function will attempt
     * to load the api_key and api_secret from the users home directory in the file
     * ~/jaggedsoft/php-binance-api.json
     *
     * @param $file string file location
     * @return null
     */
    protected function setupApiConfigFromFile(string $file = null)
    {
        $file = is_null($file) ? getenv("HOME") . "/.config/jaggedsoft/php-binance-api.json" : $file;

        if (empty($this->api_key) === false || empty($this->api_secret) === false) {
            return;
        }
        if (file_exists($file) === false) {
            echo "Unable to load config from: " . $file . PHP_EOL;
            echo "Detected no API KEY or SECRET, all signed requests will fail" . PHP_EOL;
            return;
        }
        $contents = json_decode(file_get_contents($file), true);
        $this->api_key = isset($contents['api-key']) ? $contents['api-key'] : "";
        $this->api_secret = isset($contents['api-secret']) ? $contents['api-secret'] : "";
        $this->useTestnet = isset($contents['use-testnet']) ? (bool)$contents['use-testnet'] : false;
    }

    /**
     * If no paramaters are supplied in the constructor, this function will attempt
     * to load the acurlopts from the users home directory in the file
     * ~/jaggedsoft/php-binance-api.json
     *
     * @param $file string file location
     * @return null
     */
    protected function setupCurlOptsFromFile(string $file = null)
    {
        $file = is_null($file) ? getenv("HOME") . "/.config/jaggedsoft/php-binance-api.json" : $file;

        if (count($this->curlOpts) > 0) {
            return;
        }
        if (file_exists($file) === false) {
            echo "Unable to load config from: " . $file . PHP_EOL;
            echo "No curl options will be set" . PHP_EOL;
            return;
        }
        $contents = json_decode(file_get_contents($file), true);
        $this->curlOpts = isset($contents['curlOpts']) && is_array($contents['curlOpts']) ? $contents['curlOpts'] : [];
    }

    /**
     * If no paramaters are supplied in the constructor for the proxy confguration,
     * this function will attempt to load the proxy info from the users home directory
     * ~/jaggedsoft/php-binance-api.json
     *
     * @return null
     */
    protected function setupProxyConfigFromFile(string $file = null)
    {
        $file = is_null($file) ? getenv("HOME") . "/.config/jaggedsoft/php-binance-api.json" : $file;

        if (is_null($this->proxyConf) === false) {
            return;
        }
        if (file_exists($file) === false) {
            echo "Unable to load config from: " . $file . PHP_EOL;
            echo "No proxies will be used " . PHP_EOL;
            return;
        }
        $contents = json_decode(file_get_contents($file), true);
        if (isset($contents['proto']) === false) {
            return;
        }
        if (isset($contents['address']) === false) {
            return;
        }
        if (isset($contents['port']) === false) {
            return;
        }
        $this->proxyConf['proto'] = $contents['proto'];
        $this->proxyConf['address'] = $contents['address'];
        $this->proxyConf['port'] = $contents['port'];
        if (isset($contents['user'])) {
            $this->proxyConf['user'] = isset($contents['user']) ? $contents['user'] : "";
        }
        if (isset($contents['pass'])) {
            $this->proxyConf['pass'] = isset($contents['pass']) ? $contents['pass'] : "";
        }
    }

    public static function uuid22($length = 22) {
        return bin2hex(random_bytes(intval($length / 2)));
    }

    protected function generateSpotClientOrderId()
    {
        return $this->SPOT_ORDER_PREFIX . self::uuid22();
    }

    protected function generateFuturesClientOrderId()
    {
        return $this->CONTRACT_ORDER_PREFIX . self::uuid22();;
    }

    /**
     * buy attempts to create a currency order
     * each currency supports a number of order types, such as
     * -LIMIT
     * -MARKET
     * -STOP_LOSS
     * -STOP_LOSS_LIMIT
     * -TAKE_PROFIT
     * -TAKE_PROFIT_LIMIT
     * -LIMIT_MAKER
     *
     * You should check the @see exchangeInfo for each currency to determine
     * what types of orders can be placed against specific pairs
     *
     * $quantity = 1;
     * $price = 0.0005;
     * $order = $api->buy("BNBBTC", $quantity, $price);
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity required
     * @param $price string price per unit you want to spend
     * @param $type string type of order
     * @param $flags array addtional options for order type
     * @return array with error message or the order details
     */
    public function buy(string $symbol, $quantity, $price, string $type = "LIMIT", array $flags = [])
    {
        return $this->order("BUY", $symbol, $quantity, $price, $type, $flags);
    }

    /**
     * buyTest attempts to create a TEST currency order
     *
     * @see buy()
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity required
     * @param $price string price per unit you want to spend
     * @param $type string config
     * @param $flags array config
     * @return array with error message or empty or the order details
     */
    public function buyTest(string $symbol, $quantity, $price, string $type = "LIMIT", array $flags = [])
    {
        return $this->order("BUY", $symbol, $quantity, $price, $type, $flags, true);
    }

    /**
     * sell attempts to create a currency order
     * each currency supports a number of order types, such as
     * -LIMIT
     * -MARKET
     * -STOP_LOSS
     * -STOP_LOSS_LIMIT
     * -TAKE_PROFIT
     * -TAKE_PROFIT_LIMIT
     * -LIMIT_MAKER
     *
     * You should check the @see exchangeInfo for each currency to determine
     * what types of orders can be placed against specific pairs
     *
     * $quantity = 1;
     * $price = 0.0005;
     * $order = $api->sell("BNBBTC", $quantity, $price);
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity required
     * @param $price string price per unit you want to spend
     * @param $type string type of order
     * @param $flags array addtional options for order type
     * @return array with error message or the order details
     */
    public function sell(string $symbol, $quantity, $price, string $type = "LIMIT", array $flags = [])
    {
        return $this->order("SELL", $symbol, $quantity, $price, $type, $flags);
    }

    /**
     * sellTest attempts to create a TEST currency order
     *
     * @see sell()
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity required
     * @param $price string price per unit you want to spend
     * @param $type array config
     * @param $flags array config
     * @return array with error message or empty or the order details
     */
    public function sellTest(string $symbol, $quantity, $price, string $type = "LIMIT", array $flags = [])
    {
        return $this->order("SELL", $symbol, $quantity, $price, $type, $flags, true);
    }

    /**
     * marketQuoteBuy attempts to create a currency order at given market price
     *
     * $quantity = 1;
     * $order = $api->marketQuoteBuy("BNBBTC", $quantity);
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity of the quote to use
     * @param $flags array additional options for order type
     * @return array with error message or the order details
     */
    public function marketQuoteBuy(string $symbol, $quantity, array $flags = [])
    {
        $flags['isQuoteOrder'] = true;

        return $this->order("BUY", $symbol, $quantity, 0, "MARKET", $flags);
    }

    /**
     * marketQuoteBuyTest attempts to create a TEST currency order at given market price
     *
     * @see marketBuy()
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity of the quote to use
     * @param $flags array additional options for order type
     * @return array with error message or the order details
     */
    public function marketQuoteBuyTest(string $symbol, $quantity, array $flags = [])
    {
        $flags['isQuoteOrder'] = true;

        return $this->order("BUY", $symbol, $quantity, 0, "MARKET", $flags, true);
    }

    /**
     * marketBuy attempts to create a currency order at given market price
     *
     * $quantity = 1;
     * $order = $api->marketBuy("BNBBTC", $quantity);
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity required
     * @param $flags array addtional options for order type
     * @return array with error message or the order details
     */
    public function marketBuy(string $symbol, $quantity, array $flags = [])
    {
        return $this->order("BUY", $symbol, $quantity, 0, "MARKET", $flags);
    }

    /**
     * marketBuyTest attempts to create a TEST currency order at given market price
     *
     * @see marketBuy()
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity required
     * @param $flags array addtional options for order type
     * @return array with error message or the order details
     */
    public function marketBuyTest(string $symbol, $quantity, array $flags = [])
    {
        return $this->order("BUY", $symbol, $quantity, 0, "MARKET", $flags, true);
    }


    /**
     * numberOfDecimals() returns the signifcant digits level based on the minimum order amount.
     *
     * $dec = numberOfDecimals(0.00001); // Returns 5
     *
     * @param $val float the minimum order amount for the pair
     * @return integer (signifcant digits) based on the minimum order amount
     */
    public function numberOfDecimals($val = 0.00000001)
    {
        $val = sprintf("%.14f", $val);
        $parts = explode('.', $val);
        $parts[1] = rtrim($parts[1], "0");
        return strlen($parts[1]);
    }

    /**
     * marketQuoteSell attempts to create a currency order at given market price
     *
     * $quantity = 1;
     * $order = $api->marketQuoteSell("BNBBTC", $quantity);
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity of the quote you want to obtain
     * @param $flags array additional options for order type
     * @return array with error message or the order details
     */
    public function marketQuoteSell(string $symbol, $quantity, array $flags = [])
    {
        $flags['isQuoteOrder'] = true;
        $c = $this->numberOfDecimals($this->exchangeInfo()['symbols'][$symbol]['filters'][1]['minQty']);
        $quantity = $this->floorDecimal($quantity, $c);

        return $this->order("SELL", $symbol, $quantity, 0, "MARKET", $flags);
    }

    /**
     * marketQuoteSellTest attempts to create a TEST currency order at given market price
     *
     * @see marketSellTest()
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity of the quote you want to obtain
     * @param $flags array additional options for order type
     * @return array with error message or the order details
     */
    public function marketQuoteSellTest(string $symbol, $quantity, array $flags = [])
    {
        $flags['isQuoteOrder'] = true;

        return $this->order("SELL", $symbol, $quantity, 0, "MARKET", $flags, true);
    }

    /**
     * marketSell attempts to create a currency order at given market price
     *
     * $quantity = 1;
     * $order = $api->marketSell("BNBBTC", $quantity);
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity required
     * @param $flags array addtional options for order type
     * @return array with error message or the order details
     */
    public function marketSell(string $symbol, $quantity, array $flags = [])
    {
        $c = $this->numberOfDecimals($this->exchangeInfo()['symbols'][$symbol]['filters'][1]['minQty']);
        $quantity = $this->floorDecimal($quantity, $c);

        return $this->order("SELL", $symbol, $quantity, 0, "MARKET", $flags);
    }

    /**
     * marketSellTest attempts to create a TEST currency order at given market price
     *
     * @see marketSellTest()
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity required
     * @param $flags array addtional options for order type
     * @return array with error message or the order details
     */
    public function marketSellTest(string $symbol, $quantity, array $flags = [])
    {
        return $this->order("SELL", $symbol, $quantity, 0, "MARKET", $flags, true);
    }

    /**
     * cancel attempts to cancel a currency order
     *
     * $orderid = "123456789";
     * $order = $api->cancel("BNBBTC", $orderid);
     *
     * @param $symbol string the currency symbol
     * @param $orderid string the orderid to cancel
     * @param $flags array of optional options like ["side"=>"sell"]
     * @return array with error message or the order details
     * @throws \Exception
     */
    public function cancel(string $symbol, $orderid, $flags = [])
    {
        $params = [
            "symbol" => $symbol,
            "orderId" => $orderid,
        ];
        return $this->httpRequest("v3/order", "DELETE", array_merge($params, $flags), true);
    }

    /**
     * orderStatus attempts to get orders status
     *
     * $orderid = "123456789";
     * $order = $api->orderStatus("BNBBTC", $orderid);
     *
     * @param $symbol string the currency symbol
     * @param $orderid string the orderid to fetch
     * @return array with error message or the order details
     * @throws \Exception
     */
    public function orderStatus(string $symbol, $orderid)
    {
        return $this->httpRequest("v3/order", "GET", [
            "symbol" => $symbol,
            "orderId" => $orderid,
        ], true);
    }

    /**
     * openOrders attempts to get open orders for all currencies or a specific currency
     *
     * $allOpenOrders = $api->openOrders();
     * $allBNBOrders = $api->openOrders( "BNBBTC" );
     *
     * @param $symbol string the currency symbol
     * @return array with error message or the order details
     * @throws \Exception
     */
    public function openOrders(string $symbol = null)
    {
        $params = [];
        if (is_null($symbol) != true) {
            $params = [
                "symbol" => $symbol,
            ];
        }
        return $this->httpRequest("v3/openOrders", "GET", $params, true);
    }

    /**
     * Cancel all open orders method
     * $api->cancelOpenOrders( "BNBBTC" );
     * @param $symbol string the currency symbol
     * @return array with error message or the order details
     * @throws \Exception
     */
    public function cancelOpenOrders(string $symbol = null)
    {
        $params = [];
        if (is_null($symbol) != true) {
            $params = [
                "symbol" => $symbol,
            ];
        }
        return $this->httpRequest("v3/openOrders", "DELETE", $params, true);
    }

    /**
     * orders attempts to get the orders for all or a specific currency
     *
     * $allBNBOrders = $api->orders( "BNBBTC" );
     *
     * @param $symbol string the currency symbol
     * @param $limit int the amount of orders returned
     * @param $fromOrderId string return the orders from this order onwards
     * @param $params array optional startTime, endTime parameters
     * @return array with error message or array of orderDetails array
     * @throws \Exception
     */
    public function orders(string $symbol, int $limit = 500, int $fromOrderId = 0, array $params = [])
    {
        $params["symbol"] = $symbol;
        $params["limit"] = $limit;
        if ($fromOrderId) {
            $params["orderId"] = $fromOrderId;
        }
        return $this->httpRequest("v3/allOrders", "GET", $params, true);
    }

    /**
     * history Get the complete account trade history for all or a specific currency
     * @deprecated
     * use myTrades() instead
     *
     * $BNBHistory = $api->history("BNBBTC");
     * $limitBNBHistory = $api->history("BNBBTC",5);
     * $limitBNBHistoryFromId = $api->history("BNBBTC",5,3);
     *
     * @param $symbol string the currency symbol
     * @param $limit int the amount of orders returned
     * @param $fromTradeId int (optional) return the orders from this order onwards. negative for all
     * @param $startTime int (optional) return the orders from this time onwards. null to ignore
     * @param $endTime int (optional) return the orders from this time backwards. null to ignore
     * @return array with error message or array of orderDetails array
     * @throws \Exception
     */
    public function history(string $symbol, int $limit = 500, int $fromTradeId = -1, int $startTime = null, int $endTime = null)
    {
        $parameters = [
            "symbol" => $symbol,
            "limit" => $limit,
        ];
        if ($fromTradeId > 0) {
            $parameters["fromId"] = $fromTradeId;
        }
        if (isset($startTime)) {
            $parameters["startTime"] = $startTime;
        }
        if (isset($endTime)) {
            $parameters["endTime"] = $endTime;
        }

        return $this->httpRequest("v3/myTrades", "GET", $parameters, true);
    }

    /**
     * myTrades
     * another name for history
     * @see history()
     *
     * @return array with error message or array of orderDetails array
     * @throws \Exception
     */
    public function myTrades(string $symbol, int $limit = 500, int $fromTradeId = -1, int $startTime = null, int $endTime = null)
    {
        return $this->history($symbol, $limit, $fromTradeId, $startTime, $endTime);
    }

    /**
     * useServerTime adds the 'useServerTime'=>true to the API request to avoid time errors
     *
     * $api->useServerTime();
     *
     * @return null
     * @throws \Exception
     */
    public function useServerTime()
    {
        $request = $this->httpRequest("v3/time");
        if (isset($request['serverTime'])) {
            $this->info['timeOffset'] = $request['serverTime'] - (microtime(true) * 1000);
        }
    }

    /**
     * time Gets the server time
     *
     * $time = $api->time();
     *
     * @return array with error message or array with server time key
     * @throws \Exception
     */
    public function time()
    {
        return $this->httpRequest("v3/time");
    }

    /**
     * exchangeInfo -  Gets the complete exchange info, including limits, currency options etc.
     *
     * @link https://binance-docs.github.io/apidocs/spot/en/#exchange-information
     *
     * $info = $api->exchangeInfo();
     * $info = $api->exchangeInfo('BTCUSDT');
     *
     * $arr = array('ATABUSD','BTCUSDT');
     * $info = $api->exchangeInfo($arr);
     *
     * @property int $weight 10
     *
     * @param string|array  $symbols  (optional)  A symbol or an array of symbols, default is empty
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function exchangeInfo($symbols = null)
    {
        if (!$this->exchangeInfo) {
            $arr = array();
            $arr['symbols'] = array();
            $parameters = [];

            if ($symbols) {
                if (gettype($symbols) == "string") {
                    $parameters["symbol"] = $symbols;
                    $arr = $this->httpRequest("v3/exchangeInfo", "GET", $parameters);
                }
                if (gettype($symbols) == "array")  {
                    $arr = $this->httpRequest("v3/exchangeInfo?symbols=" . '["' . implode('","', $symbols) . '"]');
                }
            } else {
                $arr = $this->httpRequest("v3/exchangeInfo");
            }

            $this->exchangeInfo = $arr;
            $this->exchangeInfo['symbols'] = null;

            foreach ($arr['symbols'] as $key => $value) {
                $this->exchangeInfo['symbols'][$value['symbol']] = $value;
            }
        }

        return $this->exchangeInfo;
    }

    /**
     * assetDetail - Fetch details of assets supported on Binance
     *
     * @link https://binance-docs.github.io/apidocs/spot/en/#asset-detail-user_data
     *
     * @property int $weight 1
     *
     * @param string $asset  (optional)  Should be an asset, e.g. BNB or empty to get the full list
     *
     * @return array containing the response
     */
    public function assetDetail($asset = '')
    {
        $params["sapi"] = true;
        if ($asset != '' && gettype($asset) == 'string')
            $params['asset'] = $asset;
        $arr = $this->httpRequest("v1/asset/assetDetail", 'GET', $params, true);
        // if asset was set, no backward compatibility needed as this was implemented later
        if (isset($params['asset']))
            return $arr;

        // wrap into another array for backward compatibility with the old wapi one
        if (!empty($arr['BTC']['withdrawFee'])) {
            return array(
                'success'     => 1,
                'assetDetail' => $arr,
                );
        } else {
            return array(
                'success'     => 0,
                'assetDetail' => array(),
                );

        }
    }

    /**
     * userAssetDribbletLog - Log of the conversion of the dust assets to BNB
     * @deprecated
     */
    public function userAssetDribbletLog()
    {
        $params["wapi"] = true;
        trigger_error('Deprecated - function will disappear on 2021-08-01 from Binance. Please switch to $api->dustLog().', E_USER_DEPRECATED);
        return $this->httpRequest("v3/userAssetDribbletLog.html", 'GET', $params, true);
    }

    /**
     * dustLog - Log of the conversion of the dust assets to BNB
     *
     * @link https://binance-docs.github.io/apidocs/spot/en/#dustlog-user_data
     *
     * @property int $weight 1
     *
     * @param long  $startTime  (optional)  Start time, e.g. 1617580799000
     * @param long  $endTime    (optional)  End time, e.g. 1617580799000. Endtime is mandatory if startTime is set.
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function dustLog($startTime = NULL, $endTime = NULL)
    {
        $params["sapi"] = true;
        if (!empty($startTime) && !empty($endTime)) {
            $params['startTime'] = $startTime;
            $params['endTime'] = $endTime;
        }

        return $this->httpRequest("v1/asset/dribblet", 'GET', $params, true);
    }

    /**
     * dustTransfer - Convert dust assets ( < 0.001 BTC) to BNB
     *
     * @link https://binance-docs.github.io/apidocs/spot/en/#dust-transfer-user_data
     *
     * @property int $weight 1
     *
     * @param string|array  $assets  (mandatory)  Asset(s), e.g. IOST or array like ['IOST','AAVE','CHZ']
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function dustTransfer($assets)
    {
        $params["sapi"] = true;
        $params["asset"] = $assets;

        return $this->httpRequest("v1/asset/dust", 'POST', $params, true);
    }

    /**
     * Fetch current(daily) trade fee of symbol, values in percentage.
     * for more info visit binance official api document
     *
     * $symbol = "BNBBTC"; or any other symbol or even a set of symbols in an array
     * @param string $symbol
     * @return mixed
     */
    public function tradeFee(string $symbol)
    {
        $params = [
            "symbol" => $symbol,
            "sapi" => true,
        ];
        return $this->httpRequest("v1/asset/tradeFee", 'GET', $params, true);
    }

    /**
     * commissionFee - Fetch commission trade fee
     *
     * @link https://binance-docs.github.io/apidocs/spot/en/#trade-fee-user_data
     *
     * @property int $weight 1
     *
     * @param string $symbol  (optional)  Should be a symbol, e.g. BNBUSDT or empty to get the full list
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function commissionFee($symbol = '')
    {
        $params = array('sapi' => true);
        if ($symbol != '' && gettype($symbol) == 'string')
            $params['symbol'] = $symbol;

        return $this->httpRequest("v1/asset/tradeFee", 'GET', $params, true);
    }

    /**
     * withdraw - Submit a withdraw request to move an asset to another wallet
     *
     * @link https://binance-docs.github.io/apidocs/spot/en/#withdraw-sapi
     *
     * @example https://github.com/jaggedsoft/php-binance-api#withdraw   Standard withdraw
     * @example https://github.com/jaggedsoft/php-binance-api#withdraw-with-addresstag   Withdraw with addressTag for e.g. XRP
     *
     * @property int $weight 1
     *
     * @param string $asset               (mandatory)  An asset, e.g. BTC
     * @param string $address             (mandatory)  The address where to send, e.g. 1C5gqLRs96Xq4V2ZZAR1347yUCpHie7sa or 44tLjmXrQNrWJ5NBsEj2R77ZBEgDa3fEe9GLpSf2FRmhexPvfYDUAB7EXX1Hdb3aMQ9FLqdJ56yaAhiXoRsceGJCRS3Jxkn
     * @param string $amount              (mandatory)  The amount, e.g. 0.2
     * @param string $addressTag          (optional)   Mandatory secondary address for some assets (XRP,XMR,etc), e.g. 0e5e38a01058dbf64e53a4333a5acf98e0d5feb8e523d32e3186c664a9c762c1
     * @param string $addressName         (optional)   Description of the address
     * @param string $transactionFeeFlag  (optional)   When making internal transfer, true for returning the fee to the destination account; false for returning the fee back to the departure account.
     * @param string $network             (optional)
     * @param string $orderId             (optional)   Client id for withdraw
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function withdraw(string $asset, string $address, $amount, $addressTag = null, $addressName = "", bool $transactionFeeFlag = false, $network = null, $orderId = null)
    {
        $options = [
            "coin" => $asset,
            "address" => $address,
            "amount" => $amount,
            "sapi" => true,
        ];

        if (is_null($addressName) === false && empty($addressName) === false) {
            $options['name'] = str_replace(' ', '%20', $addressName);
        }
        if (is_null($addressTag) === false && empty($addressTag) === false) {
            $options['addressTag'] = $addressTag;
        }
        if ($transactionFeeFlag) $options['transactionFeeFlag'] = true;

        if (is_null($network) === false && empty($network) === false) {
            $options['network'] = $network;
        }
        if (is_null($orderId) === false && empty($orderId) === false) {
            $options['withdrawOrderId'] = $orderId;
        }
        return $this->httpRequest("v1/capital/withdraw/apply", "POST", $options, true);
    }

    /**
     * depositAddress - Get the deposit address for an asset
     *
     * @link https://binance-docs.github.io/apidocs/spot/en/#deposit-address-supporting-network-user_data
     *
     * @property int $weight 1
     *
     * @param string $asset    (mandatory)  An asset, e.g. BTC
     * @param string $network  (optional)   You can get network in networkList from /sapi/v1/capital/config/getall
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function depositAddress(string $asset, $network = null)
    {
        $params = [
            "sapi" => true,
            "coin" => $asset,
        ];
        if (is_null($network) === false && empty($network) === false) {
            $params['network'] = $network;
        }

        $return = $this->httpRequest("v1/capital/deposit/address", "GET", $params, true);

        // Adding for backwards compatibility with wapi
        $return['asset'] = $return['coin'];
        $return['addressTag'] = $return['tag'];

        if (!empty($return['address'])) {
            $return['success'] = 1;
        } else {
            $return['success'] = 0;
        }

        return $return;
    }

    /**
     * depositHistory - Get the deposit history for one or all assets
     *
     * @link https://binance-docs.github.io/apidocs/spot/en/#deposit-history-supporting-network-user_data
     *
     * @property int $weight 1
     *
     * @param string $asset    (optional)  An asset, e.g. BTC - or leave empty for all
     * @param array  $params   (optional)  An array of additional parameters that the API endpoint allows
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function depositHistory(string $asset = null, array $params = [])
    {
        $params["sapi"] = true;
        if (is_null($asset) === false) {
            $params['coin'] = $asset;
        }
        $return = $this->httpRequest("v1/capital/deposit/hisrec", "GET", $params, true);

        // Adding for backwards compatibility with wapi
        foreach ($return as $key=>$item) {
            $return[$key]['asset'] = $item['coin'];
        }

        return $return;

    }

    /**
     * withdrawHistory - Get the withdraw history for one or all assets
     *
     * @link https://binance-docs.github.io/apidocs/spot/en/#withdraw-history-supporting-network-user_data
     *
     * @property int $weight 1
     *
     * @param string $asset    (optional)  An asset, e.g. BTC - or leave empty for all
     * @param array  $params   (optional)  An array of additional parameters that the API endpoint allows: status, offset, limit, startTime, endTime
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function withdrawHistory(string $asset = null, array $params = [])
    {
        $params["sapi"] = true;
        if (is_null($asset) === false) {
            $params['coin'] = $asset;
        }
        // Wrapping in array for backwards compatibility with wapi
        $return = array(
            'withdrawList' => $this->httpRequest("v1/capital/withdraw/history", "GET", $params, true)
            );

        // Adding for backwards compatibility with wapi
        $return['success'] = 1;

        return $return;
    }

    /**
     * withdrawFee - Get the withdrawal fee for an asset
     *
     * @property int $weight 1
     *
     * @param string $asset    (mandatory)  An asset, e.g. BTC
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function withdrawFee(string $asset)
    {
        $return = $this->assetDetail();

        if (isset($return['success'], $return['assetDetail'], $return['assetDetail'][$asset]) && $return['success']) {
            return $return['assetDetail'][$asset];
        } else {
            return array();
        }
    }

    /**
     * transfer - Transfer asset between accounts
     * possible types of transfer are:
     * - MAIN_UMFUTURE - Spot account transfer to USDⓈ-M Futures account
     * - MAIN_CMFUTURE - Spot account transfer to COIN-M Futures account
     * - MAIN_MARGIN - Spot account transfer to Margin（cross）account
     * - UMFUTURE_MAIN - USDⓈ-M Futures account transfer to Spot account
     * - UMFUTURE_MARGIN - USDⓈ-M Futures account transfer to Margin（cross）account
     * - CMFUTURE_MAIN - COIN-M Futures account transfer to Spot account
     * - CMFUTURE_MARGIN - COIN-M Futures account transfer to Margin(cross) account
     * - MARGIN_MAIN - Margin（cross）account transfer to Spot account
     * - MARGIN_UMFUTURE - Margin（cross）account transfer to USDⓈ-M Futures
     * - MARGIN_CMFUTURE - Margin（cross）account transfer to COIN-M Futures
     * - ISOLATEDMARGIN_MARGIN - Isolated margin account transfer to Margin(cross) account
     * - MARGIN_ISOLATEDMARGIN - Margin(cross) account transfer to Isolated margin account
     * - ISOLATEDMARGIN_ISOLATEDMARGIN - Isolated margin account transfer to Isolated margin account
     * - MAIN_FUNDING - Spot account transfer to Funding account
     * - FUNDING_MAIN - Funding account transfer to Spot account
     * - FUNDING_UMFUTURE - Funding account transfer to UMFUTURE account
     * - UMFUTURE_FUNDING - UMFUTURE account transfer to Funding account
     * - MARGIN_FUNDING - MARGIN account transfer to Funding account
     * - FUNDING_MARGIN - Funding account transfer to Margin account
     * - FUNDING_CMFUTURE - Funding account transfer to CMFUTURE account
     * - CMFUTURE_FUNDING - CMFUTURE account transfer to Funding account
     * - MAIN_OPTION - Spot account transfer to Options account
     * - OPTION_MAIN - Options account transfer to Spot account
     * - UMFUTURE_OPTION - USDⓈ-M Futures account transfer to Options account
     * - OPTION_UMFUTURE - Options account transfer to USDⓈ-M Futures account
     * - MARGIN_OPTION - Margin（cross）account transfer to Options account
     * - OPTION_MARGIN - Options account transfer to Margin（cross）account
     * - FUNDING_OPTION - Funding account transfer to Options account
     * - OPTION_FUNDING - Options account transfer to Funding account
     * - MAIN_PORTFOLIO_MARGIN - Spot account transfer to Portfolio Margin account
     * - PORTFOLIO_MARGIN_MAIN - Portfolio Margin account transfer to Spot account
     *
     * @link https://developers.binance.com/docs/wallet/asset/user-universal-transfer
     *
     * @property int $weight 900
     *
     * @param string $type (mandatory) type of transfer, e.g. MAIN_MARGIN
     * @param string $asset (mandatory) an asset, e.g. BTC
     * @param string $amount (mandatory) the amount to transfer
     * @param string $fromSymbol (optional) must be sent when type are ISOLATEDMARGIN_MARGIN and ISOLATEDMARGIN_ISOLATEDMARGIN
     * @param string $toSymbol (optional) must be sent when type are MARGIN_ISOLATEDMARGIN and ISOLATEDMARGIN_ISOLATEDMARGIN
     * @param int    $recvWindow (optional) the time in milliseconds to wait for the transfer to complete
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function transfer(string $type, string $asset, string $amount, $fromSymbol = null, $toSymbol = null, int $recvWindow = null)
    {
        $params = [
            'type' => $type,
            'asset' => $asset,
            'amount' => $amount,
        ];
        // todo: check this method with real account
        if ($fromSymbol) {
            $params['fromSymbol'] = $fromSymbol;
        }
        if ($toSymbol) {
            $params['toSymbol'] = $toSymbol;
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }

        return $this->httpRequest("v1/asset/transfer", 'POST', $params, true);
    }

    /**
     * transfersHistory - get the transfer history between accounts
     *
     * @link https://developers.binance.com/docs/wallet/asset/query-user-universal-transfer
     *
     * @property int $weight 1
     *
     * @param string $type (optional) type of transfer, e.g. MAIN_MARGIN (@see transfer())
     * @param string $startTime (optional) start time in milliseconds
     * @param string $endTime (optional) end time in milliseconds
     * @param int    $limit (optional) the number of records to return (default 10, max 100)
     * @param int    $current (optional) default 1
     * @param string $fromSymbol (optional) must be sent when type are ISOLATEDMARGIN_MARGIN and ISOLATEDMARGIN_ISOLATEDMARGIN
     * @param string $toSymbol (optional) must be sent when type are MARGIN_ISOLATEDMARGIN and ISOLATEDMARGIN_ISOLATEDMARGIN
     * @param int    $recvWindow (optional) the time in milliseconds to wait for the transfer to complete
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function transfersHistory(string $type, $startTime = null, $endTime = null, $limit = null, $current = null, $fromSymbol = null, $toSymbol = null, $recvWindow = null)
    {
        $params = [
            'type' => $type,
        ];
        // todo: check this method with real account
        if ($startTime) {
            $params['startTime'] = $startTime;
        }
        if ($endTime) {
            $params['endTime'] = $endTime;
        }
        if ($limit) {
            $params['size'] = $limit;
        }
        if ($current) {
            $params['current'] = $current;
        }
        if ($fromSymbol) {
            $params['fromSymbol'] = $fromSymbol;
        }
        if ($toSymbol) {
            $params['toSymbol'] = $toSymbol;
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }

        return $this->httpRequest("v1/asset/transfer", 'GET', $params, true);
    }

    /**
     * prices get all the current prices
     *
     * $ticker = $api->prices();
     *
     * @return array with error message or array of all the currencies prices
     * @throws \Exception
     */
    public function prices()
    {
        return $this->priceData($this->httpRequest("v3/ticker/price"));
    }

    /**
     * price get the latest price of a symbol
     *
     * $price = $api->price( "ETHBTC" );
     *
     * @return array with error message or array with symbol price
     * @throws \Exception
     */
    public function price(string $symbol)
    {
        $ticker = $this->httpRequest("v3/ticker/price", "GET", ["symbol" => $symbol]);

        return $ticker['price'];
    }

    /**
     * bookPrices get all bid/asks prices
     *
     * $ticker = $api->bookPrices();
     *
     * @return array with error message or array of all the book prices
     * @throws \Exception
     */
    public function bookPrices()
    {
        return $this->bookPriceData($this->httpRequest("v3/ticker/bookTicker"));
    }

    /**
     * account get all information about the api account
     *
     * $account = $api->account();
     *
     * @return array with error message or array of all the account information
     * @throws \Exception
     */
    public function account()
    {
        return $this->httpRequest("v3/account", "GET", [], true);
    }

    /**
     * prevDay get 24hr ticker price change statistics for symbols
     *
     * $prevDay = $api->prevDay("BNBBTC");
     *
     * @param $symbol (optional) symbol to get the previous day change for
     * @return array with error message or array of prevDay change
     * @throws \Exception
     */
    public function prevDay(string $symbol = null)
    {
        $additionalData = [];
        if (is_null($symbol) === false) {
            $additionalData = [
                'symbol' => $symbol,
            ];
        }
        return $this->httpRequest("v1/ticker/24hr", "GET", $additionalData);
    }

    /**
     * aggTrades get Market History / Aggregate Trades
     *
     * $trades = $api->aggTrades("BNBBTC");
     *
     * @param $symbol string the symbol to get the trade information for
     * @return array with error message or array of market history
     * @throws \Exception
     */
    public function aggTrades(string $symbol)
    {
        return $this->tradesData($this->httpRequest("v1/aggTrades", "GET", [
            "symbol" => $symbol,
        ]));
    }

    /**
     * historicalTrades - Get historical trades for a specific currency
     *
     * @link https://github.com/binance/binance-spot-api-docs/blob/master/rest-api.md#old-trade-lookup-market_data
     * @link https://binance-docs.github.io/apidocs/spot/en/#old-trade-lookup
     *
     * @property int $weight 5
     * Standard weight is 5 but if no tradeId is given, weight is 1
     *
     * @param string $symbol  (mandatory) to query, e.g. BNBBTC
     * @param int    $limit   (optional)  limit the amount of trades, default 500, max 1000
     * @param int    $tradeId (optional)  return the orders from this orderId onwards, negative to get recent ones
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function historicalTrades(string $symbol, int $limit = 500, int $tradeId = -1)
    {
        $parameters = [
            "symbol" => $symbol,
            "limit" => $limit,
        ];
        if ($tradeId > 0) {
            $parameters["fromId"] = $tradeId;
        } else {
            // if there is no tradeId given, we can use v3/trades, weight is 1 and not 5
            return $this->httpRequest("v3/trades", "GET", $parameters);
        }

        // The endpoint cannot handle extra parameters like 'timestamp' or 'signature',
        // but it needs the http header with the key so we need to construct it here
        $query = http_build_query($parameters, '', '&');
        return $this->httpRequest("v3/historicalTrades?$query");
    }

    /**
     * depth get Market depth
     *
     * $depth = $api->depth("ETHBTC");
     *
     * @param $symbol string the symbol to get the depth information for
     * @param $limit int set limition for number of market depth data
     * @return array with error message or array of market depth
     * @throws \Exception
     */
    public function depth(string $symbol, int $limit = 100)
    {
        if (is_int($limit) === false) {
            $limit = 100;
        }

        if (isset($symbol) === false || is_string($symbol) === false) {
            // WPCS: XSS OK.
            echo "asset: expected bool false, " . gettype($symbol) . " given" . PHP_EOL;
        }
        $json = $this->httpRequest("v1/depth", "GET", [
            "symbol" => $symbol,
            "limit" => $limit,
        ]);
        if (isset($this->info[$symbol]) === false) {
            $this->info[$symbol] = [];
        }
        $this->info[$symbol]['firstUpdate'] = $json['lastUpdateId'];
        return $this->depthData($symbol, $json);
    }

    /**
     * balances get balances for the account assets
     *
     * $balances = $api->balances();
     *
     * @param string $market_type (optional) market type - "spot" or "futures" (default is "spot")
     * @param int    $recvWindow (optional) the time in milliseconds to wait for the transfer to complete (not for spot)
     * @param string $api_version (optional) not for spot - the api version to use (default is v2)
     *
     * @return array with error message or array of balances
     * @throws \Exception
     */
    public function balances(string $market_type = 'spot', $recvWindow = null, string $api_version = 'v2')
    {
        $is_spot = $market_type === 'spot';
        $params = [];
        if ($is_spot) {
            $url = "v3/account";
        } else {
            $params['fapi'] = true;
            if ($recvWindow) {
                $params['recvWindow'] = $recvWindow;
            }
            if ($api_version === 'v2') {
                $url = "v2/balance";
            } else if ($api_version === 'v3') {
                $url = "v3/balance";
            } else {
                throw new \Exception("Invalid API version specified. Use 'v2' or 'v3'.");
            }
        }
        $response = $this->httpRequest($url, "GET", $params, true);
        if (is_array($response) === false) {
            echo "Error: unable to fetch your account details" . PHP_EOL;
        }
        if (empty($response) || ($is_spot && (isset($response['balances']) === false || empty($response['balances'])))) {
            echo "Error: your balances were empty or unset" . PHP_EOL;
            return [];
        }
        return $this->balanceData($response, $market_type);
    }

    /**
     * coins get list coins
     *
     * $coins = $api->coins();
     * @return array with error message or array containing coins
     * @throws \Exception
     */
    public function coins()
    {
        return $this->httpRequest("v1/capital/config/getall", 'GET', [ 'sapi' => true ], true);
    }

    /**
     * getProxyUriString get Uniform Resource Identifier string assocaited with proxy config
     *
     * $balances = $api->getProxyUriString();
     *
     * @return string uri
     */
    public function getProxyUriString()
    {
        $uri = isset($this->proxyConf['proto']) ? $this->proxyConf['proto'] : "http";
        // https://curl.haxx.se/libcurl/c/CURLOPT_PROXY.html
        $supportedProtocols = array(
            'http',
            'https',
            'socks4',
            'socks4a',
            'socks5',
            'socks5h',
        );

        if (in_array($uri, $supportedProtocols) === false) {
            // WPCS: XSS OK.
            echo "Unknown proxy protocol '" . $this->proxyConf['proto'] . "', supported protocols are " . implode(", ", $supportedProtocols) . PHP_EOL;
        }

        $uri .= "://";
        $uri .= isset($this->proxyConf['address']) ? $this->proxyConf['address'] : "localhost";

        if (isset($this->proxyConf['address']) === false) {
            // WPCS: XSS OK.
            echo "warning: proxy address not set defaulting to localhost" . PHP_EOL;
        }

        $uri .= ":";
        $uri .= isset($this->proxyConf['port']) ? $this->proxyConf['port'] : "1080";

        if (isset($this->proxyConf['address']) === false) {
            // WPCS: XSS OK.
            echo "warning: proxy port not set defaulting to 1080" . PHP_EOL;
        }

        return $uri;
    }

    /**
     * setProxy set proxy config by passing in an array of the proxy configuration
     *
     * $proxyConf = [
     * 'proto' => 'tcp',
     * 'address' => '192.168.1.1',
     * 'port' => '8080',
     * 'user' => 'dude',
     * 'pass' => 'd00d'
     * ];
     *
     * $api->setProxy( $proxyconf );
     *
     * @return null
     */
    public function setProxy(array $proxyconf)
    {
        $this->proxyConf = $proxyconf;
    }


    protected function curl_exec($curl)
    {
        return curl_exec($curl);
    }

    protected function curl_set_url($curl, $endpoint) {
        curl_setopt($curl, CURLOPT_URL, $endpoint);
    }

    protected function curl_set_body($curl, $option, $query) {
        curl_setopt($curl, $option, $query);
    }

    /**
     * httpRequest curl wrapper for all http api requests.
     * You can't call this function directly, use the helper functions
     *
     * @see buy()
     * @see sell()
     * @see marketBuy()
     * @see marketSell() $this->httpRequest( "https://api.binance.com/api/v1/ticker/24hr");
     *
     * @param $url string the endpoint to query, typically includes query string
     * @param $method string this should be typically GET, POST or DELETE
     * @param $params array addtional options for the request
     * @param $signed bool true or false sign the request with api secret
     * @return array containing the response
     * @throws \Exception
     */
    protected function httpRequest(string $url, string $method = "GET", array $params = [], bool $signed = false)
    {
        if (function_exists('curl_init') === false) {
            throw new \Exception("Sorry cURL is not installed!");
        }

        if ($this->caOverride === false) {
            if (file_exists(getcwd() . '/ca.pem') === false) {
                $this->downloadCurlCaBundle();
            }
        }

        $base = $this->base;
        if ($this->useTestnet) {
            $base = $this->baseTestnet;
        }

        if (isset($params['wapi'])) {
            if ($this->useTestnet) {
                throw new \Exception("wapi endpoints are not available in testnet");
            }
            unset($params['wapi']);
            $base = $this->wapi;
        }

        if (isset($params['sapi'])) {
            if ($this->useTestnet) {
                throw new \Exception("sapi endpoints are not available in testnet");
            }
            unset($params['sapi']);
            $base = $this->sapi;
        }

        if (isset($params['fapi'])) {
            unset($params['fapi']);
            $base = $this->useTestnet ? $this->fapiTestnet : $this->fapi;
        }

        if (isset($params['fapiData'])) {
            if ($this->useTestnet) {
                throw new \Exception("fapiData endpoints are not available in testnet");
            }
            unset($params['fapiData']);
            $base = $this->fapiData;
        }

        if (isset($params['dapi'])) {
            unset($params['dapi']);
            $base = $this->useTestnet ? $this->dapiTestnet : $this->dapi;
        }

        if (isset($params['dapiData'])) {
            if ($this->useTestnet) {
                throw new \Exception("dapiData endpoints are not available in testnet");
            }
            unset($params['dapiData']);
            $base = $this->dapiData;
        }

        if (isset($params['papi'])) {
            if ($this->useTestnet) {
                throw new \Exception("papi endpoints are not available in testnet");
            }
            unset($params['papi']);
            $base = $this->papi;
        }

        if (isset($params['bapi'])) {
            if ($this->useTestnet) {
                throw new \Exception("bapi endpoints are not available in testnet");
            }
            unset($params['bapi']);
            $base = $this->bapi;
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_VERBOSE, $this->httpDebug);

        //set custom headers if any
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => $this->headers,
            // Optional: other cURL options
        ]);

        $query = $this->binance_build_query($params);

        // signed with params
        if ($signed === true) {
            if (empty($this->api_key)) {
                throw new \Exception("signedRequest error: API Key not set!");
            }

            if (empty($this->api_secret)) {
                throw new \Exception("signedRequest error: API Secret not set!");
            }

            $ts = (microtime(true) * 1000) + $this->info['timeOffset'];
            $params['timestamp'] = number_format($ts, 0, '.', '');
            $query = $this->binance_build_query($params);
            $query = str_replace([ '%40' ], [ '@' ], $query);//if send data type "e-mail" then binance return: [Signature for this request is not valid.]
            $signature = hash_hmac('sha256', $query, $this->api_secret);
            if ($method === "POST") {
                $endpoint = $base . $url;
                $params['signature'] = $signature; // signature needs to be inside BODY
                $query = $this->binance_build_query($params); // rebuilding query
            } else {
                $endpoint = $base . $url . '?' . $query . '&signature=' . $signature;
            }

            $this->curl_set_url($curl, $endpoint);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'X-MBX-APIKEY: ' . $this->api_key,
            ));
        }
        // params so buildquery string and append to url
        elseif (count($params) > 0) {
            $this->curl_set_url($curl, $base . $url . '?' . $query);
        }
        // no params so just the base url
        else {
            $this->curl_set_url($curl,  $base . $url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'X-MBX-APIKEY: ' . $this->api_key,
            ));
        }
        curl_setopt($curl, CURLOPT_USERAGENT, "User-Agent: Mozilla/4.0 (compatible; PHP Binance API)");
        // Post and postfields
        if ($method === "POST") {
            curl_setopt($curl, CURLOPT_POST, true);
            $this->curl_set_body($curl, CURLOPT_POSTFIELDS, $query);
        }
        // Delete Method
        if ($method === "DELETE") {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        }

        // PUT Method
        if ($method === "PUT") {
            curl_setopt($curl, CURLOPT_PUT, true);
        }

        // proxy settings
        if (is_array($this->proxyConf)) {
            curl_setopt($curl, CURLOPT_PROXY, $this->getProxyUriString());
            if (isset($this->proxyConf['user']) && isset($this->proxyConf['pass'])) {
                curl_setopt($curl, CURLOPT_PROXYUSERPWD, $this->proxyConf['user'] . ':' . $this->proxyConf['pass']);
            }
        }
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);

        // set user defined curl opts last for overriding
        foreach ($this->curlOpts as $key => $value) {
            curl_setopt($curl, constant($key), $value);
        }

        if ($this->caOverride === false) {
            if (file_exists(getcwd() . '/ca.pem') === false) {
                $this->downloadCurlCaBundle();
            }
        }

        $output = $this->curl_exec($curl);
        // Check if any error occurred
        // if (curl_errno($curl) > 0) {
        //     // should always output error, not only on httpdebug
        //     // not outputing errors, hides it from users and ends up with tickets on github
        //     throw new \Exception('Curl error: ' . curl_error($curl));
        // }

        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = $this->get_headers_from_curl_response($output);
        $output = substr($output, $header_size);

        curl_close($curl);

        $json = json_decode($output, true);

        $this->lastRequest = [
            'url' => $url,
            'method' => $method,
            'params' => $params,
            'header' => $header,
            'json' => $json
        ];

        if (isset($header['x-mbx-used-weight'])) {
            $this->setXMbxUsedWeight($header['x-mbx-used-weight']);
        }

        if (isset($header['x-mbx-used-weight-1m'])) {
            $this->setXMbxUsedWeight1m($header['x-mbx-used-weight-1m']);
        }

        if (isset($json['msg']) && !empty($json['msg'])) {
            if ($json['msg'] !== 'success' && $url != 'v1/system/status' && $url != 'v3/systemStatus.html' && $url != 'v3/accountStatus.html' && $url != 'v1/allOpenOrders') {
                // should always output error, not only on httpdebug
                // not outputing errors, hides it from users and ends up with tickets on github
                throw new \Exception('signedRequest error: '.print_r($output, true));
            }
        }
        $this->transfered += strlen($output);
        $this->requestCount++;
        return $json;
    }

    /**
     * binance_build_query - Wrapper for http_build_query to allow arrays as parameters
     *
     * sapi v1/asset/dust can have an array, so it needs a conversion
     *
     * @param array  $params  (mandatory)   Parameters to convert to http query
     *
     * @return array containing the response
     * @throws \Exception
     */
    protected function binance_build_query($params = [])
    {
        $new_arr = array();
        $query_add = '';
        foreach ($params as $label=>$item) {
            if ( gettype($item) == 'array' ) {
                foreach ($item as $arritem) {
                    $query_add = $label . '=' . $arritem . '&' . $query_add;
                }
            } else {
                $new_arr[$label] = $item;
            }
        }
        $query = http_build_query($new_arr, '', '&');
        $query = $query_add . $query;

        return $query;
    }

    /**
     * Converts the output of the CURL header to an array
     *
     * @param $header string containing the response
     * @return array headers converted to an array
     */
    public function get_headers_from_curl_response(string $header)
    {
        $headers = array();
        $header_text = substr($header, 0, strpos($header, "\r\n\r\n"));

        foreach (explode("\r\n", $header_text) as $i => $line)
            if ($i === 0)
                $headers['http_code'] = $line;
            else {
                list ($key, $value) = explode(': ', $line);
                $headers[$key] = $value;
            }

        return $headers;
    }

    /**
     * order formats the orders before sending them to the curl wrapper function
     * You can call this function directly or use the helper functions
     *
     * @see buy()
     * @see sell()
     * @see marketBuy()
     * @see marketSell() $this->httpRequest( "https://api.binance.com/api/v1/ticker/24hr");
     *
     * @param $side string typically "BUY" or "SELL"
     * @param $symbol string to buy or sell
     * @param $quantity string in the order
     * @param $price string for the order
     * @param $type string is determined by the symbol bu typicall LIMIT, STOP_LOSS_LIMIT etc.
     * @param $flags array additional transaction options
     * @param $test bool whether to test or not, test only validates the query
     * @return array containing the response
     * @throws \Exception
     */
    public function order(string $side, string $symbol, $quantity, $price, string $type = "LIMIT", array $flags = [], bool $test = false)
    {
        $opt = [
            "symbol" => $symbol,
            "side" => $side,
            "type" => $type,
            "quantity" => $quantity,
            "recvWindow" => 60000,
        ];

        // someone has preformated there 8 decimal point double already
        // dont do anything, leave them do whatever they want
        if (gettype($price) !== "string") {
            // for every other type, lets format it appropriately
            $price = number_format($price, 8, '.', '');
        }

        if (is_numeric($quantity) === false) {
            // WPCS: XSS OK.
            echo "warning: quantity expected numeric got " . gettype($quantity) . PHP_EOL;
        }

        if (is_string($price) === false) {
            // WPCS: XSS OK.
            echo "warning: price expected string got " . gettype($price) . PHP_EOL;
        }

        if ($type === "LIMIT" || $type === "STOP_LOSS_LIMIT" || $type === "TAKE_PROFIT_LIMIT") {
            $opt["price"] = $price;
            $opt["timeInForce"] = "GTC";
        }

        if ($type === "MARKET" && isset($flags['isQuoteOrder']) && $flags['isQuoteOrder']) {
            unset($opt['quantity']);
            $opt['quoteOrderQty'] = $quantity;
        }

        if (isset($flags['stopPrice'])) {
            $opt['stopPrice'] = $flags['stopPrice'];
        }

        if (isset($flags['icebergQty'])) {
            $opt['icebergQty'] = $flags['icebergQty'];
        }

        if (isset($flags['newOrderRespType'])) {
            $opt['newOrderRespType'] = $flags['newOrderRespType'];
        }

        if (isset($flags['newClientOrderId'])) {
            $opt['newClientOrderId'] = $flags['newClientOrderId'];
        } else {
            $opt['newClientOrderId'] = $this->generateSpotClientOrderId();
        }

        $qstring = ($test === false) ? "v3/order" : "v3/order/test";
        return $this->httpRequest($qstring, "POST", $opt, true);
    }

    /**
     * candlesticks get the candles for the given intervals
     * 1m,3m,5m,15m,30m,1h,2h,4h,6h,8h,12h,1d,3d,1w,1M
     *
     * $candles = $api->candlesticks("BNBBTC", "5m");
     *
     * @param $symbol market symbol to get the response for, e.g. ETHUSDT
     * @param $interval string to request
     * @param $limit int limit the amount of candles
     * @param $startTime string request candle information starting from here
     * @param $endTime string request candle information ending here
     * @return array containing the response
     * @throws \Exception
     */
    public function candlesticks(string $symbol, string $interval = "5m", int $limit = null, $startTime = null, $endTime = null)
    {
        if (!isset($this->charts[$symbol])) {
            $this->charts[$symbol] = [];
        }

        $opt = [
            "symbol" => $symbol,
            "interval" => $interval,
        ];

        if ($limit) {
            $opt["limit"] = $limit;
        }

        if ($startTime) {
            $opt["startTime"] = $startTime;
        }

        if ($endTime) {
            $opt["endTime"] = $endTime;
        }

        $response = $this->httpRequest("v1/klines", "GET", $opt);

        if (is_array($response) === false) {
            return [];
        }

        if (count($response) === 0) {
            echo "warning: v1/klines returned empty array, usually a blip in the connection or server" . PHP_EOL;
            return [];
        }

        $ticks = $this->chartData($symbol, $interval, $response);
        $this->charts[$symbol][$interval] = $ticks;
        return $ticks;
    }

    /**
     * balanceData Converts all your balances into a nice array
     *
     * @param $priceData array of prices
     * @return array containing the response
     */
    protected function balanceData(array $array, string $marketType = 'spot')
    {
        $balances = [];
        $is_spot = $marketType === 'spot';
        if (empty($array) || ($is_spot && empty($array['balances']))) {
            // WPCS: XSS OK.
            echo "balanceData error: Please make sure your system time is synchronized: call \$api->useServerTime() before this function" . PHP_EOL;
            echo "ERROR: Invalid request. Please double check your API keys and permissions." . PHP_EOL;
            return [];
        }
        $rawBalances = $is_spot ? $array['balances'] : $array;
        foreach ($rawBalances as $obj) {
            $asset = $obj['asset'];
            $avaliable = 0.00000000;
            $onOrder = 0.00000000;
            if ($is_spot) {
                $avaliable = $obj['free'];
                $onOrder = $obj['locked'];
                $total = $avaliable + $onOrder;
            } else {
                $avaliable = $obj['availableBalance'];
                $total = $obj['balance'];
                $onOrder = $total - $avaliable;
            }
            $balances[$asset] = [
                "available" => $avaliable,
                "onOrder" => $onOrder,
                "total" => $total,
                "info" => $obj,
            ];
        }
        return $balances;
    }

    /**
     * balanceHandler Convert balance WebSocket data into array
     *
     * $data = $this->balanceHandler( $json );
     *
     * @param $json array data to convert
     * @return array
     */
    protected function balanceHandler(array $json)
    {
        $balances = [];
        foreach ($json as $item) {
            $asset = $item->a;
            $available = $item->f;
            $onOrder = $item->l;
            $balances[$asset] = [
                "available" => $available,
                "onOrder" => $onOrder,
            ];
        }
        return $balances;
    }

    /**
     * tickerStreamHandler Convert WebSocket ticker data into array
     *
     * $data = $this->tickerStreamHandler( $json );
     *
     * @param $json object data to convert
     * @return array
     */
    protected function tickerStreamHandler(\stdClass $json)
    {
        return [
            "eventType" => $json->e,
            "eventTime" => $json->E,
            "symbol" => $json->s,
            "priceChange" => $json->p,
            "percentChange" => $json->P,
            "averagePrice" => $json->w,
            "prevClose" => $json->x,
            "close" => $json->c,
            "closeQty" => $json->Q,
            "bestBid" => $json->b,
            "bestBidQty" => $json->B,
            "bestAsk" => $json->a,
            "bestAskQty" => $json->A,
            "open" => $json->o,
            "high" => $json->h,
            "low" => $json->l,
            "volume" => $json->v,
            "quoteVolume" => $json->q,
            "openTime" => $json->O,
            "closeTime" => $json->C,
            "firstTradeId" => $json->F,
            "lastTradeId" => $json->L,
            "numTrades" => $json->n,
        ];
    }

    /**
     * executionHandler Convert WebSocket trade execution into array
     *
     * $data = $this->executionHandler( $json );
     *
     * @param \stdClass $json object data to convert
     * @return array
     */
    protected function executionHandler(\stdClass $json)
    {
        return [
            "symbol" => $json->s,
            "side" => $json->S,
            "orderType" => $json->o,
            "quantity" => $json->q,
            "price" => $json->p,
            "executionType" => $json->x,
            "orderStatus" => $json->X,
            "rejectReason" => $json->r,
            "orderId" => $json->i,
            "clientOrderId" => $json->c,
            "orderTime" => $json->T,
            "eventTime" => $json->E,
        ];
    }

    /**
     * chartData Convert kline data into object
     *
     * $object = $this->chartData($symbol, $interval, $ticks);
     *
     * @param $symbol string of your currency
     * @param $interval string the time interval
     * @param $ticks array of the canbles array
     * @return array object of the chartdata
     */
    protected function chartData(string $symbol, string $interval, array $ticks, string $market_type = "spot", string $kline_type = 'klines')
    {
        $output = [];
        foreach ($ticks as $tick) {
            list($openTime, $open, $high, $low, $close, $assetVolume, $closeTime, $baseVolume, $trades, $assetBuyVolume, $takerBuyVolume, $ignored) = $tick;
            $output[$openTime] = [
                "open" => $open,
                "high" => $high,
                "low" => $low,
                "close" => $close,
                "volume" => $baseVolume,
                "openTime" => $openTime,
                "closeTime" => $closeTime,
                "assetVolume" => $assetVolume,
                "baseVolume" => $baseVolume,
                "trades" => $trades,
                "assetBuyVolume" => $assetBuyVolume,
                "takerBuyVolume" => $takerBuyVolume,
                "ignored" => $ignored,
            ];
        }

        if ($market_type !== "spot") {
            if (!isset($this->info[$market_type])) {
                $this->info[$market_type] = [];
            }
            if (!isset($this->info[$market_type][$symbol])) {
                $this->info[$market_type][$symbol] = [];
            }
            if (!isset($this->info[$market_type][$symbol][$kline_type])) {
                $this->info[$market_type][$symbol][$kline_type] = [];
            }
            if (!isset($this->info[$market_type][$symbol][$kline_type][$interval])) {
                $this->info[$market_type][$symbol][$kline_type][$interval] = [];
            }
            if (isset($openTime)) {
                $this->info[$market_type][$symbol][$kline_type][$interval]['firstOpen'] = $openTime;
            }
        } else {
            if (!isset($this->info[$symbol])) {
                $this->info[$symbol] = [];
            }
            if (!isset($this->info[$symbol][$interval])) {
                $this->info[$symbol][$interval] = [];
            }
            if (isset($openTime)) {
                $this->info[$symbol][$interval]['firstOpen'] = $openTime;
            }
        }

        return $output;
    }

    /**
     * tradesData Convert aggTrades data into easier format
     *
     * $tradesData = $this->tradesData($trades);
     *
     * @param $trades array of trade information
     * @return array easier format for trade information
     */
    protected function tradesData(array $trades)
    {
        $output = [];
        foreach ($trades as $trade) {
            $price = $trade['p'];
            $quantity = $trade['q'];
            $timestamp = $trade['T'];
            $maker = $trade['m'] ? 'true' : 'false';
            $output[] = [
                "price" => $price,
                "quantity" => $quantity,
                "timestamp" => $timestamp,
                "maker" => $maker,
            ];
        }
        return $output;
    }

    /**
     * bookPriceData Consolidates Book Prices into an easy to use object
     *
     * $bookPriceData = $this->bookPriceData($array);
     *
     * @param $array array book prices
     * @return array easier format for book prices information
     */
    protected function bookPriceData(array $array)
    {
        $bookprices = [];
        foreach ($array as $obj) {
            $bookprices[$obj['symbol']] = [
                "bid" => $obj['bidPrice'],
                "bids" => $obj['bidQty'],
                "ask" => $obj['askPrice'],
                "asks" => $obj['askQty'],
            ];
        }
        return $bookprices;
    }

    /**
     * priceData Converts Price Data into an easy key/value array
     *
     * $array = $this->priceData($array);
     *
     * @param $array array of prices
     * @return array of key/value pairs
     */
    protected function priceData(array $array)
    {
        $prices = [];
        foreach ($array as $obj) {
            $prices[$obj['symbol']] = $obj['price'];
        }
        return $prices;
    }

    /**
     * cumulative Converts depth cache into a cumulative array
     *
     * $cumulative = $api->cumulative($depth);
     *
     * @param $depth array cache array
     * @return array cumulative depth cache
     */
    public function cumulative(array $depth)
    {
        $bids = [];
        $asks = [];
        $cumulative = 0;
        foreach ($depth['bids'] as $price => $quantity) {
            $cumulative += $quantity;
            $bids[] = [
                $price,
                $cumulative,
            ];
        }
        $cumulative = 0;
        foreach ($depth['asks'] as $price => $quantity) {
            $cumulative += $quantity;
            $asks[] = [
                $price,
                $cumulative,
            ];
        }
        return [
            "bids" => $bids,
            "asks" => array_reverse($asks),
        ];
    }

    /**
     * highstock Converts Chart Data into array for highstock & kline charts
     *
     * $highstock = $api->highstock($chart, $include_volume);
     *
     * @param $chart array
     * @param $include_volume bool for inclusion of volume
     * @return array highchart data
     */
    public function highstock(array $chart, bool $include_volume = false)
    {
        $array = [];
        foreach ($chart as $timestamp => $obj) {
            $line = [
                $timestamp,
                floatval($obj['open']),
                floatval($obj['high']),
                floatval($obj['low']),
                floatval($obj['close']),
            ];
            if ($include_volume) {
                $line[] = floatval($obj['volume']);
            }

            $array[] = $line;
        }
        return $array;
    }

    /**
     * first Gets first key of an array
     *
     * $first = $api->first($array);
     *
     * @param $array array
     * @return string key or null
     */
    public function first(array $array)
    {
        if (count($array) > 0) {
            return array_keys($array)[0];
        }
        return null;
    }

    /**
     * last Gets last key of an array
     *
     * $last = $api->last($array);
     *
     * @param $array array
     * @return string key or null
     */
    public function last(array $array)
    {
        if (count($array) > 0) {
            return array_keys(array_slice($array, -1))[0];
        }
        return null;
    }

    /**
     * displayDepth Formats nicely for console output
     *
     * $outputString = $api->displayDepth($array);
     *
     * @param $array array
     * @return string of the depth information
     */
    public function displayDepth(array $array)
    {
        $output = '';
        foreach ([
            'asks',
            'bids',
        ] as $type) {
            $entries = $array[$type];
            if ($type === 'asks') {
                $entries = array_reverse($entries);
            }

            $output .= "{$type}:" . PHP_EOL;
            foreach ($entries as $price => $quantity) {
                $total = number_format($price * $quantity, 8, '.', '');
                $quantity = str_pad(str_pad(number_format(rtrim($quantity, '.0')), 10, ' ', STR_PAD_LEFT), 15);
                $output .= "{$price} {$quantity} {$total}" . PHP_EOL;
            }
            // echo str_repeat('-', 32).PHP_EOL;
        }
        return $output;
    }

    /**
     * depthData Formats depth data for nice display
     *
     * $array = $this->depthData($symbol, $json);
     *
     * @param $symbol string to display
     * @param $json array of the depth infomration
     * @return array of the depth information
     */
    protected function depthData(string $symbol, array $json, string $product_type = null)
    {
        $bids = $asks = [];
        foreach ($json['bids'] as $obj) {
            $bids[$obj[0]] = $obj[1];
        }
        foreach ($json['asks'] as $obj) {
            $asks[$obj[0]] = $obj[1];
        }
        $result = [
            "bids" => $bids,
            "asks" => $asks,
        ];
        if (isset($product_type)) {
            $this->depthCache[$symbol][$product_type] = $result;
        } else {
            $this->depthCache[$symbol] = $result;
        }
        return $result;
    }

    /**
     * roundStep rounds quantity with stepSize
     * @param $qty quantity
     * @param $stepSize parameter from exchangeInfo
     * @return rounded value. example: roundStep(1.2345, 0.1) = 1.2
     *
     */
    public function roundStep($qty, $stepSize = 0.1)
    {
        $precision = strlen(substr(strrchr(rtrim($stepSize, '0'), '.'), 1));
        return round((($qty / $stepSize) | 0) * $stepSize, $precision);
    }

    /**
     * roundTicks rounds price with tickSize
     * @param $value price
     * @param $tickSize parameter from exchangeInfo
     * @return rounded value. example: roundStep(1.2345, 0.1) = 1.2
     *
     */
    public function roundTicks($price, $tickSize)
    {
        $precision = strlen(rtrim(substr($tickSize, strpos($tickSize, '.', 1) + 1), '0'));
        return number_format($price, $precision, '.', '');
    }

    /**
     * getTransfered gets the total transfered in b,Kb,Mb,Gb
     *
     * $transfered = $api->getTransfered();
     *
     * @return string showing the total transfered
     */
    public function getTransfered()
    {
        $base = log($this->transfered, 1024);
        $suffixes = array(
            '',
            'K',
            'M',
            'G',
            'T',
        );
        return round(pow(1024, $base - floor($base)), 2) . ' ' . $suffixes[floor($base)];
    }

    /**
     * getRequestCount gets the total number of API calls
     *
     * $apiCount = $api->getRequestCount();
     *
     * @return int get the total number of api calls
     */
    public function getRequestCount()
    {
        return $this->requestCount;
    }

    /**
     * addToTransfered add interger bytes to the total transfered
     * also incrementes the api counter
     *
     * $apiCount = $api->addToTransfered( $int );
     *
     * @return null
     */
    public function addToTransfered(int $int)
    {
        $this->transfered += $int;
        $this->requestCount++;
    }

    /*
     * WebSockets
     */

    /**
     * depthHandler For WebSocket Depth Cache
     *
     * $this->depthHandler($json);
     *
     * @param $json array of depth bids and asks
     * @return null
     */
    protected function depthHandler(array $json)
    {
        $symbol = $json['s'];
        if ($json['u'] <= $this->info[$symbol]['firstUpdate']) {
            return;
        }

        foreach ($json['b'] as $bid) {
            $this->depthCache[$symbol]['bids'][$bid[0]] = $bid[1];
            if ($bid[1] == "0.00000000") {
                unset($this->depthCache[$symbol]['bids'][$bid[0]]);
            }
        }
        foreach ($json['a'] as $ask) {
            $this->depthCache[$symbol]['asks'][$ask[0]] = $ask[1];
            if ($ask[1] == "0.00000000") {
                unset($this->depthCache[$symbol]['asks'][$ask[0]]);
            }
        }
    }

    /**
     * chartHandler For WebSocket Chart Cache
     *
     * $this->chartHandler($symbol, $interval, $json);
     *
     * @param $symbol string to sort
     * @param $interval string time
     * @param \stdClass $json object time
     * @return null
     */
    protected function chartHandler(string $symbol, string $interval, \stdClass $json)
    {
        if (!$this->info[$symbol][$interval]['firstOpen']) { // Wait for /kline to finish loading
            $this->chartQueue[$symbol][$interval][] = $json;
            return;
        }
        $chart = $json->k;
        $symbol = $json->s;
        $interval = $chart->i;
        $tick = $chart->t;
        if ($tick < $this->info[$symbol][$interval]['firstOpen']) {
            return;
        }
        // Filter out of sync data
        $open = $chart->o;
        $high = $chart->h;
        $low = $chart->l;
        $close = $chart->c;
        $volume = $chart->q; // +trades buyVolume assetVolume makerVolume
        $this->charts[$symbol][$interval][$tick] = [
            "open" => $open,
            "high" => $high,
            "low" => $low,
            "close" => $close,
            "volume" => $volume,
        ];
    }

    /**
     * sortDepth Sorts depth data for display & getting highest bid and lowest ask
     *
     * $sorted = $api->sortDepth($symbol, $limit);
     *
     * @param $symbol string to sort
     * @param $limit int depth
     * @return null
     */
    public function sortDepth(string $symbol, int $limit = 11)
    {
        $bids = $this->depthCache[$symbol]['bids'];
        $asks = $this->depthCache[$symbol]['asks'];
        krsort($bids);
        ksort($asks);
        return [
            "asks" => array_slice($asks, 0, $limit, true),
            "bids" => array_slice($bids, 0, $limit, true),
        ];
    }

    /**
     * depthCache Pulls /depth data and subscribes to @depth WebSocket endpoint
     * Maintains a local Depth Cache in sync via lastUpdateId.
     * See depth() and depthHandler()
     *
     * $api->depthCache(["BNBBTC"], function($api, $symbol, $depth) {
     * echo "{$symbol} depth cache update".PHP_EOL;
     * //print_r($depth); // Print all depth data
     * $limit = 11; // Show only the closest asks/bids
     * $sorted = $api->sortDepth($symbol, $limit);
     * $bid = $api->first($sorted['bids']);
     * $ask = $api->first($sorted['asks']);
     * echo $api->displayDepth($sorted);
     * echo "ask: {$ask}".PHP_EOL;
     * echo "bid: {$bid}".PHP_EOL;
     * });
     *
     * @param $symbol string optional array of symbols
     * @param $callback callable closure
     * @return null
     */
    public function depthCache($symbols, callable $callback)
    {
        if (!is_array($symbols)) {
            $symbols = [
                $symbols,
            ];
        }

        $loop = \React\EventLoop\Factory::create();
        $react = new \React\Socket\Connector($loop);
        $connector = new \Ratchet\Client\Connector($loop, $react);
        foreach ($symbols as $symbol) {
            if (!isset($this->info[$symbol])) {
                $this->info[$symbol] = [];
            }

            if (!isset($this->depthQueue[$symbol])) {
                $this->depthQueue[$symbol] = [];
            }

            if (!isset($this->depthCache[$symbol])) {
                $this->depthCache[$symbol] = [
                    "bids" => [],
                    "asks" => [],
                ];
            }

            $this->info[$symbol]['firstUpdate'] = 0;
            $endpoint = strtolower($symbol) . '@depthCache';
            $this->subscriptions[$endpoint] = true;

            $connector($this->getWsEndpoint() . strtolower($symbol) . '@depth')->then(function ($ws) use ($callback, $symbol, $loop, $endpoint) {
                $ws->on('message', function ($data) use ($ws, $callback, $loop, $endpoint) {
                    if ($this->subscriptions[$endpoint] === false) {
                        //$this->subscriptions[$endpoint] = null;
                        $loop->stop();
                        return; //return $ws->close();
                    }
                    $json = json_decode($data, true);
                    $symbol = $json['s'];
                    if (intval($this->info[$symbol]['firstUpdate']) === 0) {
                        $this->depthQueue[$symbol][] = $json;
                        return;
                    }
                    $this->depthHandler($json);
                    call_user_func($callback, $this, $symbol, $this->depthCache[$symbol]);
                });
                $ws->on('close', function ($code = null, $reason = null) use ($symbol, $loop) {
                    // WPCS: XSS OK.
                    echo "depthCache({$symbol}) WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
                    $loop->stop();
                });
            }, function ($e) use ($loop, $symbol) {
                // WPCS: XSS OK.
                echo "depthCache({$symbol})) Could not connect: {$e->getMessage()}" . PHP_EOL;
                $loop->stop();
            });
            $this->depth($symbol, 100);
            foreach ($this->depthQueue[$symbol] as $data) {
                //TODO:: WTF ??? where is json and what should be in it ??
                $this->depthHandler($json);
            }
            $this->depthQueue[$symbol] = [];
            call_user_func($callback, $this, $symbol, $this->depthCache[$symbol]);
        }
        $loop->run();
    }

    /**
     * trades Trades WebSocket Endpoint
     *
     * $api->trades(["BNBBTC"], function($api, $symbol, $trades) {
     * echo "{$symbol} trades update".PHP_EOL;
     * print_r($trades);
     * });
     *
     * @param $symbols
     * @param $callback callable closure
     * @return null
     */
    public function trades($symbols, callable $callback)
    {
        if (!is_array($symbols)) {
            $symbols = [
                $symbols,
            ];
        }

        $loop = \React\EventLoop\Factory::create();
        $react = new \React\Socket\Connector($loop);
        $connector = new \Ratchet\Client\Connector($loop, $react);
        foreach ($symbols as $symbol) {
            if (!isset($this->info[$symbol])) {
                $this->info[$symbol] = [];
            }

            // $this->info[$symbol]['tradesCallback'] = $callback;

            $endpoint = strtolower($symbol) . '@trades';
            $this->subscriptions[$endpoint] = true;

            $connector($this->getWsEndpoint() . strtolower($symbol) . '@aggTrade')->then(function ($ws) use ($callback, $symbol, $loop, $endpoint) {
                $ws->on('message', function ($data) use ($ws, $callback, $loop, $endpoint) {
                    if ($this->subscriptions[$endpoint] === false) {
                        //$this->subscriptions[$endpoint] = null;
                        $loop->stop();
                        return; //return $ws->close();
                    }
                    $json = json_decode($data, true);
                    $symbol = $json['s'];
                    $price = $json['p'];
                    $quantity = $json['q'];
                    $timestamp = $json['T'];
                    $maker = $json['m'] ? 'true' : 'false';
                    $trades = [
                        "price" => $price,
                        "quantity" => $quantity,
                        "timestamp" => $timestamp,
                        "maker" => $maker,
                    ];
                    // $this->info[$symbol]['tradesCallback']($this, $symbol, $trades);
                    call_user_func($callback, $this, $symbol, $trades);
                });
                $ws->on('close', function ($code = null, $reason = null) use ($symbol, $loop) {
                    // WPCS: XSS OK.
                    echo "trades({$symbol}) WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
                    $loop->stop();
                });
            }, function ($e) use ($loop, $symbol) {
                // WPCS: XSS OK.
                echo "trades({$symbol}) Could not connect: {$e->getMessage()}" . PHP_EOL;
                $loop->stop();
            });
        }
        $loop->run();
    }

    /**
     * ticker pulls 24h price change statistics via WebSocket
     *
     * $api->ticker(false, function($api, $symbol, $ticker) {
     * print_r($ticker);
     * });
     *
     * @param $symbol string optional symbol or false
     * @param $callback callable closure
     * @return null
     */
    public function ticker($symbol, callable $callback)
    {
        $endpoint = $symbol ? strtolower($symbol) . '@ticker' : '!ticker@arr';
        $this->subscriptions[$endpoint] = true;

        // @codeCoverageIgnoreStart
        // phpunit can't cover async function
        \Ratchet\Client\connect($this->getWsEndpoint() . $endpoint)->then(function ($ws) use ($callback, $symbol, $endpoint) {
            $ws->on('message', function ($data) use ($ws, $callback, $symbol, $endpoint) {
                if ($this->subscriptions[$endpoint] === false) {
                    //$this->subscriptions[$endpoint] = null;
                    $ws->close();
                    return; //return $ws->close();
                }
                $json = json_decode($data);
                if ($symbol) {
                    call_user_func($callback, $this, $symbol, $this->tickerStreamHandler($json));
                } else {
                    foreach ($json as $obj) {
                        $return = $this->tickerStreamHandler($obj);
                        $symbol = $return['symbol'];
                        call_user_func($callback, $this, $symbol, $return);
                    }
                }
            });
            $ws->on('close', function ($code = null, $reason = null) {
                // WPCS: XSS OK.
                echo "ticker: WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
            });
        }, function ($e) {
            // WPCS: XSS OK.
            echo "ticker: Could not connect: {$e->getMessage()}" . PHP_EOL;
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * chart Pulls /kline data and subscribes to @klines WebSocket endpoint
     *
     * $api->chart(["BNBBTC"], "15m", function($api, $symbol, $chart) {
     * echo "{$symbol} chart update\n";
     * print_r($chart);
     * });
     *
     * @param $symbols string required symbols
     * @param $interval string time inteval
     * @param $callback callable closure
     * @param $limit int default 500, maximum 1000
     * @return null
     * @throws \Exception
     */
    public function chart($symbols, string $interval = "30m", callable $callback = null, $limit = 500)
    {
        if (is_null($callback)) {
            throw new Exception("You must provide a valid callback");
        }
        if (!is_array($symbols)) {
            $symbols = [
                $symbols,
            ];
        }

        $loop = \React\EventLoop\Factory::create();
        $react = new \React\Socket\Connector($loop);
        $connector = new \Ratchet\Client\Connector($loop, $react);
        foreach ($symbols as $symbol) {
            if (!isset($this->charts[$symbol])) {
                $this->charts[$symbol] = [];
            }

            $this->charts[$symbol][$interval] = [];
            if (!isset($this->info[$symbol])) {
                $this->info[$symbol] = [];
            }

            if (!isset($this->info[$symbol][$interval])) {
                $this->info[$symbol][$interval] = [];
            }

            if (!isset($this->chartQueue[$symbol])) {
                $this->chartQueue[$symbol] = [];
            }

            $this->chartQueue[$symbol][$interval] = [];
            $this->info[$symbol][$interval]['firstOpen'] = 0;
            $endpoint = strtolower($symbol) . '@kline_' . $interval;
            $this->subscriptions[$endpoint] = true;
            $connector($this->getWsEndpoint() . $endpoint)->then(function ($ws) use ($callback, $symbol, $loop, $endpoint, $interval) {
                $ws->on('message', function ($data) use ($ws, $loop, $callback, $endpoint) {
                    if ($this->subscriptions[$endpoint] === false) {
                        //$this->subscriptions[$endpoint] = null;
                        $loop->stop();
                        return; //return $ws->close();
                    }
                    $json = json_decode($data);
                    $chart = $json->k;
                    $symbol = $json->s;
                    $interval = $chart->i;
                    $this->chartHandler($symbol, $interval, $json);
                    call_user_func($callback, $this, $symbol, $this->charts[$symbol][$interval]);
                });
                $ws->on('close', function ($code = null, $reason = null) use ($symbol, $loop, $interval) {
                    // WPCS: XSS OK.
                    echo "chart({$symbol},{$interval}) WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
                    $loop->stop();
                });
            }, function ($e) use ($loop, $symbol, $interval) {
                // WPCS: XSS OK.
                echo "chart({$symbol},{$interval})) Could not connect: {$e->getMessage()}" . PHP_EOL;
                $loop->stop();
            });
            $this->candlesticks($symbol, $interval, $limit);
            foreach ($this->chartQueue[$symbol][$interval] as $json) {
                $this->chartHandler($symbol, $interval, $json);
            }
            $this->chartQueue[$symbol][$interval] = [];
            call_user_func($callback, $this, $symbol, $this->charts[$symbol][$interval]);
        }
        $loop->run();
    }

    /**
     * kline Subscribes to @klines WebSocket endpoint for latest chart data only
     *
     * $api->kline(["BNBBTC"], "15m", function($api, $symbol, $chart) {
     * echo "{$symbol} chart update\n";
     * print_r($chart);
     * });
     *
     * @param $symbols string required symbols
     * @param $interval string time inteval
     * @param $callback callable closure
     * @return null
     * @throws \Exception
     */
    public function kline($symbols, string $interval = "30m", callable $callback = null)
    {
        if (is_null($callback)) {
            throw new Exception("You must provide a valid callback");
        }
        if (!is_array($symbols)) {
            $symbols = [
                $symbols,
            ];
        }

        $loop = \React\EventLoop\Factory::create();
        $react = new \React\Socket\Connector($loop);
        $connector = new \Ratchet\Client\Connector($loop, $react);
        foreach ($symbols as $symbol) {
            $endpoint = strtolower($symbol) . '@kline_' . $interval;
            $this->subscriptions[$endpoint] = true;
            $connector($this->getWsEndpoint() . $endpoint)->then(function ($ws) use ($callback, $symbol, $loop, $endpoint, $interval) {
                $ws->on('message', function ($data) use ($ws, $loop, $callback, $endpoint) {
                    if ($this->subscriptions[$endpoint] === false) {
                        $loop->stop();
                        return;
                    }
                    $json = json_decode($data);
                    $chart = $json->k;
                    $symbol = $json->s;
                    $interval = $chart->i;
                    call_user_func($callback, $this, $symbol, $chart);
                });
                $ws->on('close', function ($code = null, $reason = null) use ($symbol, $loop, $interval) {
                    // WPCS: XSS OK.
                    echo "kline({$symbol},{$interval}) WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
                    $loop->stop();
                });
            }, function ($e) use ($loop, $symbol, $interval) {
                // WPCS: XSS OK.
                echo "kline({$symbol},{$interval})) Could not connect: {$e->getMessage()}" . PHP_EOL;
                $loop->stop();
            });
        }
        $loop->run();
    }

    /**
     * terminate Terminates websocket endpoints. View endpoints first: print_r($api->subscriptions)
     *
     * $api->terminate('ethbtc_kline@5m');
     *
     * @return null
     */
    public function terminate($endpoint)
    {
        // check if $this->subscriptions[$endpoint] is true otherwise error
        $this->subscriptions[$endpoint] = false;
    }

    /**
     * keepAlive Keep-alive function for userDataStream
     *
     * $api->keepAlive();
     *
     * @return null
     */
    public function keepAlive()
    {
        $loop = \React\EventLoop\Factory::create();
        $loop->addPeriodicTimer(30, function () {
            $listenKey = $this->listenKey;
            $this->httpRequest("v1/userDataStream?listenKey={$listenKey}", "PUT", []);
        });
        $loop->run();
    }

    /**
     * userData Issues userDataStream token and keepalive, subscribes to userData WebSocket
     *
     * $balance_update = function($api, $balances) {
     * print_r($balances);
     * echo "Balance update".PHP_EOL;
     * };
     *
     * $order_update = function($api, $report) {
     * echo "Order update".PHP_EOL;
     * print_r($report);
     * $price = $report['price'];
     * $quantity = $report['quantity'];
     * $symbol = $report['symbol'];
     * $side = $report['side'];
     * $orderType = $report['orderType'];
     * $orderId = $report['orderId'];
     * $orderStatus = $report['orderStatus'];
     * $executionType = $report['orderStatus'];
     * if( $executionType == "NEW" ) {
     * if( $executionType == "REJECTED" ) {
     * echo "Order Failed! Reason: {$report['rejectReason']}".PHP_EOL;
     * }
     * echo "{$symbol} {$side} {$orderType} ORDER #{$orderId} ({$orderStatus})".PHP_EOL;
     * echo "..price: {$price}, quantity: {$quantity}".PHP_EOL;
     * return;
     * }
     *
     * //NEW, CANCELED, REPLACED, REJECTED, TRADE, EXPIRED
     * echo "{$symbol} {$side} {$executionType} {$orderType} ORDER #{$orderId}".PHP_EOL;
     * };
     * $api->userData($balance_update, $order_update);
     *
     * @param $balance_callback callable function
     * @param bool $execution_callback callable function
     * @return null
     * @throws \Exception
     */
    public function userData(&$balance_callback, &$execution_callback = false)
    {
        $response = $this->httpRequest("v1/userDataStream", "POST", []);
        $this->listenKey = $response['listenKey'];
        $this->info['balanceCallback'] = $balance_callback;
        $this->info['executionCallback'] = $execution_callback;

        $this->subscriptions['@userdata'] = true;

        $loop = \React\EventLoop\Factory::create();
        $loop->addPeriodicTimer(30*60, function () {
            $listenKey = $this->listenKey;
            $this->httpRequest("v1/userDataStream?listenKey={$listenKey}", "PUT", []);
        });
        $connector = new \Ratchet\Client\Connector($loop);

        // @codeCoverageIgnoreStart
        // phpunit can't cover async function
        $connector($this->getWsEndpoint() . $this->listenKey)->then(function ($ws) use ($loop) {
            $ws->on('message', function ($data) use ($ws) {
                if ($this->subscriptions['@userdata'] === false) {
                    //$this->subscriptions[$endpoint] = null;
                    $ws->close();
                    return; //return $ws->close();
                }
                $json = json_decode($data);
                $type = $json->e;
                if ($type === "outboundAccountPosition") {
                    $balances = $this->balanceHandler($json->B);
                    $this->info['balanceCallback']($this, $balances);
                } elseif ($type === "executionReport") {
                    $report = $this->executionHandler($json);
                    if ($this->info['executionCallback']) {
                        $this->info['executionCallback']($this, $report);
                    }
                }
            });
            $ws->on('close', function ($code = null, $reason = null) use ($loop) {
                // WPCS: XSS OK.
                echo "userData: WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
                $loop->stop();
            });
        }, function ($e) use ($loop) {
            // WPCS: XSS OK.
            echo "userData: Could not connect: {$e->getMessage()}" . PHP_EOL;
            $loop->stop();
        });

        $loop->run();
    }

    /**
     * miniTicker Get miniTicker for all symbols
     *
     * $api->miniTicker(function($api, $ticker) {
     * print_r($ticker);
     * });
     *
     * @param $callback callable function closer that takes 2 arguments, $pai and $ticker data
     * @return null
     */
    public function miniTicker(callable $callback)
    {
        $endpoint = '@miniticker';
        $this->subscriptions[$endpoint] = true;

        // @codeCoverageIgnoreStart
        // phpunit can't cover async function
        \Ratchet\Client\connect($this->getWsEndpoint() . '!miniTicker@arr')->then(function ($ws) use ($callback, $endpoint) {
            $ws->on('message', function ($data) use ($ws, $callback, $endpoint) {
                if ($this->subscriptions[$endpoint] === false) {
                    //$this->subscriptions[$endpoint] = null;
                    $ws->close();
                    return; //return $ws->close();
                }
                $json = json_decode($data, true);
                $markets = [];
                foreach ($json as $obj) {
                    $markets[] = [
                        "symbol" => $obj['s'],
                        "close" => $obj['c'],
                        "open" => $obj['o'],
                        "high" => $obj['h'],
                        "low" => $obj['l'],
                        "volume" => $obj['v'],
                        "quoteVolume" => $obj['q'],
                        "eventTime" => $obj['E'],
                    ];
                }
                call_user_func($callback, $this, $markets);
            });
            $ws->on('close', function ($code = null, $reason = null) {
                // WPCS: XSS OK.
                echo "miniticker: WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
            });
        }, function ($e) {
            // WPCS: XSS OK.
            echo "miniticker: Could not connect: {$e->getMessage()}" . PHP_EOL;
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * bookTicker Get bookTicker for all symbols
     *
     * $api->bookTicker(function($api, $ticker) {
     * print_r($ticker);
     * });
     *
     * @param $callback callable function closer that takes 2 arguments, $api and $ticker data
     * @return null
     */
    public function bookTicker(callable $callback)
    {
        $endpoint = '!bookticker';
        $this->subscriptions[$endpoint] = true;

        // @codeCoverageIgnoreStart
        // phpunit can't cover async function
        \Ratchet\Client\connect($this->getWsEndpoint() . '!bookTicker')->then(function ($ws) use ($callback, $endpoint) {
            $ws->on('message', function ($data) use ($ws, $callback, $endpoint) {
                if ($this->subscriptions[$endpoint] === false) {
                    //$this->subscriptions[$endpoint] = null;
                    $ws->close();
                    return; //return $ws->close();
                }
                $json = json_decode($data, true);

                $markets = [
                    "updateId"  => $json['u'],
                    "symbol"    => $json['s'],
                    "bid_price" => $json['b'],
                    "bid_qty"   => $json['B'],
                    "ask_price" => $json['a'],
                    "ask_qty"   => $json['A'],
                ];
                call_user_func($callback, $this, $markets);
            });
            $ws->on('close', function ($code = null, $reason = null) {
                // WPCS: XSS OK.
                echo "miniticker: WebSocket Connection closed! ({$code} - {$reason})" . PHP_EOL;
            });
        }, function ($e) {
            // WPCS: XSS OK.
            echo "miniticker: Could not connect: {$e->getMessage()}" . PHP_EOL;
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Due to ongoing issues with out of date wamp CA bundles
     * This function downloads ca bundle for curl website
     * and uses it as part of the curl options
     */
    protected function downloadCurlCaBundle()
    {
        $output_filename = getcwd() . "/ca.pem";

        if (is_writable(getcwd()) === false) {
            die(getcwd() . ' folder is not writeable, please check your permissions to download CA Certificates, or use $api->caOverride = true;');
        }

        $host = "https://curl.se/ca/cacert.pem";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $host);
        curl_setopt($curl, CURLOPT_VERBOSE, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        // proxy settings
        if (is_array($this->proxyConf)) {
            curl_setopt($curl, CURLOPT_PROXY, $this->getProxyUriString());
            if (isset($this->proxyConf['user']) && isset($this->proxyConf['pass'])) {
                curl_setopt($curl, CURLOPT_PROXYUSERPWD, $this->proxyConf['user'] . ':' . $this->proxyConf['pass']);
            }
        }

        $result = curl_exec($curl);
        curl_close($curl);

        if ($result === false) {
            echo "Unable to to download the CA bundle $host" . PHP_EOL;
            return;
        }

        $fp = fopen($output_filename, 'w');

        if ($fp === false) {
            echo "Unable to write $output_filename, please check permissions on folder" . PHP_EOL;
            return;
        }

        fwrite($fp, $result);
        fclose($fp);
    }

    protected function floorDecimal($n, $decimals=2)
    {
        return floor($n * pow(10, $decimals)) / pow(10, $decimals);
    }


    protected function setXMbxUsedWeight(int $usedWeight) : void
    {
        $this->xMbxUsedWeight = $usedWeight;
    }

    protected function setXMbxUsedWeight1m(int $usedWeight1m) : void
    {
        $this->xMbxUsedWeight1m = $usedWeight1m;
    }

    public function getXMbxUsedWeight() : int
    {
        return $this->xMbxUsedWeight;
    }

    public function getXMbxUsedWeight1m() : int
    {
        return $this->xMbxUsedWeight1m;
    }

    private function getWsEndpoint() : string
    {
        return $this->useTestnet ? $this->streamTestnet : $this->stream;
    }

    public function isOnTestnet() : bool
    {
        return $this->useTestnet;
    }

    /**
     * systemStatus - Status indicator for api sapi
     *
     * @link https://binance-docs.github.io/apidocs/spot/en/#test-connectivity
     * @link https://binance-docs.github.io/apidocs/spot/en/#system-status-system
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api
     *
     * @property int $weight 2
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function systemStatus()
    {
        $arr = array();
        $api_status = $this->httpRequest("v3/ping", 'GET');
        if ( empty($api_status) ) {
            $arr['api']['status']  = 'ping ok';
        } else {
            $arr['api']['status']  = $api_status;
        }

        $fapi_status = $this->httpRequest("v1/ping", 'GET', [ 'fapi' => true ]);
        if ( empty($fapi_status) ) {
            $arr['fapi']['status'] = 'ping ok';
        } else {
            $arr['fapi']['status'] = $fapi_status;
        }

        $arr['sapi'] = $this->httpRequest("v1/system/status", 'GET', [ 'sapi' => true ]);
        return $arr;
    }

    /**
     * accountSnapshot - Daily Account Snapshot at 00:00:00 UTC
     *
     * @link https://binance-docs.github.io/apidocs/spot/en/#daily-account-snapshot-user_data
     *
     * @property int $weight 1
     *
     * @param string $type      (mandatory) Should be SPOT, MARGIN or FUTURES
     * @param int    $nbrDays   (optional)  Number of days. Default 5, min 5, max 30
     * @param long   $startTime (optional)  Start time, e.g. 1617580799000
     * @param long   $endTime   (optional)  End time, e.g. 1617667199000
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function accountSnapshot($type, $nbrDays = 5, $startTime = 0, $endTime = 0)
    {
        if ($nbrDays < 5 || $nbrDays > 30)
            $nbrDays = 5;

        $params = [
            'sapi' => true,
            'type' => $type,
            ];

        if ($startTime > 0)
            $params['startTime'] = $startTime;
        if ($endTime > 0)
            $params['endTime'] = $startTime;
        if ($nbrDays != 5)
            $params['limit'] = $nbrDays;

        return $this->httpRequest("v1/accountSnapshot", 'GET', $params, true);
    }

    /**
     * accountStatus - Fetch account status detail.
     *
     * @link https://binance-docs.github.io/apidocs/spot/en/#account-status-user_data
     *
     * @property int $weight 1
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function accountStatus()
    {
        $arr = array();
        $arr['sapi'] = $this->httpRequest("v1/account/status", 'GET', [ 'sapi' => true ], true);
        return $arr;
    }

    /**
     * apiRestriction - Fetch a set of API restrictions
     *
     * @link https://binance-docs.github.io/apidocs/spot/en/#get-api-key-permission-user_data
     *
     * @property int $weight 1
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function apiRestrictions()
    {
        return $this->httpRequest("v1/account/apiRestrictions", 'GET', ['sapi' => true], true);
    }

    /**
     * apiTradingStatus - Fetch account API trading status detail.
     *
     * @link https://binance-docs.github.io/apidocs/spot/en/#account-api-trading-status-user_data
     *
     * @property int $weight 1
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function apiTradingStatus()
    {
        $arr = array();
        $arr['sapi'] = $this->httpRequest("v1/account/apiTradingStatus", 'GET', [ 'sapi' => true ], true);
        return $arr;
    }

    /**
     * ocoOrder - Create a new OCO order
     *
     * @link https://binance-docs.github.io/apidocs/spot/en/#new-oco-trade
     *
     * @property int $weight 1
     *
     * @param string $side       (mandatory)   Should be SELL or BUY
     * @param string $symbol     (mandatory)   The symbol, e.g. BTCBUSD
     * @param float  $quantity   (mandatory)   Quantity to buy/sell
     * @param int    $price      (mandatory)   Price
     * @param int    $stopprice  (mandatory)   Stop Price
     * @param int    $stoplimitprice        (optional)   Stop Limit Price
     * @param int    $stoplimittimeinforce  (optional)   GTC, FOK or IOC
     * @param array  $flags                 (optional)   Extra flags/parameters
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function ocoOrder(string $side, string $symbol, $quantity, $price, $stopprice, $stoplimitprice = null, $stoplimittimeinforce = 'GTC', array $flags = [])
    {
        $opt = [
            "symbol" => $symbol,
            "side" => $side,
            "recvWindow" => 60000,
        ];

        if (is_numeric($quantity) === false) {
            $error = "Parameter quantity expected numeric for ' $side . ' ' . $symbol .', got " . gettype($quantity);
            trigger_error($error, E_USER_ERROR);
        } else {
            $opt['quantity'] = $quantity;
        }

        if (is_numeric($price) === false) {
            $error = "Parameter price expected numeric for ' $side . ' ' . $symbol .', got " . gettype($price);
            trigger_error($error, E_USER_ERROR);
        } else {
            $opt['price'] = $price;
        }

        if (is_numeric($stopprice) === false) {
            $error = "Parameter stopprice expected numeric for ' $side . ' ' . $symbol .', got " . gettype($stopprice);
            trigger_error($error, E_USER_ERROR);
        } else {
            $opt['stopPrice'] = $stopprice;
        }

        if (is_null($stoplimitprice) === false && empty($stoplimitprice) === false) {
            $opt['stopLimitPrice'] = $stoplimitprice;
            if ( ($stoplimittimeinforce == 'FOK') || ($stoplimittimeinforce == 'IOC') ) {
                $opt['stopLimitTimeInForce'] = $stoplimittimeinforce;
            } else {
                $opt['stopLimitTimeInForce'] = 'GTC'; // `Good 'till cancel`. Needed if flag `stopLimitPrice` used.
            }
        }

        // Check other flags
        foreach (array('icebergQty','stopIcebergQty','listClientOrderId','limitClientOrderId','stopClientOrderId','newOrderRespType') as $flag) {
            if ( isset($flags[$flag]) && !empty($flags[$flag]) )
                $opt[$flag] = $flags[$flag];
        }

        return $this->httpRequest("v3/order/oco", "POST", $opt, true);
    }

    /**
    * avgPrice - get the average price of a symbol based on the last 5 minutes
    *
    * $avgPrice = $api->avgPrice( "ETHBTC" );
    *
    * @property int $weight 1
    *
    * @param string $symbol (mandatory) a symbol, e.g. ETHBTC
    *
    * @return string with symbol price
    * @throws \Exception
    */
    public function avgPrice(string $symbol)
    {
        $ticker = $this->httpRequest("v3/avgPrice", "GET", ["symbol" => $symbol]);
        return $ticker['price'];
    }


    /*********************************************
     *
     * Binance Liquid Swap (bswap) functions
     *
     * https://binance-docs.github.io/apidocs/spot/en/#bswap-endpoints
     *
     *********************************************/

    /**
    * bswapQuote - Request a quote for swap of quote asset (selling) or base asset (buying), essentially price/exchange rates.
    *
    * @property int $weight 2
    *
    * @param string $baseAsset  (mandatory) e.g. ETH
    * @param string $quoteAsset (mandatory) e.g. BTC
    * @param string $quoteQty   (mandatory)
    *
    * @return array containing the response
    * @throws \Exception
    */
    public function bswapQuote($baseAsset, $quoteAsset, $quoteQty) {
        $opt = [
            'sapi'       => true,
            'quoteAsset' => $quoteAsset,
            'baseAsset'  => $baseAsset,
            'quoteQty'   => $quoteQty,
        ];

        return $this->httpRequest("v1/bswap/quote", 'GET', $opt, true);
    }

    /*********************************************
    *
    * Binance futures (fapi) functions
    *
    * https://developers.binance.com/docs/derivatives/usds-margined-futures/general-info
    *
    *********************************************/

    /**
     * futuresTime Gets the server time
     *
     * $time = $api->futuresTime();
     *
     * @return array with error message or array with server time key
     * @throws \Exception
     */
    public function futuresTime()
    {
        return $this->httpRequest("v1/time", "GET", [ 'fapi' => true ]);
    }

    /**
     * futuresExchangeInfo -  Gets the complete exchange info, including limits, currency options etc. for futures
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Exchange-Information
     *
     * $info = $api->futuresExchangeInfo();
     *
     * @property int $weight 1
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresExchangeInfo()
    {
        if (!$this->futuresExchangeInfo) {
            $arr = array();
            $arr['symbols'] = array();

            $arr = $this->httpRequest("v1/exchangeInfo", "GET", [ 'fapi' => true ]);

            $this->futuresExchangeInfo = $arr;
            $this->futuresExchangeInfo['symbols'] = null;

            foreach ($arr['symbols'] as $key => $value) {
                $this->futuresExchangeInfo['symbols'][$value['symbol']] = $value;
            }
        }

        return $this->futuresExchangeInfo;
    }

    /**
     * futuresDepth get Market depth for futures
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Order-Book
     *
     * $depth = $api->futuresDepth("ETHBTC");
     *
     * @property int $weight 10
     * for limit 5, 10, 20, 50 - weight 2
     * for limit 100 - weight 5
     * for limit 500 (default) - weight 10
     * for limit 1000 - weight 20
     *
     * @param string $symbol (mandatory) the symbol to get the depth information for, e.g. ETHUSDT
     * @param int    $limit (optional) $limit set limition for number of market depth data, default 500, max 1000 (possible values are 5, 10, 20, 50, 100, 500, 1000)
     * @return array with error message or array of market depth
     * @throws \Exception
     */
    public function futuresDepth(string $symbol, int $limit = null)
    {
        if (isset($symbol) === false || is_string($symbol) === false) {
            // WPCS: XSS OK.
            echo "asset: expected bool false, " . gettype($symbol) . " given" . PHP_EOL;
        }

        $params = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        if ($limit) {
            $params['limit'] = $limit;
        }
        $json = $this->httpRequest("v1/depth", "GET", $params);
        if (isset($this->info[$symbol]) === false) {
            $this->info[$symbol] = [];
            $this->info[$symbol]['futures'] = [];
        }
        $this->info[$symbol]['futures']['firstUpdate'] = $json['lastUpdateId'];
        return $this->depthData($symbol, $json, 'futures');
    }

    /**
     * futuresRecentTrades - Get recent trades for a specific currency
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Recent-Trades-List
     *
     * $trades = $api->futuresRecentTrades("ETHBTC");
     *
     * @property int $weight 5
     *
     * @param string $symbol (mandatory) the symbol to query, e.g. ETHUSDT
     * @param int    $limit  (optional) limit the amount of trades, default 500, max 1000
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresRecentTrades(string $symbol, int $limit = null)
    {
        $parameters = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        if ($limit) {
            $parameters['limit'] = $limit;
        }
        return $this->httpRequest("v1/trades", "GET", $parameters);
    }

    /**
     * futuresHistoricalTrades - Get historical trades for a specific currency
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Old-Trades-Lookup
     *
     * $trades = $api->futuresHistoricalTrades("ETHBTC");
     *
     * @property int $weight 20
     *
     * @param string $symbol  (mandatory) the symbol to query, e.g. ETHUSDT
     * @param int    $limit   (optional)  limit the amount of trades, default 100, max 500
     * @param int    $tradeId (optional)  return the orders from this orderId onwards, negative to get recent ones
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresHistoricalTrades(string $symbol, int $limit = null, int $tradeId = null)
    {
        $parameters = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        if ($limit) {
            $parameters['limit'] = $limit;
        }
        if ($tradeId) {
            $parameters['fromId'] = $tradeId;
        }
        return $this->httpRequest("v1/historicalTrades", "GET", $parameters, true);
    }

    /**
     * futuresAggTrades get Market History / Aggregate Trades
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Compressed-Aggregate-Trades-List
     *
     * $trades = $api->futuresAggTrades("BNBBTC");
     *
     * @property int $weight 20
     *
     * @param string $symbol (mandatory) the symbol to get the trade information for, e.g. ETHUSDT
     * @param int    $fromId (optional) ID to get aggregate trades from INCLUSIVE
     * @param int    $startTime (optional) timestamp in ms to get aggregate trades from INCLUSIVE
     * @param int    $endTime (optional) timestamp in ms to get aggregate trades until INCLUSIVE
     * @param int    $limit (optional) the amount of trades, default 500, max 1000
     *
     * @return array with error message or array of market history
     * @throws \Exception
     */
    public function futuresAggTrades(string $symbol, int $fromId = null, int $startTime = null, int $endTime = null, int $limit = null)
    {
        $parameters = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        if ($fromId) {
            $parameters['fromId'] = $fromId;
        }
        if ($startTime) {
            $parameters['startTime'] = $startTime;
        }
        if ($endTime) {
            $parameters['endTime'] = $endTime;
        }
        if ($limit) {
            $parameters['limit'] = $limit;
        }
        return $this->tradesData($this->httpRequest("v1/aggTrades", "GET", $parameters));
    }

    /**
     * futuresCandlesticks get the candles for the given intervals
     * 1m,3m,5m,15m,30m,1h,2h,4h,6h,8h,12h,1d,3d,1w,1M
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Kline-Candlestick-Data
     *
     * $candles = $api->futuresCandlesticks("BNBBTC", "5m");
     *
     * @property int $weight 5
     * for limit < 100 - weight 1
     * for limit < 500 - weight 2
     * for limit <= 1000 - weight 5
     * for limit > 1000 - weight 10
     *
     * @param string $symbol (mandatory) the symbol to query, e.g. ETHUSDT
     * @param string $interval (optional) string to request - 1m, 3m, 5m, 15m, 30m, 1h, 2h, 4h, 6h, 8h, 12h, 1d, 3d, 1w, 1M (default 5m)
     * @param int    $limit (optional) int limit the amount of candles (default 500, max 1000)
     * @param int    $startTime (optional) string request candle information starting from here
     * @param int    $endTime (optional) string request candle information ending here
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresCandlesticks(string $symbol, string $interval = '5m', int $limit = null, $startTime = null, $endTime = null)
    {
        return $this->futuresCandlesticksHelper($symbol, $interval, $limit, $startTime, $endTime, 'klines');
    }

    /**
     * futuresContinuousCandlesticks get the candles for the given intervals
     * 1m,3m,5m,15m,30m,1h,2h,4h,6h,8h,12h,1d,3d,1w,1M
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Continuous-Contract-Kline-Candlestick-Data
     *
     * $candles = $api->futuresContinuousCandlesticks("BNBBTC", "5m");
     *
     * @property int $weight 5
     * for limit < 100 - weight 1
     * for limit < 500 - weight 2
     * for limit <= 1000 - weight 5
     * for limit > 1000 - weight 10
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. ETHUSDT
     * @param string $interval (optional) string to request - 1m, 3m, 5m, 15m, 30m, 1h, 2h, 4h, 6h, 8h, 12h, 1d, 3d, 1w, 1M (default 5m)
     * @param int    $limit (optional) int limit the amount of candles (default 500, max 1000)
     * @param int    $startTime (optional) string request candle information starting from here
     * @param int    $endTime (optional) string request candle information ending here
     * @param string $contractType (optional) string to request - PERPETUAL, CURRENT_QUARTER, NEXT_QUARTER (default PERPETUAL)
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresContinuousCandlesticks(string $symbol, string $interval = '5m', int $limit = null, $startTime = null, $endTime = null, $contractType = 'PERPETUAL')
    {
        return $this->futuresCandlesticksHelper($symbol, $interval, $limit, $startTime, $endTime, 'continuousKlines', $contractType);
    }

    /**
     * futuresIndexPriceCandlesticks get the candles for the given intervals
     * 1m,3m,5m,15m,30m,1h,2h,4h,6h,8h,12h,1d,3d,1w,1M
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Index-Price-Kline-Candlestick-Data
     *
     * $candles = $api->futuresIndexPriceCandlesticks("BNBBTC", "5m");
     *
     * @property int $weight 5
     * for limit < 100 - weight 1
     * for limit < 500 - weight 2
     * for limit <= 1000 - weight 5
     * for limit > 1000 - weight 10
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. ETHUSDT
     * @param string $interval (optional) string to request - 1m, 3m, 5m, 15m, 30m, 1h, 2h, 4h, 6h, 8h, 12h, 1d, 3d, 1w, 1M (default 5m)
     * @param int    $limit (optional) int limit the amount of candles (default 500, max 1000)
     * @param int    $startTime (optional) string request candle information starting from here
     * @param int    $endTime (optional) string request candle information ending here
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresIndexPriceCandlesticks(string $symbol, string $interval = '5m', int $limit = null, $startTime = null, $endTime = null)
    {
        return $this->futuresCandlesticksHelper($symbol, $interval, $limit, $startTime, $endTime, 'indexPriceKlines');
    }

    /**
     * futuresMarkPriceCandlesticks get the candles for the given intervals
     * 1m,3m,5m,15m,30m,1h,2h,4h,6h,8h,12h,1d,3d,1w,1M
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Mark-Price-Kline-Candlestick-Data
     *
     * $candles = $api->futuresMarkPriceCandlesticks("BNBBTC", "5m");
     *
     * @property int $weight 5
     * for limit < 100 - weight 1
     * for limit < 500 - weight 2
     * for limit <= 1000 - weight 5
     * for limit > 1000 - weight 10
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. ETHUSDT
     * @param string $interval (optional) string to request - 1m, 3m, 5m, 15m, 30m, 1h, 2h, 4h, 6h, 8h, 12h, 1d, 3d, 1w, 1M (default 5m)
     * @param int    $limit (optional) int limit the amount of candles (default 500, max 1000)
     * @param int    $startTime (optional) string request candle information starting from here
     * @param int    $endTime (optional) string request candle information ending here
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresMarkPriceCandlesticks(string $symbol, string $interval = '5m', int $limit = null, $startTime = null, $endTime = null)
    {
        return $this->futuresCandlesticksHelper($symbol, $interval, $limit, $startTime, $endTime, 'markPriceKlines');
    }

    /**
     * futuresPremiumIndexKlines get the candles for the given intervals
     * 1m,3m,5m,15m,30m,1h,2h,4h,6h,8h,12h,1d,3d,1w,1M
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Premium-Index-Kline-Data
     *
     * $candles = $api->futuresPremiumIndexKlines("ETHBTC", "5m");
     *
     * @property int $weight 5
     * for limit < 100 - weight 1
     * for limit < 500 - weight 2
     * for limit <= 1000 - weight 5
     * for limit > 1000 - weight 10
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. ETHUSDT
     * @param string $interval (optional) string to request - 1m, 3m, 5m, 15m, 30m, 1h, 2h, 4h, 6h, 8h, 12h, 1d, 3d, 1w, 1M (default 5m)
     * @param int    $limit (optional) int limit the amount of candles (default 500, max 1000)
     * @param int    $startTime (optional) string request candle information starting from here
     * @param int    $endTime (optional) string request candle information ending here
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresPremiumIndexKlines(string $symbol, string $interval = '5m', int $limit = null, $startTime = null, $endTime = null)
    {
        return $this->futuresCandlesticksHelper($symbol, $interval, $limit, $startTime, $endTime, 'premiumIndexKlines');
    }

    /**
     * futuresCandlesticksHelper
     * helper for routing the futuresCandlesticks, futuresContinuousCandlesticks, futuresIndexPriceCandlesticks, futuresMarkPriceCandlesticks and futuresPremiumIndexKlines
     */
    private function futuresCandlesticksHelper($symbol, $interval, $limit, $startTime, $endTime, $klineType, $contractType = null)
    {
        if (!isset($this->charts['futures'])) {
            $this->charts['futures'] = [];
        }
        if (!isset($this->charts['futures'][$symbol])) {
            $this->charts['futures'][$symbol] = [];
        }
        if (!isset($this->charts['futures'][$symbol][$type])) {
            $this->charts['futures'][$symbol][$type] = [];
        }
        if (!isset($this->charts['futures'][$symbol][$type][$interval])) {
            $this->charts['futures'][$symbol][$type][$interval] = [];
        }
        $params = [
            'interval' => $interval,
            'fapi' => true,
        ];
        if ($klineType === 'continuousKlines' || $klineType === 'indexPriceKlines') {
            $params['pair'] = $symbol;
        } else {
            $params['symbol'] = $symbol;
        }
        if ($limit) {
            $params['limit'] = $limit;
        }
        if ($startTime) {
            $params['startTime'] = $startTime;
        }
        if ($endTime) {
            $params['endTime'] = $endTime;
        }
        if ($contractType) {
            $params['contractType'] = $contractType;
        }

        $response = $this->httpRequest("v1/{$klineType}", 'GET', $params);

        if (is_array($response) === false) {
            return [];
        }
        if (count($response) === 0) {
            echo "warning: fapi/v1/{$klineType} returned empty array, usually a blip in the connection or server" . PHP_EOL;
            return [];
        }

        $candlesticks = $this->chartData($symbol, $interval, $response, 'futures', $klineType);
        $this->charts['futures'][$symbol][$klineType][$interval] = $candlesticks;
        return $candlesticks;
    }

    /**
     * futuresMarkPrice get the mark price for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Mark-Price
     *
     * $markPrice = $api->futuresMarkPrice();
     * $markPrice = $api->futuresMarkPrice("ETHBTC");
     *
     * @property int $weight 1
     *
     * @param string $symbol (optional) market symbol to get the response for, e.g. ETHUSDT
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresMarkPrice(string $symbol = null)
    {
        $parameters = [
            'fapi' => true,
        ];
        if ($symbol) {
            $parameters['symbol'] = $symbol;
        }
        return $this->httpRequest("v1/premiumIndex", "GET", $parameters);
    }

    /**
     * futuresFundingRateHistory get the funding rate history for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Get-Funding-Rate-History
     *
     * $fundingRate = $api->futuresFundingRateHistory();
     * $fundingRate = $api->futuresFundingRateHistory("ETHBTC");
     *
     * @param string $symbol (optional) market symbol to get the response for, e.g. ETHUSDT
     * @param int    $limit  (optional) int limit the amount of funding rate history (default 100, max 1000)
     * @param int    $startTime (optional) string request funding rate history starting from here
     * @param int    $endTime (optional) string request funding rate history ending here
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresFundingRateHistory(string $symbol = null, int $limit = null, $startTime = null, $endTime = null)
    {
        $parameters = [
            'fapi' => true,
        ];
        if ($symbol) {
            $parameters['symbol'] = $symbol;
        }
        if ($limit) {
            $parameters['limit'] = $limit;
        }
        if ($startTime) {
            $parameters['startTime'] = $startTime;
        }
        if ($endTime) {
            $parameters['endTime'] = $endTime;
        }
        return $this->httpRequest("v1/fundingRate", "GET", $parameters);
    }

    /**
     * futuresFundingInfo get the funding rate history for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Get-Funding-Rate-Info
     *
     * $fundingInfo = $api->futuresFundingInfo();
     *
     * @property int $weight 1
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresFundingInfo()
    {
        $parameters = [
            'fapi' => true,
        ];
        return $this->httpRequest("v1/fundingInfo", "GET", $parameters);
    }

    /**
     * futuresPrevDay get 24 hour price change statistics for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/24hr-Ticker-Price-Change-Statistics
     *
     * $ticker = $api->futuresPrevDay();
     * $ticker = $api->futuresPrevDay("ETHBTC");
     *
     * @property int $weight 1
     * if the symbol parameter is omitted weight is 40
     *
     * @param string $symbol (optional) market symbol to get the response for, e.g. ETHUSDT
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresPrevDay(string $symbol = null)
    {
        $parameters = [
            'fapi' => true,
        ];
        if ($symbol) {
            $parameters['symbol'] = $symbol;
        }
        return $this->httpRequest("v1/ticker/24hr", "GET", $parameters);
    }

    /**
     * futuresPrice get the latest price for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Symbol-Price-Ticker
     *
     * $price = $api->futuresPrice('ETHUSDT');
     *
     * @property int $weight 1
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. ETHUSDT
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresPrice(string $symbol)
    {
        $parameters = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        $ticker = $this->httpRequest("v1/ticker/price", "GET", $parameters);
        return $ticker['price'];
    }

    /**
     * futuresPrices get the latest price for all symbols
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Symbol-Price-Ticker
     *
     * $price = $api->futuresPrices();
     *
     * @property int $weight 2
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresPrices()
    {
        $parameters = [
            'fapi' => true,
        ];
        return $this->priceData($this->httpRequest("v1/ticker/price", "GET", $parameters));
    }

    /**
     * futuresPriceV2 get the latest price for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Symbol-Price-Ticker-v2
     *
     * $price = $api->futuresPriceV2('ETHBTC');
     *
     * @property int $weight 1
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. ETHUSDT
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresPriceV2(string $symbol)
    {
        $parameters = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        $ticker = $this->httpRequest("v2/ticker/price", "GET", $parameters);
        return $ticker['price'];
    }

    /**
     * futuresPricesV2 get the latest price for all symbols
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Symbol-Price-Ticker-v2
     *
     * $price = $api->futuresPricesV2();
     *
     * @property int $weight 2
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresPricesV2()
    {
        $parameters = [
            'fapi' => true,
        ];
        return $this->priceData($this->httpRequest("v2/ticker/price", "GET", $parameters));
    }

    /**
     * futuresSymbolOrderBookTicker get the best price/qty on the order book for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Symbol-Order-Book-Ticker
     *
     * $ticker = $api->futuresSymbolOrderBookTicker();
     * $ticker = $api->futuresSymbolOrderBookTicker("ETHBTC");
     *
     * @property int $weight 2
     * 5 when the symbol parameter is omitted
     *
     * @param string $symbol (optional) market symbol to get the response for, e.g. ETHUSDT
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresSymbolOrderBookTicker(string $symbol = null): array
    {
        $parameters = [
            'fapi' => true,
        ];
        if ($symbol) {
            $parameters['symbol'] = $symbol;
        }
        return $this->httpRequest("v1/ticker/bookTicker", "GET", $parameters);
    }

    /**
     * futuresDeliveryPrice get the latest delivery price for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Delivery-Price
     *
     * $price = $api->futuresDeliveryPrice("ETHBTC");
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. ETHUSDT
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresDeliveryPrice(string $symbol): array
    {
        $parameters = [
            'pair' => $symbol,
            'fapiData' => true,
        ];

        return $this->httpRequest("delivery-price", "GET", $parameters);
    }

    /**
     * futuresOpenInterest get the open interest for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Open-Interest
     *
     * $openInterest = $api->futuresOpenInterest("ETHBTC");
     *
     * @property int $weight 1
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. ETHUSDT
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresOpenInterest(string $symbol): array
    {
        $parameters = [
            'symbol'=> $symbol,
            'fapi' => true,
        ];
        return $this->httpRequest("v1/openInterest", 'GET', $parameters);
    }

    /**
     * symbolPeriodLimitStartEndRequest
     * helper for routing GET methods that require symbol, period, limit, startTime and endTime
     */
    private function symbolPeriodLimitStartEndContractTypeRequest($symbol, $period, $limit, $startTime, $endTime, $url, $base = 'fapi', $contractType = null)
    {
        $parameters = [
            'symbol' => $symbol,
            'period' => $period,
        ];
        $parameters[$base] = true;
        if ($limit) {
            $parameters['limit'] = $limit;
        }
        if ($startTime) {
            $parameters['startTime'] = $startTime;
        }
        if ($endTime) {
            $parameters['endTime'] = $endTime;
        }
        return $this->httpRequest($url, 'GET', $parameters);
    }

    /**
     * futuresOpenInterestHistory get the open interest history for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Open-Interest-Statistics
     *
     * $openInterest = $api->futuresOpenInterestHistory("ETHBTC", 5m);
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. ETHUSDT
     * @param string $period (optional) string of period to query - 5m, 15m, 30m, 1h, 2h, 4h, 6h, 12h, 1d (default 5m)
     * @param int    $limit (optional) int limit the amount of open interest history (default 100, max 1000)
     * @param int    $startTime (optional) string request open interest history starting from here
     * @param int    $endTime (optional) string request open interest history ending here
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresOpenInterestHistory(string $symbol, string $period = '5m', int $limit = null, $startTime = null, $endTime = null)
    {
        return $this->symbolPeriodLimitStartEndRequest($symbol, $period, $limit, $startTime, $endTime, 'openInterestHist', 'fapiData');
    }

    /**
     * futuresTopLongShortPositionRatio get the proportion of net long and net short positions to total open positions of the top 20% users with the highest margin balance
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Top-Trader-Long-Short-Ratio
     *
     * $ratio = $api->futuresTopLongShortPositionRatio("ETHBTC", 5m);
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. ETHUSDT
     * @param string $period (optional) string of period to query - 5m, 15m, 30m, 1h, 2h, 4h, 6h, 12h, 1d (default 5m)
     * @param int    $limit (optional) int limit the amount of open interest history (default 30, max 500)
     * @param int    $startTime (optional) string request open interest history starting from here
     * @param int    $endTime (optional) string request open interest history ending here
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresTopLongShortPositionRatio(string $symbol, string $period = '5m', int $limit = null, $startTime = null, $endTime = null)
    {
        return $this->symbolPeriodLimitStartEndRequest($symbol, $period, $limit, $startTime, $endTime, 'topLongShortPositionRatio', 'fapiData');
    }

    /**
     * futuresTopLongShortAccountRatio get the proportion of net long and net short accounts to total accounts of the top 20% users with the highest margin balance
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Top-Long-Short-Account-Ratio
     *
     * $ratio = $api->futuresTopLongShortAccountRatio("ETHBTC", 5m);
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. ETHUSDT
     * @param string $period (optional) string of period to query - 5m, 15m, 30m, 1h, 2h, 4h, 6h, 12h, 1d (default 5m)
     * @param int    $limit (optional) int limit the amount of open interest history (default 30, max 500)
     * @param int    $startTime (optional) string request open interest history starting from here
     * @param int    $endTime (optional) string request open interest history ending here
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresTopLongShortAccountRatio(string $symbol, string $period = '5m', int $limit = null, $startTime = null, $endTime = null)
    {
        return $this->symbolPeriodLimitStartEndRequest($symbol, $period, $limit, $startTime, $endTime, 'topLongShortAccountRatio', 'fapiData');
    }

    /**
     * futuresGlobalLongShortAccountRatio get the Long/Short Ratio for symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Long-Short-Ratio
     *
     * $ratio = $api->futuresGlobalLongShortAccountRatio("ETHBTC", 5m);
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. ETHUSDT
     * @param string $period (optional) string of period to query - 5m, 15m, 30m, 1h, 2h, 4h, 6h, 12h, 1d (default 5m)
     * @param int    $limit (optional) int limit the amount of open interest history (default 30, max 500)
     * @param int    $startTime (optional) string request open interest history starting from here
     * @param int    $endTime (optional) string request open interest history ending here
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresGlobalLongShortAccountRatio(string $symbol, string $period = '5m', int $limit = null, $startTime = null, $endTime = null)
    {
        return $this->symbolPeriodLimitStartEndRequest($symbol, $period, $limit, $startTime, $endTime, 'globalLongShortAccountRatio', 'fapiData');
    }

    /**
     * futuresTakerLongShortRatio get the taker Long/Short Ratio for symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Taker-BuySell-Volume
     *
     * $ratio = $api->futuresTakerLongShortRatio("ETHBTC", 5m);
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. ETHUSDT
     * @param string $period (optional) string of period to query - 5m, 15m, 30m, 1h, 2h, 4h, 6h, 12h, 1d (default 5m)
     * @param int    $limit (optional) int limit the amount of open interest history (default 30, max 500)
     * @param int    $startTime (optional) string request open interest history starting from here
     * @param int    $endTime (optional) string request open interest history ending here
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresTakerLongShortRatio(string $symbol, string $period = '5m', int $limit = null, $startTime = null, $endTime = null)
    {
        return $this->symbolPeriodLimitStartEndRequest($symbol, $period, $limit, $startTime, $endTime, 'takerlongshortRatio', 'fapiData');
    }

    /**
     * futuresBasis get future basis for symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Basis
     *
     * $basis = $api->futuresBasis("ETHBTC", 5m);
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. ETHUSDT
     * @param string $period (optional) string of period to query - 5m, 15m, 30m, 1h, 2h, 4h, 6h, 12h, 1d (default 5m)
     * @param int    $limit (optional) int limit the amount of open interest history (default 30, max 500)
     * @param int    $startTime (optional) string request open interest history starting from here
     * @param int    $endTime (optional) string request open interest history ending here
     * @param string $contractType (optional) string of period to query - PERPETUAL, CURRENT_QUARTER, NEXT_QUARTER (default PERPETUAL)
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresBasis(string $symbol, string $period = '5m', int $limit = 30, $startTime = null, $endTime = null, $contractType = 'PERPETUAL')
    {
        $parameters = [
            'pair' => $symbol,
            'period' => $period,
            'contractType' => $contractType,
            'fapiData' => true,
        ];
        if ($limit) {
            $parameters['limit'] = $limit;
        }
        if ($startTime) {
            $parameters['startTime'] = $startTime;
        }
        if ($endTime) {
            $parameters['endTime'] = $endTime;
        }
        return $this->httpRequest("basis", 'GET', $parameters);
    }

    /**
     * futuresIndexInfo get composite index symbol information
     * only for composite index symbols
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Composite-Index-Symbol-Information
     *
     * $indexInfo = $api->futuresIndexInfo("DEFIUSDT");
     *
     * @property int $weight 1
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. DEFIUSDT
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresIndexInfo(string $symbol)
    {
        $parameters = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        return $this->httpRequest("v1/indexInfo", 'GET', $parameters);
    }

    /**
     * asset assetIndex get the asset index for a symbol or all symbols
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Multi-Assets-Mode-Asset-Index
     *
     * $assetIndex = $api->futuresAssetIndex();
     * $assetIndex = $api->futuresAssetIndex("USDCUSD");
     *
     * @property int $weight 1
     * with symbol parameter omitted weight is 10
     *
     * @param string $symbol (optional) market symbol to get the response for, e.g. USDCUSD
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresAssetIndex(string $symbol = null)
    {
        $parameters = [
            'fapi' => true,
        ];
        if ($symbol) {
            $parameters['symbol'] = $symbol;
        }
        return $this->httpRequest("v1/assetIndex", 'GET', $parameters);
    }

    /**
     * futuresConstituents get the index price constituents
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Index-Constituents
     *
     * $constituents = $api->futuresConstituents("BTCUSDT");
     *
     * @property int $weight 2
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. ETHUSDT
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresConstituents(string $symbol)
    {
        $parameters = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        return $this->httpRequest("v1/indexInfo", 'GET', $parameters);
    }

    /**
     * createFuturesOrderRequest
     * helper for creating the request for futures order
     * @return array containing the request
     * @throws \Exception
     */
    protected function createFuturesOrderRequest(string $side, string $symbol, $quantity = null, $price = null, string $type = 'LIMIT', array $flags = []) {
        $opt = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => $type,
        ];

        // someone has preformated there 8 decimal point double already
        // dont do anything, leave them do whatever they want
        if ($price && gettype($price) !== 'string') {
            // for every other type, lets format it appropriately
            $price = number_format($price, 8, '.', '');
        }

        if ($quantity) {
            if (is_numeric($quantity) === false) {
                // WPCS: XSS OK.
                echo "warning: quantity expected numeric got " . gettype($quantity) . PHP_EOL;
            }
            if (isset($flags['closePosition']) && $flags['closePosition'] === true) {
                // WPCS: XSS OK.
                echo "warning: closePosition is set to true, quantity will be ignored" . PHP_EOL;
            } else {
                $opt['quantity'] = $quantity;
            }
        }

        if ($price && is_string($price) === false) {
            // WPCS: XSS OK.
            echo "warning: price expected string got " . gettype($price) . PHP_EOL;
        }

        if ($type === "LIMIT" || $type === "STOP_LOSS_LIMIT" || $type === "TAKE_PROFIT_LIMIT") {
            $opt["price"] = $price;
            if (!isset($flags['timeInForce'])) {
                $opt['timeInForce'] = 'GTC';
            }
        }

        if (isset($flags['positionSide'])) {
            $opt['positionSide'] = $flags['positionSide'];
        }

        if (isset($flags['timeInForce'])) {
            $opt['timeInForce'] = $flags['timeInForce'];
        }

        if (isset($flags['reduceOnly'])) {
            $reduceOnly = $flags['reduceOnly'];
            if ($reduceOnly === true) {
                $opt['reduceOnly'] = 'true';
            } else {
                $opt['reduceOnly'] = 'false';
            }
        }

        if (isset($flags['newClientOrderId'])) {
            $opt['newClientOrderId'] = $flags['newClientOrderId'];
        } else {
            $opt['newClientOrderId'] = $this->generateFuturesClientOrderId();
        }

        if (isset($flags['stopPrice'])) {
            $opt['stopPrice'] = $flags['stopPrice'];
        }

        if (isset($flags['closePosition'])) {
            $closePosition = $flags['closePosition'];
            if ($closePosition === true) {
                $opt['closePosition'] = 'true';
            } else {
                $opt['closePosition'] = 'false';
            }
        }

        if (isset($flags['activationPrice'])) {
            $opt['activationPrice'] = $flags['activationPrice'];
        }

        if (isset($flags['callbackRate'])) {
            $opt['callbackRate'] = $flags['callbackRate'];
        }

        if (isset($flags['workingType'])) {
            $opt['workingType'] = $flags['workingType'];
        }

        if (isset($flags['priceProtect'])) {
            $priceProtect = $flags['priceProtect'];
            if ($priceProtect === true) {
                $opt['priceProtect'] = 'TRUE';
            } else {
                $opt['priceProtect'] = 'FALSE';
            }
        }

        if (isset($flags['newOrderRespType'])) {
            $opt['newOrderRespType'] = $flags['newOrderRespType'];
        }

        if (isset($flags['priceMatch'])) {
            $opt['priceMatch'] = $flags['priceMatch'];
        }

        if (isset($flags['selfTradePreventionMode'])) {
            $opt['selfTradePreventionMode'] = $flags['selfTradePreventionMode'];
        }

        if (isset($flags['goodTillDate'])) {
            $opt['goodTillDate'] = $flags['goodTillDate'];
        }

        if (isset($flags['recvWindow'])) {
            $opt['recvWindow'] = $flags['recvWindow'];
        }

        return $opt;
    }

    /**
     * futuresOrder formats the orders before sending them to the curl wrapper function
     * You can call this function directly or use the helper functions
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api
     *
     * @see futuresBuy()
     * @see sell()
     * @see marketBuy()
     * @see marketSell()
     *
     * @param string $side (mandatory) typically "BUY" or "SELL"
     * @param string $symbol (mandatory) market symbol
     * @param string $quantity (optional) of the order (Cannot be sent with closePosition=true (Close-All))
     * @param string $price (optional) price per unit
     * @param string $type (mandatory) is determined by the symbol bu typicall LIMIT, STOP_LOSS_LIMIT etc.
     * @param array $flags (optional) additional transaction options
     * - @param string $flags['positionSide'] position side, "BOTH" for One-way Mode; "LONG" or "SHORT" for Hedge Mode (mandatory for Hedge Mode)
     * - @param string $flags['timeInForce']
     * - @param bool   $flags['reduceOnly'] default false (Cannot be sent in Hedge Mode; cannot be sent with closePosition=true)
     * - @param string $flags['newClientOrderId'] new client order id
     * - @param string $flags['stopPrice'] stop price (Used with STOP/STOP_MARKET or TAKE_PROFIT/TAKE_PROFIT_MARKET orders)
     * - @param bool   $flags['closePosition'] Close-All (used with STOP_MARKET or TAKE_PROFIT_MARKET orders)
     * - @param string $flags['activationPrice'] Used with TRAILING_STOP_MARKET orders, default as the latest price (supporting different workingType)
     * - @param string $flags['callbackRate'] Used with TRAILING_STOP_MARKET orders, min 0.1, max 5 where 1 for 1%
     * - @param string $flags['workingType'] stopPrice triggered by: "MARK_PRICE", "CONTRACT_PRICE". Default "CONTRACT_PRICE"
     * - @param bool   $flags['priceProtect'] Used with STOP/STOP_MARKET or TAKE_PROFIT/TAKE_PROFIT_MARKET orders (default false)
     * - @param string $flags['newOrderRespType'] response type, default "RESULT", other option is "ACK"
     * - @param string $flags['priceMatch'] only avaliable for LIMIT/STOP/TAKE_PROFIT order; can be set to OPPONENT/ OPPONENT_5/ OPPONENT_10/ OPPONENT_20: /QUEUE/ QUEUE_5/ QUEUE_10/ QUEUE_20; Can't be passed together with price
     * - @param string $flags['selfTradePreventionMode'] EXPIRE_TAKER:expire taker order when STP triggers/ EXPIRE_MAKER:expire taker order when STP triggers/ EXPIRE_BOTH:expire both orders when STP triggers; default NONE
     * - @param string $flags['goodTillDate'] order cancel time for timeInForce GTD, mandatory when timeInforce set to GTD; order the timestamp only retains second-level precision, ms part will be ignored; The goodTillDate timestamp must be greater than the current time plus 600 seconds and smaller than 253402300799000
     * - @param int    $flags['recvWindow']
     * @param $test bool whether to test or not, test only validates the query
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresOrder(string $side, string $symbol, $quantity = null, $price = null, string $type = 'LIMIT', array $flags = [], $test = false)
    {
        $opt = $this->createFuturesOrderRequest($side, $symbol, $quantity, $price, $type, $flags);
        $opt['fapi'] = true;
        $qstring = ($test === false) ? 'v1/order' : 'v1/order/test';
        return $this->httpRequest($qstring, 'POST', $opt, true);
    }

    /**
     * futuresBuy attempts to create a buy order
     * each market supports a number of order types, such as
     * -LIMIT
     * -MARKET
     * -STOP_LOSS
     * -STOP_LOSS_LIMIT
     * -TAKE_PROFIT
     * -TAKE_PROFIT_LIMIT
     * -LIMIT_MAKER
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api
     *
     * You should check the @see exchangeInfo for each currency to determine
     * what types of orders can be placed against specific pairs
     *
     * $quantity = 1;
     * $price = 0.0005;
     * $order = $api->futuresBuy("BNBBTC", $quantity, $price);
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param string $quantity (optional) the quantity required
     * @param string $price (optional) price per unit
     * @param string $type (mandatory) type of order
     * @param array $flags (optional) addtional options for order type
     * - @param string $flags['positionSide'] position side, "BOTH" for One-way Mode; "LONG" or "SHORT" for Hedge Mode (mandatory for Hedge Mode)
     * - @param string $flags['timeInForce']
     * - @param bool   $flags['reduceOnly'] default false (Cannot be sent in Hedge Mode; cannot be sent with closePosition=true)
     * - @param string $flags['newClientOrderId'] new client order id
     * - @param string $flags['stopPrice'] stop price (Used with STOP/STOP_MARKET or TAKE_PROFIT/TAKE_PROFIT_MARKET orders)
     * - @param bool   $flags['closePosition'] Close-All (used with STOP_MARKET or TAKE_PROFIT_MARKET orders)
     * - @param string $flags['activationPrice'] Used with TRAILING_STOP_MARKET orders, default as the latest price (supporting different workingType)
     * - @param string $flags['callbackRate'] Used with TRAILING_STOP_MARKET orders, min 0.1, max 5 where 1 for 1%
     * - @param string $flags['workingType'] stopPrice triggered by: "MARK_PRICE", "CONTRACT_PRICE". Default "CONTRACT_PRICE"
     * - @param bool   $flags['priceProtect'] Used with STOP/STOP_MARKET or TAKE_PROFIT/TAKE_PROFIT_MARKET orders (default false)
     * - @param string $flags['newOrderRespType'] response type, default "RESULT", other option is "ACK"
     * - @param string $flags['priceMatch'] only avaliable for LIMIT/STOP/TAKE_PROFIT order; can be set to OPPONENT/ OPPONENT_5/ OPPONENT_10/ OPPONENT_20: /QUEUE/ QUEUE_5/ QUEUE_10/ QUEUE_20; Can't be passed together with price
     * - @param string $flags['selfTradePreventionMode'] EXPIRE_TAKER:expire taker order when STP triggers/ EXPIRE_MAKER:expire taker order when STP triggers/ EXPIRE_BOTH:expire both orders when STP triggers; default NONE
     * - @param string $flags['goodTillDate'] order cancel time for timeInForce GTD, mandatory when timeInforce set to GTD; order the timestamp only retains second-level precision, ms part will be ignored; The goodTillDate timestamp must be greater than the current time plus 600 seconds and smaller than 253402300799000
     * - @param int    $flags['recvWindow']
     * @return array with error message or the order details
     */
    public function futuresBuy(string $symbol, $quantity = null, $price = null, string $type = 'LIMIT', array $flags = [])
    {
        return $this->futuresOrder('BUY', $symbol, $quantity, $price, $type, $flags);
    }

    /**
     * futuresBuyTest attempts to create a TEST futures buy order
     *
     * @see futuresBuy()
     *
     * params and return value are the same as @see futuresBuy()
     */
    public function futuresBuyTest(string $symbol, $quantity = null, $price = null, string $type = 'LIMIT', array $flags = [])
    {
        return $this->futuresOrder('BUY', $symbol, $quantity, $price, $type, $flags, true);
    }

    /**
     * futuresSell creates a futures sell order
     * each market supports a number of order types, such as
     * -LIMIT
     * -MARKET
     * -STOP_LOSS
     * -STOP_LOSS_LIMIT
     * -TAKE_PROFIT
     * -TAKE_PROFIT_LIMIT
     * -LIMIT_MAKER
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api
     *
     * You should check the @see exchangeInfo for each currency to determine
     * what types of orders can be placed against specific pairs
     *
     * $quantity = 1;
     * $price = 0.0005;
     * $order = $api->futuresSell("BNBBTC", $quantity, $price);
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param string $quantity (optional) the quantity required
     * @param string $price (optional) price per unit
     * @param string $type (mandatory) type of order
     * @param array $flags (optional) addtional options for order type
     * - @param string $flags['positionSide'] position side, "BOTH" for One-way Mode; "LONG" or "SHORT" for Hedge Mode (mandatory for Hedge Mode)
     * - @param string $flags['timeInForce']
     * - @param bool   $flags['reduceOnly'] default false (Cannot be sent in Hedge Mode; cannot be sent with closePosition=true)
     * - @param string $flags['newClientOrderId'] new client order id
     * - @param string $flags['stopPrice'] stop price (Used with STOP/STOP_MARKET or TAKE_PROFIT/TAKE_PROFIT_MARKET orders)
     * - @param bool   $flags['closePosition'] Close-All (used with STOP_MARKET or TAKE_PROFIT_MARKET orders)
     * - @param string $flags['activationPrice'] Used with TRAILING_STOP_MARKET orders, default as the latest price (supporting different workingType)
     * - @param string $flags['callbackRate'] Used with TRAILING_STOP_MARKET orders, min 0.1, max 5 where 1 for 1%
     * - @param string $flags['workingType'] stopPrice triggered by: "MARK_PRICE", "CONTRACT_PRICE". Default "CONTRACT_PRICE"
     * - @param bool   $flags['priceProtect'] Used with STOP/STOP_MARKET or TAKE_PROFIT/TAKE_PROFIT_MARKET orders (default false)
     * - @param string $flags['newOrderRespType'] response type, default "RESULT", other option is "ACK"
     * - @param string $flags['priceMatch'] only avaliable for LIMIT/STOP/TAKE_PROFIT order; can be set to OPPONENT/ OPPONENT_5/ OPPONENT_10/ OPPONENT_20: /QUEUE/ QUEUE_5/ QUEUE_10/ QUEUE_20; Can't be passed together with price
     * - @param string $flags['selfTradePreventionMode'] EXPIRE_TAKER:expire taker order when STP triggers/ EXPIRE_MAKER:expire taker order when STP triggers/ EXPIRE_BOTH:expire both orders when STP triggers; default NONE
     * - @param string $flags['goodTillDate'] order cancel time for timeInForce GTD, mandatory when timeInforce set to GTD; order the timestamp only retains second-level precision, ms part will be ignored; The goodTillDate timestamp must be greater than the current time plus 600 seconds and smaller than 253402300799000
     * - @param int    $flags['recvWindow']
     * @return array with error message or the order details
     */
    public function futuresSell(string $symbol, $quantity = null, $price = null, string $type = 'LIMIT', array $flags = [])
    {
        return $this->futuresOrder('SELL', $symbol, $quantity, $price, $type, $flags);
    }

    /**
     * futuresSellTest attempts to create a TEST futures sell order
     *
     * @see futuresSell()
     *
     * params and return value are the same as @see futuresSell()
     */
    public function futuresSellTest(string $symbol, $quantity = null, $price = null, $type = null, array $flags = [])
    {
        if ($type === null) {
            $type = 'LIMIT';
        }
        return $this->futuresOrder('SELL', $symbol, $quantity, $price, $type, $flags, true);
    }

    /**
     * createBatchOrdersRequest
     * helper for creating the request for multiple futures orders
     * @param array $orders (mandatory) array of orders to be placed
     * objects in the array should contain literally the same keys as the @see futuresOrder but without the recvWindow
     *
     * @return array containing the request
     * @throws \Exception
     */
    protected function createBatchOrdersRequest(array $orders)
    {
        $formatedOrders = [];
        for ($index = 0; $index < count($orders); $index++) {
            $order = $orders[$index];
            if (!isset($order['quantity'])) {
                $order['quantity'] = null;
            }
            if (!isset($order['price'])) {
                $order['price'] = null;
            }
            if (!isset($order['flags'])) {
                $order['flags'] = [];
            }
            $formatedOrder = $this->createFuturesOrderRequest(
                $order['side'],
                $order['symbol'],
                $order['quantity'],
                $order['price'],
                $order['type'],
                $order['flags']
            );
            if (isset($formatedOrder['recvWindow'])) {
                // remove recvWindow from the order
                unset($formatedOrder['recvWindow']);
            }
            if (isset($order['orderId'])) {
                $formatedOrder['orderId'] = $order['orderId'];
            }
            if (isset($order['origClientOrderId'])) {
                $formatedOrder['origClientOrderId'] = $order['origClientOrderId'];
            }
            $formatedOrders[$index] = $formatedOrder;
        }
        return $formatedOrders;
    }

    /**
     * futuresBatchOrders creates multiple orders in a single request
     * max 5 orders
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Place-Multiple-Orders
     *
     * @param array $orders (mandatory) array of orders to be placed
     * objects in the array should contain literally the same keys as the @see futuresOrder but without the $flags['recvWindow']
     * @param string $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array containing the response or error message
     * @throws \Exception
     */
    public function futuresBatchOrders(array $orders, int $recvWindow = null)
    {
        $params = [
            'fapi' => true,
        ];
        $formatedOrders = $this->createBatchOrdersRequest($orders);
        if (count($formatedOrders) > 5) {
            throw new \Exception('futuresBatchOrders: max 5 orders allowed');
        }
        if (count($formatedOrders) < 1) {
            throw new \Exception('futuresBatchOrders: at least 1 order required');
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        // current endpoint accepts orders list as a json string in the query string
        $encodedOrders = json_encode($formatedOrders);
        $url = 'v1/batchOrders?batchOrders=' . $encodedOrders;
        return $this->httpRequest($url, 'POST', $params, true);
    }

    /**
     * futuresEditOrder edits the limit order
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Modify-Order
     *
     * @param string $symbol (mandatory) market symbol
     * @param string $side (mandatory) "BUY" or "SELL"
     * @param string $orderId (optional) order id to be modified (mandatory if $flags['origClientOrderId'] is not set)
     * @param string $quantity (optional) of the order (Cannot be sent for orders with closePosition=true (Close-All))
     * @param string $price (mandatory) price per unit
     * @param array $flags (optional) additional options
     * - @param string $flags['priceMatch'] only avaliable for LIMIT/STOP/TAKE_PROFIT order; can be set to OPPONENT/ OPPONENT_5/ OPPONENT_10/ OPPONENT_20: /QUEUE/ QUEUE_5/ QUEUE_10/ QUEUE_20; Can't be passed together with price
     * - @param int    $flags['recvWindow']
     * - @param string $flags['origClientOrderId'] client order id to be modified (mandatory if $orderId is not set)
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresEditOrder(string $symbol, string $side, string $quantity, string $price, $orderId = null, array $flags = [])
    {
        $opt = $this->createFuturesOrderRequest($side, $symbol, $quantity, $price, 'LIMIT', $flags);
        $origClientOrderId = null;
        if (isset($flags['origClientOrderId'])) {
            $origClientOrderId = $flags['origClientOrderId'];
            $opt['origClientOrderId'] = $origClientOrderId;
        }
        if (!$origClientOrderId && !$orderId) {
            throw new \Exception('futuresEditOrder: either orderId or origClientOrderId must be set');
        }
        if ($orderId) {
            $opt['orderId'] = $orderId;
        }
        unset($opt['type']);
        $opt['fapi'] = true;
        return $this->httpRequest("v1/order", 'PUT', $opt, true);
    }

    /**
     * futuresEditOrders edits the multiple limit orders
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Modify-Multiple-Orders
     *
     * @param array $orders (mandatory) array of orders to be modified
     * objects in the array should contain literally the same keys as the @see futuresEditOrder but without the $flags['recvWindow']
     * @param string $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array containing the response or error message
     * @throws \Exception
     */
    public function futuresEditOrders(array $orders, $recvWindow = null)
    {
        $params = [
            'fapi' => true,
        ];
        $formatedOrders = $this->createBatchOrdersRequest($orders);
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        // current endpoint accepts orders list as a json string in the query string
        $encodedOrders = json_encode($formatedOrders);
        $url = 'v1/batchOrders?batchOrders=' . $encodedOrders;
        return $this->httpRequest($url, 'PUT', $params, true);
    }

    /**
     * futuresOrderAmendment get order modification history
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Get-Order-Modify-History
     *
     * $amendment = $api->futuresOrderAmendment("ETHUSDT");
     *
     * @param string $symbol (mandatory) market symbol to get the response for, e.g. ETHUSDT
     * @param string $orderId (optional) order id to get the response for
     * @param string $origClientOrderId (optional) original client order id to get the response for
     * @param int    $startTime (optional) timestamp in ms to get modification history from INCLUSIVE
     * @param int    $endTime (optional) timestamp in ms to get modification history until INCLUSIVE
     * @param int    $limit (optional) limit the amount of open interest history (default 50, max 100)
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresOrderAmendment(string $symbol, $orderId = null, $origClientOrderId = null, $startTime = null, $endTime = null, $limit = null, int $recvWindow = null)
    {
        $params = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        if ($orderId) {
            $params['orderId'] = $orderId;
        }
        if ($origClientOrderId) {
            $params['origClientOrderId'] = $origClientOrderId;
        }
        if ($startTime) {
            $params['startTime'] = $startTime;
        }
        if ($endTime) {
            $params['endTime'] = $endTime;
        }
        if ($limit) {
            $params['limit'] = $limit;
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/orderAmendment", 'GET', $params, true);
    }

    /**
     * futuresCancel cancels a futures order
     *
     * $orderid = "123456789";
     * $order = $api->futuresCancel("BNBBTC", $orderid);
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param string $orderid (optional) the orderid to cancel (mandatory if $flags['origClientOrderId'] is not set)
     * @param array  $flags (optional) additional options
     * - @param string $flags['origClientOrderId'] original client order id to cancel
     * - @param int    $flags['recvWindow'] the time in milliseconds to wait for a response
     *
     * @return array with error message or the order details
     * @throws \Exception
     */
    public function futuresCancel(string $symbol, $orderid, $flags = [])
    {
        $params = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        if ($orderid) {
            $params['orderId'] = $orderid;
        } else if (!isset($flags['origClientOrderId'])) {
            throw new \Exception('futuresCancel: either orderId or origClientOrderId must be set');
        }
        return $this->httpRequest("v1/order", 'DELETE', array_merge($params, $flags), true);
    }

    /**
     * futuresCancelBatchOrders canceles multiple futures orders
     *
     * $orderIds = ["123456789", "987654321"];
     * $order = $api->futuresCancelBatchOrders("BNBBTC", $orderIds);
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param array  $orderIdList (optional) list of ids to cancel (mandatory if origClientOrderIdList is not set)
     * @param array  $origClientOrderIdList (optional) list of client order ids to cancel (mandatory if orderIdList is not set)
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the orders details
     * @throws \Exception
     */
    public function futuresCancelBatchOrders(string $symbol, $orderIdList = null, $origClientOrderIdList = null, int $recvWindow = null)
    {
        $params = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        if ($orderIdList) {
            $idsString = json_encode($orderIdList);
            // remove quotes and spaces
            $params['orderIdList'] = str_replace(' ', '', str_replace('"', '', str_replace("'", '', $idsString)));
        } else if ($origClientOrderIdList) {
            // remove spaces
            $params['origClientOrderIdList'] = str_replace(' ', '', json_encode($origClientOrderIdList));
        } else {
            throw new \Exception('futuresCancelBatchOrders: either orderIdList or origClientOrderIdList must be set');
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/batchOrders", 'DELETE', $params, true);
    }

    /**
     * futuresCancelOpenOrders cancels all open futures orders for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Cancel-All-Open-Orders
     *
     * $orders = $api->futuresCancelOpenOrders("BNBBTC");
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param int    $recvWindow the time in milliseconds to wait for a response
     *
     * @return array with error message or the orders details
     * @throws \Exception
     */
    public function futuresCancelOpenOrders(string $symbol, int $recvWindow = null)
    {
        $params = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/allOpenOrders", 'DELETE', $params, true);
    }

    /**
     * futuresCountdownCancelAllOrders cancels all open futures orders for a symbol at the end of the specified countdown
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Auto-Cancel-All-Open-Orders
     *
     * $orders = $api->futuresCountdownCancelAllOrders("BNBBTC", 10);
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param int    $countdownTime (mandatory) countdown in milliseconds (0 to stop the timer)
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the orders details
     * @throws \Exception
     */
    public function futuresCountdownCancelAllOrders(string $symbol, int $countdownTime, int $recvWindow = null)
    {
        $params = [
            'symbol' => $symbol,
            'fapi' => true,
            'countdownTime' => $countdownTime,
        ];
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/countdownCancelAll", 'POST', $params, true);
    }

    /**
     * futuresOrderStatus gets the details of a futures order
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Query-Order
     *
     * $order = $api->futuresOrderStatus("BNBBTC", "123456789");
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param string $orderId (optional) order id to get the response for (mandatory if origClientOrderId is not set)
     * @param string $origClientOrderId (optional) original client order id to get the response for (mandatory if orderId is not set)
     * @param string $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the order details
     * @throws \Exception
     */
    public function futuresOrderStatus(string $symbol, $orderId = null, $origClientOrderId = null, int $recvWindow = null)
    {
        $params = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        if ($orderId) {
            $params['orderId'] = $orderId;
        } else if ($origClientOrderId) {
            $params['origClientOrderId'] = $origClientOrderId;
        } else {
            throw new \Exception('futuresOrderStatus: either orderId or origClientOrderId must be set');
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/order", 'GET', $params, true);
    }

    /**
     * futuresAllOrders gets all orders for a symbol
     * query time period must be less then 7 days (default as the recent 7 days)
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/All-Orders
     *
     * $orders = $api->futuresAllOrders("BNBBTC");
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param int    $startTime (optional) timestamp in ms to get orders from INCLUSIVE
     * @param int    $endTime (optional) timestamp in ms to get orders until INCLUSIVE
     * @param int    $limit (optional) limit the amount of orders (default 500, max 1000)
     * @param string $orderId (optional) order id to get the response from (if is set it will get orders >= that orderId)
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     */
    public function futuresAllOrders(string $symbol, $startTime = null, $endTime = null, $limit = null, $orderId = null, int $recvWindow = null)
    {
        $params = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        if ($startTime) {
            $params['startTime'] = $startTime;
        }
        if ($endTime) {
            $params['endTime'] = $endTime;
        }
        if ($limit) {
            $params['limit'] = $limit;
        }
        if ($orderId) {
            $params['orderId'] = $orderId;
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/allOrders", 'GET', $params, true);
    }

    /**
     * futuresOpenOrders gets all open orders for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Current-All-Open-Orders
     *
     * $orders = $api->futuresOpenOrders();
     * $orders = $api->futuresOpenOrders("BNBBTC");
     *
     * @param string $symbol (optional) market symbol (e.g. ETHUSDT)
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the orders details
     * @throws \Exception
     */
    public function futuresOpenOrders($symbol = null, int $recvWindow = null)
    {
        $params = [
            'fapi' => true,
        ];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/openOrders", 'GET', $params, true);
    }

    /**
     * futuresOpenOrder gets an open futures order
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Query-Current-Open-Order
     *
     * $order = $api->futuresOpenOrder("BNBBTC", "123456789");
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param string $orderId (optional) order id to get the response for (mandatory if origClientOrderId is not set)
     * @param string $origClientOrderId (optional) original client order id to get the response for (mandatory if orderId is not set)
     * @param string $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the order details
     * @throws \Exception
     */
    public function futuresOpenOrder(string $symbol, $orderId = null, $origClientOrderId = null, int $recvWindow = null)
    {
        $params = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        if ($orderId) {
            $params['orderId'] = $orderId;
        } else if ($origClientOrderId) {
            $params['origClientOrderId'] = $origClientOrderId;
        } else {
            throw new \Exception('futuresOpenOrder: either orderId or origClientOrderId must be set');
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/openOrder", 'GET', $params, true);
    }
    /**
     * futuresForceOrders gets all futures force orders
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Users-Force-Orders
     *
     * $orders = $api->futuresForceOrders("BNBBTC");
     *
     * @property int $weight 50
     * 20 if symbol is not passed
     *
     * @param string $symbol (optional) market symbol (e.g. ETHUSDT)
     * @param int    $startTime (optional) timestamp in ms to get orders from INCLUSIVE
     * @param int    $endTime (optional) timestamp in ms to get orders until INCLUSIVE
     * @param int    $limit (optional) limit the amount of orders (default 500, max 1000)
     * @param string $autoCloseType (optional) "LIQUIDATION" for liquidation orders, "ADL" for ADL orders
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the orders details
     * @throws \Exception
     */
    public function futuresForceOrders($symbol = null, $startTime = null, $endTime = null, $limit = null, $autoCloseType = null, $recvWindow = null)
    {
        $params = [
            'fapi' => true,
        ];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        if ($startTime) {
            $params['startTime'] = $startTime;
        }
        if ($endTime) {
            $params['endTime'] = $endTime;
        }
        if ($limit) {
            $params['limit'] = $limit;
        }
        if ($autoCloseType) {
            $params['autoCloseType'] = $autoCloseType;
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/forceOrders", 'GET', $params, true);
    }

    /**
     * futuresMyTrades gets all futures trades for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Account-Trade-List
     *
     * $trades = $api->futuresMyTrades("BNBBTC");
     *
     * @property int $weight 5
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param int    $startTime (optional) timestamp in ms to get trades from INCLUSIVE
     * @param int    $endTime (optional) timestamp in ms to get trades until INCLUSIVE
     * @param int    $limit (optional) limit the amount of trades (default 500, max 1000)
     * @param string $orderId (optional) order id to get the trades for
     * @param string $fromId (optional) trade id to get the trades from (if is set it will get trades >= that Id)
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the trades details
     * @throws \Exception
     */
    public function futuresMyTrades(string $symbol, $startTime = null, $endTime = null, $limit = null, $orderId = null, $fromId = null, int $recvWindow = null)
    {
        $params = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        if ($startTime) {
            $params['startTime'] = $startTime;
        }
        if ($endTime) {
            $params['endTime'] = $endTime;
        }
        if ($limit) {
            $params['limit'] = $limit;
        }
        if ($orderId) {
            $params['orderId'] = $orderId;
        }
        if ($fromId) {
            $params['fromId'] = $fromId;
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/userTrades", 'GET', $params, true);
    }

    /**
     * futuresHistory
     * another name for futuresMyTrades (for naming compatibility with spot)
     * @deprecated
     */
    public function futuresHistory(string $symbol, $startTime = null, $endTime = null, $limit = null, $orderId = null, $fromId = null, int $recvWindow = null)
    {
        return $this->futuresMyTrades($symbol, $startTime, $endTime, $limit, $orderId, $fromId, $recvWindow);
    }

    /**
     * futuresSetMarginMode sets the margin mode for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Change-Margin-Type
     *
     * $api->futuresSetMarginMode("BNBBTC", "ISOLATED");
     *
     * @property int $weight 1
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param string $marginType (mandatory) margin type, "CROSSED" or "ISOLATED"
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresSetMarginMode(string $symbol, string $marginType, int $recvWindow = null)
    {
        $params = [
            'symbol' => $symbol,
            'fapi' => true,
            'marginType' => $marginType,
        ];
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/marginType", 'POST', $params, true);
    }

    /**
     * futuresPositionMode gets the position mode for ALL symbols
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Get-Current-Position-Mode
     *
     * $response = $api->futuresPositionMode();
     *
     * @property int $weight 30
     *
     * @param int $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresPositionMode(int $recvWindow = null)
    {
        $params = [
            'fapi' => true,
        ];
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/positionSide/dual", 'GET', $params, true);
    }

    /**
     * futuresSetPositionMode sets the position mode for ALL symbols
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Change-Position-Mode
     *
     * $api->futuresSetPositionMode(true);
     *
     * @property int $weight 1
     *
     * @param bool $dualSidePosition (mandatory) true for Hedge Mode, false for One-way Mode
     * @param int  $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresSetPositionMode(bool $dualSidePosition, int $recvWindow = null)
    {
        $params = [
            'fapi' => true,
            'dualSidePosition' => $dualSidePosition ? 'true' : 'false',
        ];
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/positionSide/dual", 'POST', $params, true);
    }

    /**
     * futuresSetLeverage sets the leverage for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Change-Initial-Leverage
     *
     * $leverage = $api->futuresSetLeverage(10, "BTCUSDT");
     *
     * @property int $weight 1
     *
     *
     * @param int    $leverage (mandatory) leverage to be set (min 1, max 125)
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresSetLeverage(int $leverage, string $symbol, int $recvWindow = null)
    {
        $params = [
            'symbol' => $symbol,
            'fapi' => true,
            'leverage' => $leverage,
        ];
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/leverage", 'POST', $params, true);
    }

    /**
     * futuresMultiAssetsMarginMode gets the multi-assets margin mode for ALL symbols
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Get-Current-Multi-Assets-Mode
     *
     * $response = $api->futuresMultiAssetsMarginMode();
     *
     * @property int $weight 30
     *
     * @param int $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresMultiAssetsMarginMode(int $recvWindow = null)
    {
        $params = [
            'fapi' => true,
        ];
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/multiAssetsMargin", 'GET', $params, true);
    }

    /**
     * futuresSetMultiAssetsMarginMode sets the multi-assets margin mode for ALL symbols
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Change-Multi-Assets-Mode
     *
     * $response = $api->futuresSetMultiAssetsMarginMode(true);
     *
     * @property int $weight 1
     *
     * @param bool $multiAssetsMarginMode (mandatory) true for multi-assets mode, false for single-asset mode
     * @param int $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresSetMultiAssetsMarginMode(bool $multiAssetsMarginMode, int $recvWindow = null)
    {
        $params = [
            'fapi' => true,
            'multiAssetsMarginMode' => $multiAssetsMarginMode ? 'true' : 'false',
        ];
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/multiAssetsMarginMode", 'POST', $params, true);
    }

    /**
     * modifyMarginHelper helper for adding or removing margin
     *
     * @see futuresAddMargin() and futuresReduceMargin()
     *
     * @return array containing the response
     * @throws \Exception
     */
    protected function modifyMarginHelper(string $symbol, string $amount, $addOrReduce, $positionSide = null, int $recvWindow = null)
    {
        $params = [
            'symbol' => $symbol,
            'fapi' => true,
            'amount' => $amount,
            'type' => $addOrReduce,
        ];
        if ($positionSide) {
            $params['positionSide'] = $positionSide;
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/positionMargin", 'POST', $params, true);
    }

    /**
     * futuresAddMargin adds margin to a position
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Modify-Isolated-Position-Margin
     *
     * $response = $api->futuresAddMargin("BNBBTC", 10);
     *
     * @property int $weight 1
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param string $amount (mandatory) amount to be added
     * @param string $positionSide (optional) position side - "BOTH" for non-hedged and "LONG" or "SHORT" for hedged (mandatory for hedged positions)
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresAddMargin(string $symbol, string $amount, $positionSide = null, int $recvWindow = null)
    {
        return $this->modifyMarginHelper($symbol, $amount, 1, $positionSide, $recvWindow);
    }

    /**
     * futuresReduceMargin removes margin from a position
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Modify-Isolated-Position-Margin
     *
     * $response = $api->futuresReduceMargin("BNBBTC", 10);
     *
     * @property int $weight 1
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param string $amount (mandatory) amount to be removed
     * @param string $positionSide (optional) position side - "BOTH" for non-hedged and "LONG" or "SHORT" for hedged (mandatory for hedged positions)
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresReduceMargin(string $symbol, string $amount, $positionSide = null, int $recvWindow = null)
    {
        return $this->modifyMarginHelper($symbol, $amount, 2, $positionSide, $recvWindow);
    }

    /**
     * futuresPositions gets the position information for a symbol or all symbols
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Position-Information-V2
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Position-Information-V3
     *
     * $position = $api->futuresPositions("BNBBTC");
     *
     * @property int $weight 5
     *
     * @param string $symbol (optional) market symbol (e.g. ETHUSDT)
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     * @param string $api_version (optional) API version, "v2" or "v3" (default is v3)
     *
     * @return array with error message or the position details
     * @throws \Exception
     */
    public function futuresPositions($symbol = null, $recvWindow = null, $api_version = 'v3')
    {
        $params = [
            'fapi' => true,
        ];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        if ($api_version !== 'v2' && $api_version !== 'v3') {
            throw new \Exception('futuresPositions: api_version must be either v2 or v3');
        }
        return $this->httpRequest($api_version . "/positionRisk", 'GET', $params, true);
    }

    /** futuresPositionsV2
     * @see futuresPositions
     */
    public function futuresPositionsV2($symbol = null, int $recvWindow = null)
    {
        return $this->futuresPositions($symbol, $recvWindow, 'v2');
    }

    /**
     * futuresPositionsV3
     * @see futuresPositions
     */
    public function futuresPositionsV3($symbol = null, int $recvWindow = null)
    {
        return $this->futuresPositions($symbol, $recvWindow, 'v3');
    }

    /**
     * futuresPosition gets the position information for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Position-Information-V2
     *
     * $position = $api->futuresPosition("BNBBTC");
     *
     * @property int $weight 5
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     * @param string $api_version (optional) API version, "v2" or "v3" (default is v3)
     *
     * @return array with error message or the position details
     * @throws \Exception
     */
    public function futuresPosition(string $symbol, $recvWindow = null, string $api_version = 'v3')
    {
        return $this->futuresPositions($symbol, $recvWindow, $api_version);
    }

    /**
     * futuresPositionV2
     * @see futuresPosition
     */
    public function futuresPositionV2(string $symbol, int $recvWindow = null)
    {
        return $this->futuresPositionsV2($symbol, $recvWindow, 'v2');
    }

    /**
     * futuresPositionV3
     * @see futuresPosition
     */
    public function futuresPositionV3(string $symbol, int $recvWindow = null)
    {
        return $this->futuresPositionsV3($symbol, $recvWindow, 'v3');
    }

    /**
     * futuresAdlQuantile gets the ADL quantile estimation for a symbol or all symbols
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Position-ADL-Quantile-Estimation
     *
     * $response = $api->futuresAdlQuantile("BNBBTC");
     *
     * @property int $weight 5
     *
     * @param string $symbol (optional) market symbol (e.g. ETHUSDT)
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the ADL quantile details
     * @throws \Exception
     */
    public function futuresAdlQuantile($symbol = null, int $recvWindow = null)
    {
        $params = [
            'fapi' => true,
        ];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/adlQuantile", 'GET', $params, true);
    }

    /**
     * futuresPositionMarginChangeHistory gets the position margin change history for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Get-Position-Margin-Change-History
     *
     * $history = $api->futuresPositionMarginChangeHistory("BNBBTC");
     *
     * @property int $weight 1
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param int    $startTime (optional) timestamp in ms to get history from INCLUSIVE
     * @param int    $endTime (optional) timestamp in ms to get history until INCLUSIVE
     * @param int    $limit (optional) limit the amount of history (default 500)
     * @param string $addOrReduce (optional) "ADD" or "REDUCE" to filter the history
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     */
    public function futuresPositionMarginChangeHistory(string $symbol, $startTime = null, $endTime = null, $limit = null, $addOrReduce = null, int $recvWindow = null)
    {
        $params = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        if ($startTime) {
            $params['startTime'] = $startTime;
        }
        if ($endTime) {
            $params['endTime'] = $endTime;
        }
        if ($limit) {
            $params['limit'] = $limit;
        }
        if ($addOrReduce) {
            if (is_numeric($addOrReduce)) {
                $params['addOrReduce'] = $addOrReduce;
            } else if (is_string($addOrReduce)) {
                $addOrReduce = strtoupper($addOrReduce);
                if ($addOrReduce === 'ADD' || $addOrReduce === '1') {
                    $params['addOrReduce'] = 1;
                } else if ($addOrReduce === 'REDUCE' || $addOrReduce === '2') {
                    $params['addOrReduce'] = 2;
                } else {
                    throw new \Exception('futuresPositionMarginChangeHistory: addOrReduce must be "ADD" or "REDUCE" or 1 or 2');
                }
            } else {
                throw new \Exception('futuresPositionMarginChangeHistory: addOrReduce must be "ADD" or "REDUCE" or 1 or 2');
            }
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/positionMargin/history", 'GET', $params, true);
    }

    /**
     * futuresBalances gets the balance information futures account
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Futures-Account-Balance-V2
     *
     * $balances = $api->futuresBalances();
     *
     * @property int $weight 5
     *
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     * @param string $api_version (optional) API version, "v2" or "v3" (default is v3)
     *
     * @return array with error message or the balance details
     * @throws \Exception
     */
    public function futuresBalances($recvWindow = null, string $api_version = 'v3')
    {
        if ($api_version !== 'v2' && $api_version !== 'v3') {
            throw new \Exception('futuresBalances: api_version must be either v2 or v3');
        }
        return $this->balances('futures', $recvWindow, 'v3');
    }

    /**
     * futuresBalancesV2
     * see futuresBalances
     */
    public function futuresBalancesV2(int $recvWindow = null)
    {
        return $this->futuresBalances($recvWindow, 'v2');
    }

    /**
     * futuresBalancesV3
     * see futuresBalances
     */
    public function futuresBalancesV3(int $recvWindow = null)
    {
        return $this->futuresBalances($recvWindow, 'v3');
    }

    /**
     * futuresAccount get all information about the api account
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Account-Information-V2
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Account-Information-V3
     *
     * $account = $api->futuresAccount();
     *
     * @property int $weight 5
     *
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     * @param string $api_version (optional) API version, "v2" or "v3" (default is v3)
     *
     * @return array with error message or array of all the account information
     * @throws \Exception
     */
    public function futuresAccount($recvWindow = null, string $api_version = 'v3')
    {
        if ($api_version !== 'v2' && $api_version !== 'v3') {
            throw new \Exception('futuresAccount: api_version must be either v2 or v3');
        }
        $params = [
            'fapi' => true,
        ];
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest($api_version . "/account", "GET", $params, true);
    }

    /**
     * futuresAccountV2
     * see futuresAccount
     */
    public function futuresAccountV2(int $recvWindow = null)
    {
        return $this->futuresAccount($recvWindow, 'v2');
    }

    /**
     * futuresAccountV3
     * see futuresAccount
     */
    public function futuresAccountV3(int $recvWindow = null)
    {
        return $this->futuresAccount($recvWindow, 'v3');
    }

    /**
     * futuresTradeFee gets the trade fee information for a symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/User-Commission-Rate
     *
     * $tradeFee = $api->futuresTradeFee("BNBBTC");
     *
     * @property int $weight 20
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the trade fee details
     * @throws \Exception
     */
    public function futuresTradeFee(string $symbol, int $recvWindow = null)
    {
        $params = [
            'symbol' => $symbol,
            'fapi' => true,
        ];
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/commissionRate", 'GET', $params, true);
    }

    /**
     * futuresAccountConfig gets the account configuration information
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Account-Config
     *
     * $accountConfig = $api->futuresAccountConfig();
     *
     * @property int $weight 5
     *
     * @param int $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the account configuration details
     * @throws \Exception
     */
    public function futuresAccountConfig(int $recvWindow = null)
    {
        $params = [
            'fapi' => true,
        ];
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/accountConfig", 'GET', $params, true);
    }

    /**
     * futuresMarginModes gets the margin mode for all symbols or specific symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Symbol-Config
     *
     * $marginMode = $api->futuresMarginModes();
     * $marginModes = $api->futuresMarginModes("BNBBTC");
     *
     * @property int $weight 5
     *
     * @param string $symbol (optional) market symbol (e.g. ETHUSDT)
     * @param int $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the margin mode details
     * @throws \Exception
     */
    public function futuresMarginModes($symbol = null, int $recvWindow = null)
    {
        $params = [
            'fapi' => true,
        ];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/symbolConfig", 'GET', $params, true);
    }

    /**
     * futuresOrderRateLimit gets the user rate limit
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Query-Rate-Limit
     *
     * $rateLimit = $api->futuresOrderRateLimit();
     *
     * @property int $weight 1
     *
     * @param int $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the rate limit details
     * @throws \Exception
     */
    public function futuresOrderRateLimit(int $recvWindow = null)
    {
        $params = [
            'fapi' => true,
        ];
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/rateLimit/order", 'GET', $params, true);
    }

    /**
     * futuresLeverages gets the leverage information for a symbol or all symbols
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Notional-and-Leverage-Brackets
     *
     * $leverage = $api->futuresLeverages("BNBBTC");
     * $leverages = $api->futuresLeverages();
     *
     * @property int $weight 1
     *
     * @param string $symbol (optional) market symbol (e.g. ETHUSDT)
     * @param int $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the leverage details
     * @throws \Exception
     */
    public function futuresLeverages($symbol = null, int $recvWindow = null)
    {
        $params = [
            'fapi' => true,
        ];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/leverageBracket", 'GET', $params, true);
    }

    /**
     * futuresLedger fetch the history of changes, actions done by the user or operations that altered the balance of the user
     * possible values for incomeType:
     * - TRANSFER
     * - TRANSFER
     * - WELCOME_BONUS
     * - REALIZED_PNL
     * - FUNDING_FEE
     * - COMMISSION
     * - INSURANCE_CLEAR
     * - REFERRAL_KICKBACK
     * - COMMISSION_REBATE
     * - API_REBATE
     * - CONTEST_REWARD
     * - CROSS_COLLATERAL_TRANSFER
     * - OPTIONS_PREMIUM_FEE
     * - OPTIONS_SETTLE_PROFIT
     * - INTERNAL_TRANSFER
     * - AUTO_EXCHANGE
     * - DELIVERED_SETTELMENT
     * - COIN_SWAP_DEPOSIT
     * - COIN_SWAP_WITHDRAW
     * - POSITION_LIMIT_INCREASE_FEE
     * - STRATEGY_UMFUTURES_TRANSFER
     * - FEE_RETURN
     * - BFUSD_REWARD
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Get-Income-History
     *
     * $income = $api->futuresLedger("BNBBTC");
     * $income = $api->futuresLedger("BNBBTC", "FUNDING_FEE");
     *
     * @property int $weight 30
     *
     * @param string $symbol (optional) market symbol (e.g. ETHUSDT)
     * @param string $incomeType (optional) income type to filter the response
     * @param int    $startTime (optional) timestamp in ms to get income from INCLUSIVE
     * @param int    $endTime (optional) timestamp in ms to get income until INCLUSIVE
     * @param int    $limit (optional) limit the amount of income (default 100, max 1000)
     * @param int    $page (optional) number of page to get
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the income details
     * @throws \Exception
     */
    public function futuresLedger($symbol = null, $incomeType = null, $startTime = null, $endTime = null, $limit = null, $page = null, int $recvWindow = null)
    {
        $params = [
            'fapi' => true,
        ];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        if ($incomeType) {
            $params['incomeType'] = $incomeType;
        }
        if ($startTime) {
            $params['startTime'] = $startTime;
        }
        if ($endTime) {
            $params['endTime'] = $endTime;
        }
        if ($limit) {
            $params['limit'] = $limit;
        }
        if ($page) {
            $params['page'] = $page;
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/income", 'GET', $params, true);
    }

    /**
     * futuresTradingStatus get the futures trading quantitative rules indicators
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Futures-Trading-Quantitative-Rules-Indicators
     *
     * $tradingStatus = $api->futuresTradingStatus();
     *
     * @property int $weight 10
     * weigth is 1 if symbol is provided
     *
     * @param string $symbol (optional) market symbol (e.g. ETHUSDT)
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the trading status details
     * @throws \Exception
     */
    public function futuresTradingStatus($symbol = null, int $recvWindow = null)
    {
        $params = [
            'fapi' => true,
        ];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/apiTradingStatus", 'GET', $params, true);
    }

    /**
     * futuresDownloadId
     * helper for other metods for getting download id
     */
    protected function futuresDownloadId($startTime, $endTime, $recvWindow = null, string $url = '')
    {
        $params = [
            'fapi' => true,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ];
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest($url, 'GET', $params, true);
    }

    /**
     * futuresDownloadLinkByDownloadId
     * helper for other metods for getting download link by download id
     */
    protected function futuresDownloadLinkByDownloadId(string $downloadId, $recvWindow = null, string $url = '')
    {
        $params = [
            'fapi' => true,
            'downloadId' => $downloadId,
        ];
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest($url, 'GET', $params, true);
    }

    /**
     * futuresDownloadIdForTransactions gets the download id for transactions
     * request limitation is 5 times per month, shared by front end download page and rest api
     * the time between startTime and endTime can not be longer than 1 year
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Get-Download-Id-For-Futures-Transaction-History
     *
     * $downloadId = $api->futuresDownloadIdForTransactions(1744105700000, 1744105722122);
     *
     * @property int $weight 1000
     *
     * @param int $startTime (optional) timestamp in ms to get transactions from INCLUSIVE
     * @param int $endTime (optional) timestamp in ms to get transactions until INCLUSIVE
     * @param int $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the response
     * @throws \Exception
     */
    public function futuresDownloadIdForTransactions(int $startTime, int $endTime, int $recvWindow = null)
    {
        return $this->futuresDownloadId($startTime, $endTime, $recvWindow, "v1/income/asyn");
    }

    /**
     * futuresDownloadTransactionsByDownloadId get futures transaction history download link by Id
     * download link expiration: 24h
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Get-Futures-Transaction-History-Download-Link-by-Id
     *
     * $downloadLink = $api->futuresDownloadTransactionsByDownloadId("downloadId");
     *
     * @property int $weight 10
     *
     * @param string $downloadId (mandatory) download id
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the download link
     * @throws \Exception
     */
    public function futuresDownloadTransactionsByDownloadId(string $downloadId, int $recvWindow = null)
    {
        return $this->futuresDownloadLinkByDownloadId($downloadId, $recvWindow, "v1/income/asyn/id");
    }

    /**
     * futuresDownloadIdForOrders gets the download id for orders
     * request limitation is 10 times per month, shared by front end download page and rest api
     * the time between startTime and endTime can not be longer than 1 year
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Get-Download-Id-For-Futures-Order-History
     *
     * $downloadId = $api->futuresDownloadIdForOrders(1744105700000, 1744105722122);
     *
     * @property int $weight 1000
     *
     * @param int $startTime (optional) timestamp in ms to get orders from INCLUSIVE
     * @param int $endTime (optional) timestamp in ms to get orders until INCLUSIVE
     * @param int $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the response
     * @throws \Exception
     */
    public function futuresDownloadIdForOrders(int $startTime, int $endTime, int $recvWindow = null)
    {
        return $this->futuresDownloadId($startTime, $endTime, $recvWindow, "v1/order/asyn");
    }

    /**
     * futuresDownloadOrdersByDownloadId get futures orders history download link by Id
     * download link expiration: 24h
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Get-Futures-Order-History-Download-Link-by-Id
     *
     * $downloadLink = $api->futuresDownloadOrdersByDownloadId("downloadId");
     *
     * @property int $weight 10
     *
     * @param string $downloadId (mandatory) download id
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the download link
     * @throws \Exception
     */
    public function futuresDownloadOrdersByDownloadId(string $downloadId, int $recvWindow = null)
    {
        return $this->futuresDownloadLinkByDownloadId($downloadId, $recvWindow, "v1/order/asyn/id");
    }

    /**
     * futuresDownloadIdForTrades gets the download id for trades
     * request limitation is 5 times per month, shared by front end download page and rest api
     * the time between startTime and endTime can not be longer than 1 year
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Get-Download-Id-For-Futures-Trade-History
     *
     * $downloadId = $api->futuresDownloadIdForTrades(1744105700000, 1744105722122);
     *
     * @property int $weight 1000
     *
     * @param int $startTime (optional) timestamp in ms to get trades from INCLUSIVE
     * @param int $endTime (optional) timestamp in ms to get trades until INCLUSIVE
     * @param int $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the response
     * @throws \Exception
     */
    public function futuresDownloadIdForTrades(int $startTime, int $endTime, int $recvWindow = null)
    {
        return $this->futuresDownloadId($startTime, $endTime, $recvWindow, "v1/trade/asyn");
    }

    /**
     * futuresDownloadTradesByDownloadId get futures trades history download link by Id
     * download link expiration: 24h
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Get-Futures-Trade-Download-Link-by-Id
     *
     * $downloadLink = $api->futuresDownloadTradesByDownloadId("downloadId");
     *
     * @property int $weight 10
     *
     * @param string $downloadId (mandatory) download id
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array with error message or the download link
     * @throws \Exception
     */
    public function futuresDownloadTradesByDownloadId(string $downloadId, int $recvWindow = null)
    {
        return $this->futuresDownloadLinkByDownloadId($downloadId, $recvWindow, "v1/trade/asyn/id");
    }

    /**
     * futuresFeeBurn change user's BNB Fee Discount (Fee Discount On or Fee Discount Off ) on EVERY symbol
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Toggle-BNB-Burn-On-Futures-Trade
     *
     * $response = $api->futuresFeeBurn(true);
     *
     * @property int $weight 1
     *
     * @param bool $flag (mandatory) true for BNB Fee Discount On, false for BNB Fee Discount Off
     * @param int  $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresFeeBurn(bool $flag, int $recvWindow = null)
    {
        $params = [
            'fapi' => true,
            'feeBurn' => $flag ? 'true' : 'false',
        ];
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/feeBurn", 'POST', $params, true);
    }

    /**
     * futuresFeeBurnStatus gets the BNB Fee Discount status
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/account/rest-api/Get-BNB-Burn-Status
     *
     * $response = $api->futuresFeeBurnStatus();
     *
     * @property int $weight 30
     *
     * @param int $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresFeeBurnStatus(int $recvWindow = null)
    {
        $params = [
            'fapi' => true,
        ];
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/feeBurn", 'GET', $params, true);
    }

    /**
     * convertExchangeInfo get all convertible token pairs and the tokens’ respective upper/lower limits
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/convert
     *
     * $converInfo = $api->convertExchangeInfo();
     * $converInfo = $api->convertExchangeInfo("ETH", "DOGE");
     *
     * @property int $weight 20
     *
     * @param string $fromAsset (optional) the asset to convert from
     * @param string $toAsset (optional) the asset to convert to
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function convertExchangeInfo($fromAsset = null, $toAsset = null)
    {
        $params = [
            'fapi' => true,
        ];
        if ($fromAsset) {
            $params['fromAsset'] = $fromAsset;
        }
        if ($toAsset) {
            $params['toAsset'] = $toAsset;
        }
        return $this->httpRequest("v1/convert/exchangeInfo", 'GET', $params);
    }

    /**
     * convertSend send a convert request
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/convert/Send-quote-request
     *
     * $convertRequest = $api->convertSend("ETH", "DOGE", 0.1);
     *
     * @property int $weight 50
     *
     * @param string $fromAsset (mandatory) the asset to convert from
     * @param string $toAsset (mandatory) the asset to convert to
     * @param string $fromAmount (optional) mandatory if $toAmount is not set
     * @param string $toAmount (optional) mandatory if $fromAmount is not set
     * @param string $validTime (optional) deafault "10s"
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function convertSend(string $fromAsset, string $toAsset, $fromAmount = null, $toAmount = null, $validTime = null, int $recvWindow = null)
    {
        $params = [
            'fapi' => true,
            'fromAsset' => $fromAsset,
            'toAsset' => $toAsset,
        ];
        if ($fromAmount) {
            $params['fromAmount'] = $fromAmount;
        } else if ($toAmount) {
            $params['toAmount'] = $toAmount;
        } else {
            throw new \Exception('convertSendRequest: fromAmount or toAmount must be set');
        }
        if ($validTime) {
            $params['validTime'] = $validTime;
        }
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest("v1/convert/getQuote", 'POST', $params, true);
    }

    /**
     * convertAccept accept the offered quote by quote ID
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/convert/Accept-Quote
     *
     * $convertAccept = $api->convertAccept("quoteId");
     *
     * @property int $weight 200
     *
     * @param string $quoteId (mandatory) the quote ID to accept
     * @param int    $recvWindow (optional) the time in milliseconds to wait for a response
     * @param array  $params (optional) additional parameters
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function convertAccept(string $quoteId, int $recvWindow = null, array $params = [])
    {
        $request = [
            'quoteId' => $quoteId,
        ];
        return $this->fapiRequest("v1/cconvert/acceptQuote", 'POST', array_merge($request, $params), true, $recvWindow);
    }

    /**
     * fapiRequest helper for creating a fapi httpRequest
     */
    protected function fapiRequest(string $url, string $method, array $params = [], $signed = false, int $recvWindow = null)
    {
        $params['fapi'] = true;
        if ($recvWindow) {
            $params['recvWindow'] = $recvWindow;
        }
        return $this->httpRequest($url, $method, $params, $signed);
    }

    /**
     * convertStatus get the status of a convert request by orderId or quoteId
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/convert/Order-Status
     *
     * $status = $api->convertStatus("orderId");
     *
     * @property int $weight 50
     *
     * @param string $orderId (optional) the order ID to get the status of (mandatory if $quoteId is not set)
     * @param string $quoteId (optional) the quote ID to get the status of (mandatory if $orderId is not set)
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function convertStatus($orderId = null, $quoteId = null)
    {
        $params = [
            'fapi' => true,
        ];
        if ($orderId) {
            $params['orderId'] = $orderId;
        } else if ($quoteId) {
            $params['quoteId'] = $quoteId;
        } else {
            throw new \Exception('convertStatus: orderId or quoteId must be set');
        }
        return $this->httpRequest("v1/convert/orderStatus", 'GET', $params, true);
    }
}