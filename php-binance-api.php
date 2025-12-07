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
    protected $recvWindow = null; // /< The amount of time in milliseconds to wait for a response from the server for endpoints that accept a recvWindow parameter

    // /< value of available onOrder assets

    protected $exchangeInfo = null;
    protected $futuresExchangeInfo = null;
    protected $lastRequest = [];

    protected $xMbxUsedWeight = 0;
    protected $xMbxUsedWeight1m = 0;

    public $headers = [];

    private $SPOT_ORDER_PREFIX     = "x-B3AUXNYV";
	private $CONTRACT_ORDER_PREFIX = "x-ftGmvgAN";

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
    protected function setupApiConfigFromFile(?string $file = null)
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
    protected function setupCurlOptsFromFile(?string $file = null)
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
    protected function setupProxyConfigFromFile(?string $file = null)
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
     * @param $params array addtional options for order type
     * @return array with error message or the order details
     */
    public function buy(string $symbol, $quantity, $price, string $type = "LIMIT", array $params = [])
    {
        return $this->order("BUY", $symbol, $quantity, $price, $type, $params);
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
     * @param $params array config
     * @return array with error message or empty or the order details
     */
    public function buyTest(string $symbol, $quantity, $price, string $type = "LIMIT", array $params = [])
    {
        return $this->order("BUY", $symbol, $quantity, $price, $type, $params, true);
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
     * @param $params array addtional options for order type
     * @return array with error message or the order details
     */
    public function sell(string $symbol, $quantity, $price, string $type = "LIMIT", array $params = [])
    {
        return $this->order("SELL", $symbol, $quantity, $price, $type, $params);
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
     * @param $params array config
     * @return array with error message or empty or the order details
     */
    public function sellTest(string $symbol, $quantity, $price, string $type = "LIMIT", array $params = [])
    {
        return $this->order("SELL", $symbol, $quantity, $price, $type, $params, true);
    }

    /**
     * marketQuoteBuy attempts to create a currency order at given market price
     *
     * $quantity = 1;
     * $order = $api->marketQuoteBuy("BNBBTC", $quantity);
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity of the quote to use
     * @param $params array additional options for order type
     * @return array with error message or the order details
     */
    public function marketQuoteBuy(string $symbol, $quantity, array $params = [])
    {
        $params['isQuoteOrder'] = true;

        return $this->order("BUY", $symbol, $quantity, 0, "MARKET", $params);
    }

    /**
     * marketQuoteBuyTest attempts to create a TEST currency order at given market price
     *
     * @see marketBuy()
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity of the quote to use
     * @param $params array additional options for order type
     * @return array with error message or the order details
     */
    public function marketQuoteBuyTest(string $symbol, $quantity, array $params = [])
    {
        $params['isQuoteOrder'] = true;

        return $this->order("BUY", $symbol, $quantity, 0, "MARKET", $params, true);
    }

    /**
     * marketBuy attempts to create a currency order at given market price
     *
     * $quantity = 1;
     * $order = $api->marketBuy("BNBBTC", $quantity);
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity required
     * @param $params array addtional options for order type
     * @return array with error message or the order details
     */
    public function marketBuy(string $symbol, $quantity, array $params = [])
    {
        return $this->order("BUY", $symbol, $quantity, 0, "MARKET", $params);
    }

    /**
     * marketBuyTest attempts to create a TEST currency order at given market price
     *
     * @see marketBuy()
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity required
     * @param $params array addtional options for order type
     * @return array with error message or the order details
     */
    public function marketBuyTest(string $symbol, $quantity, array $params = [])
    {
        return $this->order("BUY", $symbol, $quantity, 0, "MARKET", $params, true);
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
     * @param $params array additional options for order type
     * @return array with error message or the order details
     */
    public function marketQuoteSell(string $symbol, $quantity, array $params = [])
    {
        $params['isQuoteOrder'] = true;
        $c = $this->numberOfDecimals($this->exchangeInfo()['symbols'][$symbol]['filters'][1]['minQty']);
        $quantity = $this->floorDecimal($quantity, $c);

        return $this->order("SELL", $symbol, $quantity, 0, "MARKET", $params);
    }

    /**
     * marketQuoteSellTest attempts to create a TEST currency order at given market price
     *
     * @see marketSellTest()
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity of the quote you want to obtain
     * @param $params array additional options for order type
     * @return array with error message or the order details
     */
    public function marketQuoteSellTest(string $symbol, $quantity, array $params = [])
    {
        $params['isQuoteOrder'] = true;

        return $this->order("SELL", $symbol, $quantity, 0, "MARKET", $params, true);
    }

    /**
     * marketSell attempts to create a currency order at given market price
     *
     * $quantity = 1;
     * $order = $api->marketSell("BNBBTC", $quantity);
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity required
     * @param $params array addtional options for order type
     * @return array with error message or the order details
     */
    public function marketSell(string $symbol, $quantity, array $params = [])
    {
        $c = $this->numberOfDecimals($this->exchangeInfo()['symbols'][$symbol]['filters'][1]['minQty']);
        $quantity = $this->floorDecimal($quantity, $c);

        return $this->order("SELL", $symbol, $quantity, 0, "MARKET", $params);
    }

    /**
     * marketSellTest attempts to create a TEST currency order at given market price
     *
     * @see marketSellTest()
     *
     * @param $symbol string the currency symbol
     * @param $quantity string the quantity required
     * @param $params array addtional options for order type
     * @return array with error message or the order details
     */
    public function marketSellTest(string $symbol, $quantity, array $params = [])
    {
        return $this->order("SELL", $symbol, $quantity, 0, "MARKET", $params, true);
    }

    /**
     * cancel attempts to cancel a currency order
     * either orderid or origClientOrderId is mandatory
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/trading-endpoints#cancel-order-trade
     *
     * $orderid = "123456789";
     * $order = $api->cancel("BNBBTC", $orderid);
     *
     * @param string $symbol (mandatory) the currency symbol
     * @param string $orderid (optional) the orderid to fetch (mandatory if $params['origClientOrderId'] is not provided)
     * @param array $params an array of additional parameters that the API endpoint allows
     * - @param string $params['origClientOrderId'] optional the original client order id (mandatory if $orderid is not provided)
     * - @param string $params['cancelRestrictions'] optional ONLY_NEW (cancel will succeed if the order status is NEW) or ONLY_PARTIALLY_FILLED (cancel will succeed if the order status is PARTIALLY_FILLED)
     * @return array with error message or the order details
     * @throws \Exception
     */
    public function cancel(string $symbol, ?string $orderid = null, $params = [])
    {
        $request = [
            "symbol" => $symbol,
        ];
        if (!is_null($orderid)) {
            $request["orderId"] = $orderid;
        } else if (!is_set($params['origClientOrderId'])) {
            throw new Exception("Either orderId or origClientOrderId must be provided");
        }
        return $this->apiRequest("v3/order", "DELETE", array_merge($request, $params), true);
    }

    /**
     * orderStatus attempts to get orders status
     * either orderid or origClientOrderId is mandatory
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/trading-endpoints#query-order-user_data
     *
     * $orderid = "123456789";
     * $order = $api->orderStatus("BNBBTC", $orderid);
     *
     * @param string $symbol (mandatory) the currency symbol
     * @param string $orderid (optional) the orderid to fetch (mandatory if $params['origClientOrderId'] is not provided)
     * @param array $params an array of additional parameters that the API endpoint allows
     * - @param string $params['origClientOrderId'] optional the original client order id (mandatory if $orderid is not provided)
     * @return array with error message or the order details
     * @throws \Exception
     */
    public function orderStatus(string $symbol, ?string $orderid = null, array $params = [])
    {
        $request = [
            "symbol" => $symbol,
        ];
        if (!is_null($orderid)) {
            $request["orderId"] = $orderid;
        } else if (!is_set($params['origClientOrderId'])) {
            throw new Exception("Either orderId or origClientOrderId must be provided");
        }
        return $this->apiRequest("v3/order", "GET", array_merge($request, $params), true);
    }

    /**
     * openOrders attempts to get open orders for all currencies or a specific currency
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/trading-endpoints#current-open-orders-user_data
     *
     * $allOpenOrders = $api->openOrders();
     * $allBNBOrders = $api->openOrders( "BNBBTC" );
     *
     * @param string $symbol (optional) the market symbol
     * @return array with error message or the order details
     * @throws \Exception
     */
    public function openOrders(?string $symbol = null, array $params = [])
    {
        $request = [];
        if (!is_null($symbol)) {
            $request = [
                "symbol" => $symbol,
            ];
        }
        return $this->apiRequest("v3/openOrders", "GET", array_merge($request, $params), true);
    }

    /**
     * cancelOpenOrders - Cancel all open orders method
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/trading-endpoints#cancel-all-open-orders-on-a-symbol-trade
     *
     * $api->cancelOpenOrders( "BNBBTC" );
     * @param string $symbol (mandatory) the merket symbol
     * @param array $params (optional) an array of additional parameters that the API endpoint allows
     * @return array with error message or the order details
     * @throws \Exception
     */
    public function cancelOpenOrders(string $symbol, array $params = [])
    {
        $request = [
            "symbol" => $symbol,
        ];
        return $this->apiRequest("v3/openOrders", "DELETE", array_merge($request, $params), true);
    }

    /**
     * orders attempts to get the orders for all or a specific currency
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/trading-endpoints#all-orders-user_data
     *
     * $allBNBOrders = $api->orders( "BNBBTC" );
     *
     * @param string $symbol (mandatory) the currency symbol
     * @param int $limit (optional) the amount of orders returned (default 500, max 1000)
     * @param string $fromOrderId (optional) return the orders from this order onwards
     * @param array $params (optional) an array of additional parameters that the API endpoint allows
     * @return array with error message or array of orderDetails array
     * @throws \Exception
     */
    public function orders(string $symbol, ?int $limit = null, int $fromOrderId = null, array $params = [])
    {
        $request = [
            "symbol" => $symbol,
        ];
        if (!is_null($limit)) {
            $request["limit"] = $limit;
        }
        if (!is_null($fromOrderId)) {
            $request["orderId"] = $fromOrderId;
        }
        return $this->apiRequest("v3/allOrders", "GET", array_merge($request, $params), true);
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
    public function history(string $symbol, int $limit = 500, int $fromTradeId = -1, ?int $startTime = null, ?int $endTime = null, array $params = [])
    {
        $request = [
            "symbol" => $symbol,
            "limit" => $limit,
        ];
        if ($fromTradeId > 0) {
            $request["fromId"] = $fromTradeId;
        }
        if (isset($startTime)) {
            $request["startTime"] = $startTime;
        }
        if (isset($endTime)) {
            $request["endTime"] = $endTime;
        }

        return $this->apiRequest("v3/myTrades", "GET", array_merge($request, $params), true);
    }

    /**
     * myTrades
     * another name for history
     * @see history()
     *
     * @return array with error message or array of orderDetails array
     * @throws \Exception
     */
    public function myTrades(string $symbol, int $limit = 500, int $fromTradeId = -1, ?int $startTime = null, ?int $endTime = null, array $params = [])
    {
        return $this->history($symbol, $limit, $fromTradeId, $startTime, $endTime, $params);
    }

    /**
     * useServerTime adds the 'useServerTime'=>true to the API request to avoid time errors
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/general-endpoints
     *
     * $api->useServerTime();
     *
     * @return null
     * @throws \Exception
     */
    public function useServerTime(array $params = [])
    {
        $request = $this->apiRequest("v3/time", "GET", $params);
        if (isset($request['serverTime'])) {
            $this->info['timeOffset'] = $request['serverTime'] - (microtime(true) * 1000);
        }
    }

    /**
     * time Gets the server time
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/general-endpoints
     *
     * $time = $api->time();
     *
     * @return array with error message or array with server time key
     * @throws \Exception
     */
    public function time(array $params = [])
    {
        return $this->apiRequest("v3/time", "GET", $params);
    }

    /**
     * exchangeInfo -  Gets the complete exchange info, including limits, currency options etc.
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/general-endpoints
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
    public function exchangeInfo($symbols = null, array $params = [])
    {
        if (!$this->exchangeInfo) {
            $arr = array();
            if ($symbols) {
                if (gettype($symbols) == "string") {
                    $request["symbol"] = $symbols;
                    $arr = $this->apiRequest("v3/exchangeInfo", "GET", array_merge($request, $params));
                }
                if (gettype($symbols) == "array")  {
                    $arr = $this->apiRequest("v3/exchangeInfo?symbols=" . '["' . implode('","', $symbols) . '"]', "GET", $params);
                }
            } else {
                $arr = $this->apiRequest("v3/exchangeInfo", "GET", $params);
            }
            if ((is_array($arr) === false) || empty($arr)) {
                echo "Error: unable to fetch spot exchange info" . PHP_EOL;
                $arr = array();
                $arr['symbols'] = array();
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
    public function assetDetail($asset = '', array $params = [])
    {
        $request = array();
        if ($asset != '' && gettype($asset) == 'string')
            $request['asset'] = $asset;
        $arr = $this->sapiRequest("v1/asset/assetDetail", 'GET', array_merge($request, $params), true);
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
    public function dustLog($startTime = NULL, $endTime = NULL, array $params = [])
    {
        $request = array();
        if (!empty($startTime) && !empty($endTime)) {
            $request['startTime'] = $startTime;
            $request['endTime'] = $endTime;
        }

        return $this->sapiRequest("v1/asset/dribblet", 'GET', array_merge($request, $params), true);
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
    public function dustTransfer($assets, array $params = [])
    {
        $request = [
            'assets' => $assets,
        ];

        return $this->sapiRequest("v1/asset/dust", 'POST', array_merge($request, $params), true);
    }

    /**
     * Fetch current(daily) trade fee of symbol, values in percentage.
     * for more info visit binance official api document
     *
     * $symbol = "BNBBTC"; or any other symbol or even a set of symbols in an array
     * @param string $symbol
     * @return mixed
     */
    public function tradeFee(string $symbol, array $params = [])
    {
        $request = [
            "symbol" => $symbol,
        ];
        return $this->sapiRequest("v1/asset/tradeFee", 'GET', array_merge($request, $params), true);
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
    public function commissionFee($symbol = '', array $params = [])
    {
        $request = array();
        if ($symbol != '' && gettype($symbol) == 'string')
            $request['symbol'] = $symbol;

        return $this->sapiRequest("v1/asset/tradeFee", 'GET', array_merge($request, $params), true);
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
    public function withdraw(string $asset, string $address, $amount, $addressTag = null, $addressName = "", bool $transactionFeeFlag = false, $network = null, $orderId = null, array $params = [])
    {
        $request = [
            "coin" => $asset,
            "address" => $address,
            "amount" => $amount,
            "sapi" => true,
        ];

        if (is_null($addressName) === false && empty($addressName) === false) {
            $request['name'] = str_replace(' ', '%20', $addressName);
        }
        if (is_null($addressTag) === false && empty($addressTag) === false) {
            $request['addressTag'] = $addressTag;
        }
        if ($transactionFeeFlag) $request['transactionFeeFlag'] = true;

        if (is_null($network) === false && empty($network) === false) {
            $request['network'] = $network;
        }
        if (is_null($orderId) === false && empty($orderId) === false) {
            $request['withdrawOrderId'] = $orderId;
        }
        return $this->sapiRequest("v1/capital/withdraw/apply", "POST", array_merge($request, $params), true);
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
    public function depositAddress(string $asset, $network = null, array $params = [])
    {
        $request = [
            "coin" => $asset,
        ];
        if (is_null($network) === false && empty($network) === false) {
            $request['network'] = $network;
        }

        $return = $this->sapiRequest("v1/capital/deposit/address", "GET", array_merge($request, $params), true);

        // Adding for backwards compatibility with wapi
        if (is_array($return) && !empty($return)) {
            $return['asset'] = $return['coin'];
            $return['addressTag'] = $return['tag'];
            if (!empty($return['address'])) {
                $return['success'] = 1;
            } else {
                $return['success'] = 0;
            }
        } else {
            echo "Error: no deposit address found" . PHP_EOL;
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
    public function depositHistory(?string $asset = null, array $params = [])
    {
        $request = array();
        if (is_null($asset) === false) {
            $request['coin'] = $asset;
        }
        $return = $this->sapiRequest("v1/capital/deposit/hisrec", "GET", array_merge($request, $params), true);

        // Adding for backwards compatibility with wapi
        if (is_array($return) && !empty($return)) {
            foreach ($return as $key=>$item) {
                $return[$key]['asset'] = $item['coin'];
            }
        } else {
            echo "Error: no deposit history found" . PHP_EOL;
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
    public function withdrawHistory(?string $asset = null, array $params = [])
    {
        $request = array();
        if (is_null($asset) === false) {
            $request['coin'] = $asset;
        }
        // Wrapping in array for backwards compatibility with wapi
        $return = array(
            'withdrawList' => $this->sapiRequest("v1/capital/withdraw/history", "GET", array_merge($request, $params), true)
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
    public function withdrawFee(string $asset, array $params = [])
    {
        $return = $this->assetDetail('', $params);

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
     * @param array  $params   (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function transfer(string $type, string $asset, string $amount, $fromSymbol = null, $toSymbol = null, array $params = [])
    {
        $request = [
            'type' => $type,
            'asset' => $asset,
            'amount' => $amount,
        ];
        // todo: check this method with real account
        if ($fromSymbol) {
            $request['fromSymbol'] = $fromSymbol;
        }
        if ($toSymbol) {
            $request['toSymbol'] = $toSymbol;
        }


        return $this->sapiRequest("v1/asset/transfer", 'POST', array_merge($request, $params), true);
    }

    /**
     * transfersHistory - get the transfer history between accounts
     *
     * @link https://developers.binance.com/docs/wallet/asset/query-user-universal-transfer
     *
     * @property int $weight 1
     *
     * @param string $type (mandatory) type of transfer, e.g. MAIN_MARGIN (@see transfer())
     * @param string $startTime (optional) start time in milliseconds
     * @param string $endTime (optional) end time in milliseconds
     * @param int    $limit (optional) the number of records to return (default 10, max 100)
     * @param int    $current (optional) default 1
     * @param string $fromSymbol (optional) must be sent when type are ISOLATEDMARGIN_MARGIN and ISOLATEDMARGIN_ISOLATEDMARGIN
     * @param string $toSymbol (optional) must be sent when type are MARGIN_ISOLATEDMARGIN and ISOLATEDMARGIN_ISOLATEDMARGIN
     * @param array  $params   (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function transfersHistory(string $type, $startTime = null, $endTime = null, $limit = null, $current = null, $fromSymbol = null, $toSymbol = null, array $params = [])
    {
        $request = [
            'type' => $type,
        ];
        // todo: check this method with real account
        if ($startTime) {
            $request['startTime'] = $startTime;
        }
        if ($endTime) {
            $request['endTime'] = $endTime;
        }
        if ($limit) {
            $request['size'] = $limit;
        }
        if ($current) {
            $request['current'] = $current;
        }
        if ($fromSymbol) {
            $request['fromSymbol'] = $fromSymbol;
        }
        if ($toSymbol) {
            $request['toSymbol'] = $toSymbol;
        }


        return $this->sapiRequest("v1/asset/transfer", 'GET', array_merge($request, $params), true);
    }

    /**
     * prices get all the current prices
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/market-data-endpoints#symbol-price-ticker
     *
     * $ticker = $api->prices();
     *
     * @return array with error message or array of all the currencies prices
     * @throws \Exception
     */
    public function prices(array $params = [])
    {
        return $this->priceData($this->apiRequest("v3/ticker/price", "GET", $params));
    }

    /**
     * price get the latest price of a symbol
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/market-data-endpoints#symbol-price-ticker
     *
     * $price = $api->price( "ETHBTC" );
     *
     * @return array with error message or array with symbol price
     * @throws \Exception
     */
    public function price(string $symbol, array $params = [])
    {
        $request = [
            "symbol" => $symbol,
        ];
        $ticker = $this->apiRequest("v3/ticker/price", "GET", array_merge($request, $params));
        if (!isset($ticker['price'])) {
            echo "Error: unable to fetch price for $symbol" . PHP_EOL;
            return null;
        }
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
    public function bookPrices(array $params = [])
    {
        return $this->bookPriceData($this->apiRequest("v3/ticker/bookTicker", "GET", $params));
    }

    /**
     * account get all information about the api account
     *
     * $account = $api->account();
     *
     * @return array with error message or array of all the account information
     * @throws \Exception
     */
    public function account(array $params = [])
    {
        return $this->apiRequest("v3/account", "GET", $params, true);
    }

    /**
     * prevDay get 24hr ticker price change statistics for symbols
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/market-data-endpoints
     *
     * $prevDay = $api->prevDay("BNBBTC");
     *
     * @param string $symbol (optional) symbol to get the previous day change for
     * @param array $params (optional) an array of additional parameters that the API endpoint allows
     * - @param array  $params['symbols'] (optional) the symbols to get the previous day change for (e.g. ["BNBBTC","ETHBTC"])
     * @return array with error message or array of prevDay change
     * @throws \Exception
     */
    public function prevDay(?string $symbol = null, array $params = [])
    {
        $request = [];
        if (is_null($symbol) === false) {
            $request = [
                'symbol' => $symbol,
            ];
        }
        $symbols = [];
        if (isset($params['symbols'])) {
            $symbols = $params['symbols'];
            unset($params['symbols']);
        }
        if (!empty($symbols)) {
            $request['symbols'] = json_encode($symbols);
        }
        return $this->apiRequest("v3/ticker/24hr", "GET", array_merge($request, $params));
    }

    /**
     * tradingDay get 24hr ticker price change statistics for symbols
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/market-data-endpoints
     *
     * $tradingDay = $api->tradingDay("BNBBTC");
     * $tradingDay = $api->tradingDay(null, ["BNBBTC","ETHBTC"]);
     *
     * @param string $symbol (optional) symbol to get the info for - mandatory if symbols is not set
     * @param array  $symbols (optional) the symbols to get the info for (e.g. ["BNBBTC","ETHBTC"]) - mandatory if symbol is not set
     * @param array  $params (optional) an array of additional parameters that the API endpoint allows
     * - @param string $params['timeZone'] (optional) default is 0 (UTC) - time zone to use for the response
     * - @param string $params['type'] (optional) FULL or MINI - the format of the response (default is FULL)
     * @return array with error message or array of prevDay change
     * @throws \Exception
     */
    public function tradingDay(?string $symbol = null, ?array $symbols = null, array $params = [])
    {
        $request = [];
        if (!is_null($symbol) && is_string($symbol)) {
            $request['symbol'] = $symbol;
        } elseif (is_array($symbols) && !empty($symbols)) {
            $request['symbols'] = json_encode($symbols);
        } else {
            throw new \Exception("tradingDay(): Either symbol or symbols must be set");
        }
        return $this->apiRequest("v3/ticker/tradingDay", "GET", array_merge($request, $params));
    }

    /**
     * rollingWindowPriceChange get ticker price change statistics for symbols for a rolling window
     * - 1m, 2m... 59m for minutes
     * - 1h, 2h... 23h - for hours
     * - 1d.. 7d - for days
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/market-data-endpoints
     *
     * $priceChange = $api->rollingWindowPriceChange("BNBBTC");
     * $priceChange = $api->rollingWindowPriceChange(null, ["BNBBTC","ETHBTC"], '2d');
     *
     * @param string $symbol (optional) symbol to get the info for - mandatory if symbols is not set
     * @param array  $symbols (optional) the symbols to get the info for (e.g. ["BNBBTC","ETHBTC"]) - mandatory if symbol is not set
     * @param string $windowSize (optional) the size of the rolling window (e.g. 1m, 2m, 1h, 2h, 1d, 2d) - default is 1d
     * @param array  $params (optional) an array of additional parameters that the API endpoint allows
     * - @param string $params['type'] (optional) FULL or MINI - the format of the response (default is FULL)
     * @return array with error message or array of prevDay change
     * @throws \Exception
     */
    public function rollingWindowPriceChange(?string $symbol = null, array $symbols = null, string $windowSize = null, array $params = [])
    {
        $request = [];
        if (!is_null($symbol) && is_string($symbol)) {
            $request['symbol'] = $symbol;
        } elseif (is_array($symbols) && !empty($symbols)) {
            $request['symbols'] = json_encode($symbols);
        } else {
            throw new \Exception("rollingWindowPriceChange(): Either symbol or symbols must be set");
        }
        if (!is_null($windowSize)) {
            $request['windowSize'] = $windowSize;
        }
        return $this->apiRequest("v3/ticker", "GET", array_merge($request, $params));
    }

    /**
     * aggTrades get Market History / Aggregate Trades
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/market-data-endpoints
     *
     * $trades = $api->aggTrades("BNBBTC");
     *
     * @param $symbol string the symbol to get the trade information for
     * @return array with error message or array of market history
     * @throws \Exception
     */
    public function aggTrades(string $symbol, array $params = [])
    {
        $request = [
            "symbol" => $symbol,
        ];
        return $this->tradesData($this->apiRequest("v3/aggTrades", "GET", array_merge($request, $params)));
    }

    /**
     * historicalTrades - Get historical trades for a specific currency
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/market-data-endpoints
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
    public function historicalTrades(string $symbol, int $limit = 500, int $tradeId = -1, array $params = [])
    {
        $request = [
            "symbol" => $symbol,
            "limit" => $limit,
        ];
        if ($tradeId > 0) {
            $request["fromId"] = $tradeId;
        } else {
            // if there is no tradeId given, we can use v3/trades, weight is 1 and not 5
            return $this->apiRequest("v3/trades", "GET", array_merge($request, $params));
        }

        // The endpoint cannot handle extra parameters like 'timestamp' or 'signature',
        // but it needs the http header with the key so we need to construct it here
        $query = http_build_query(array_merge($request, $params), '', '&');
        return $this->apiRequest("v3/historicalTrades?$query");
    }

    /**
     * depth get Market depth
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/market-data-endpoints
     *
     * $depth = $api->depth("ETHBTC");
     *
     * @param $symbol string the symbol to get the depth information for
     * @param $limit int set limition for number of market depth data
     * @return array with error message or array of market depth
     * @throws \Exception
     */
    public function depth(string $symbol, int $limit = 100, array $params = [])
    {
        if (is_int($limit) === false) {
            $limit = 100;
        }

        if (isset($symbol) === false || is_string($symbol) === false) {
            // WPCS: XSS OK.
            echo "asset: expected bool false, " . gettype($symbol) . " given" . PHP_EOL;
        }
        $request = [
            "symbol" => $symbol,
            "limit" => $limit,
        ];
        $json = $this->apiRequest("v3/depth", "GET", array_merge($request, $params));
        if (is_array($json) === false) {
            echo "Error: unable to fetch depth" . PHP_EOL;
            $json = [];
        }
        if (empty($json)) {
            echo "Error: depth were empty" . PHP_EOL;
            return $json;
        }
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
     * @param array  $params      (optional) an array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the transfer to complete (not for spot)
     * @param string $api_version (optional) not for spot - the api version to use (default is v2)
     *
     * @return array with error message or array of balances
     * @throws \Exception
     */
    public function balances(string $market_type = 'spot', array $params = [], string $api_version = 'v2')
    {
        $is_spot = $market_type === 'spot';
        $request = [];
        if ($is_spot) {
            $url = "v3/account";
        } else {
            $request['fapi'] = true;
            if ($api_version === 'v2') {
                $url = "v2/balance";
            } else if ($api_version === 'v3') {
                $url = "v3/balance";
            } else {
                throw new \Exception("balances(): Invalid API version specified. Use 'v2' or 'v3'.");
            }
        }
        $response = $this->httpRequest($url, "GET", array_merge($request, $params), true);
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
    public function coins(array $params = [])
    {
        return $this->sapiRequest("v1/capital/config/getall", 'GET', $params, true);
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

    public function apiRequest($url, $method = "GET", $params = [], $signed = false)
    {
        return $this->httpRequest($url, $method, $params, $signed);
    }

    public function sapiRequest($url, $method = "GET", $params = [], $signed = false)
    {
        $params['sapi'] = true;
        return $this->httpRequest($url, $method, $params, $signed);
    }

    public function fapiRequest($url, $method = "GET", $params = [], $signed = false)
    {
        $params['fapi'] = true;
        return $this->httpRequest($url, $method, $params, $signed);
    }

    public function fapiDataRequest($url, $method = "GET", $params = [], $signed = false)
    {
        $params['fapiData'] = true;
        return $this->httpRequest($url, $method, $params, $signed);
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
        if ((!isset ($params['recvWindow'])) && (!is_null($this->recvWindow))) {
            $params['recvWindow'] = $this->recvWindow;
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
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/trading-endpoints
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/trading-endpoints#sor
     *
     * @see buy()
     * @see sell()
     * @see marketBuy()
     * @see marketSell() $this->httpRequest( "https://api.binance.com/api/v1/ticker/24hr");
     *
     * @param string $side (mandatory) typically "BUY" or "SELL"
     * @param string $symbol (mandatory) to buy or sell
     * @param string $quantity (mandatory) in the order
     * @param string $price (optional) for the order
     * @param string $type (optional) is determined by the symbol bu typicall LIMIT, STOP_LOSS_LIMIT etc. (default is LIMIT)
     * @param array $params (optional) additional transaction options
     * - @param string $params['timeInForce'] - GTC, IOC, FOK
     * - @param bool $params['isQuoteOrder'] - if true, quantity is in the quote asset (not for sor orders)
     * - @param string $params['newClientOrderId'] - custom client order id
     * - @param string $params['strategyId']
     * - @param int $params['strategyType']
     * - @param string $params['stopPrice'] - stop price for STOP_LOSS, STOP_LOSS_LIMIT, TAKE_PROFIT, and TAKE_PROFIT_LIMIT orders (not for SOR orders)
     * - @param string $params['trailingDelta'] - for STOP_LOSS, STOP_LOSS_LIMIT, TAKE_PROFIT, and TAKE_PROFIT_LIMIT orders (not for SOR orders)
     * - @param string $params['icebergQty'] - used with LIMIT, STOP_LOSS_LIMIT, and TAKE_PROFIT_LIMIT to create an iceberg order
     * - @param string $params['newOrderRespType'] - set the response type of the order (ACK, RESULT, FULL)
     * - @param string $params['selfTradePreventionMode'] - NONE, EXPIRE_MAKER, EXPIRE_TAKER, EXPIRE_BOTH or DECREMENT (default is NONE)
     * - @param bool $params['sor'] - if true, order will be sent to SOR endpoint
     * - @param bool $params
     * @param $test bool whether to test or not, test only validates the query
     * @return array containing the response
     * @throws \Exception
     */
    public function order(string $side, string $symbol, $quantity, $price, string $type = "LIMIT", array $params = [], bool $test = false)
    {
        $request = $this->createSpotOrderRequest($side, $symbol, $quantity, $price, $type, $params);
        $sor = false;
        if (isset($request['sor'])) {
            $sor = $request['sor'];
            unset($request['sor']);
        }
        $url = $sor ? "v3/sor/order" : "v3/order";
        if ($test) {
            $url = $url . "/test";
        }
        return $this->apiRequest($url, "POST", $request, true); // sending only $request cuz $params are already added to $request inside createSpotOrderRequest
    }

    /**
     * createSpotOrderRequest
     * helper function to create a request for the spot order
     */
    protected function createSpotOrderRequest(string $side, string $symbol, $quantity, $price, string $type = "LIMIT", array $params = [])
    {
        $request = [
            "symbol" => $symbol,
            "side" => $side,
            "type" => $type,
            "quantity" => $quantity,
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
            $request["price"] = $price;
            $request["timeInForce"] = "GTC";
        }

        if ($type === "MARKET" && isset($params['isQuoteOrder']) && $params['isQuoteOrder']) {
            unset($request['quantity']);
            unset($params['quantity']);
            unset($params['isQuoteOrder']);
            $request['quoteOrderQty'] = $quantity;
        }

        if (isset($params['stopPrice'])) {
            $request['stopPrice'] = $params['stopPrice'];
        }

        if (isset($params['icebergQty'])) {
            $request['icebergQty'] = $params['icebergQty'];
        }

        if (isset($params['newOrderRespType'])) {
            $request['newOrderRespType'] = $params['newOrderRespType'];
        }

        if (isset($params['newClientOrderId'])) {
            $request['newClientOrderId'] = $params['newClientOrderId'];
        } else {
            $request['newClientOrderId'] = $this->generateSpotClientOrderId();
        }
        return array_merge($request, $params);
    }

    /**
     * editOrder - edits a quantity of an existing order
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/trading-endpoints#order-amend-keep-priority-trade
     * @link https://developers.binance.com/docs/binance-spot-api-docs/faqs/order_amend_keep_priority
     *
     * @param string $symbol (mandatory) market symbol
     * @param string $quantity (mandatory) new quantity (must be greater than 0 and less than the original order quantity)
     * @param string $orderId (optional) order id to be amended (mandatory if origClientOrderId is not set)
     * @param string $origClientOrderId (optional) original client order id to be amended (mandatory if orderId is not set)
     * @param array $params (optional) additional transaction options
     * - @param string $params['newClientOrderId'] - custom client order id
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function editOrder(string $symbol, $quantity, ?string $orderId = null, ?string $origClientOrderId = null, array $params = [])
    {
        $request = [
            "symbol" => $symbol,
            "newQty" => $quantity,
        ];

        if (!is_null($orderId)) {
            $request['orderId'] = $orderId;
        } else if (is_null($origClientOrderId)) {
            throw new \Exception('editOrder(): Either orderId or origClientOrderId must be set');
        } else {
            $request['origClientOrderId'] = $origClientOrderId;
        }
        if (isset($params['newClientOrderId'])) {
            $request['newClientOrderId'] = $params['newClientOrderId'];
        } else {
            $request['newClientOrderId'] = $this->generateSpotClientOrderId();
        }
        return $this->apiRequest("v3/order/amend/keepPriority", "PUT", array_merge($request, $params), true);
    }

    /**
     * sorOrder creates an order using the SOR endpoint
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/trading-endpoints#sor
     * @link https://developers.binance.com/docs/binance-spot-api-docs/faqs/sor_faq
     *
     * @param string $side (mandatory) typically "BUY" or "SELL"
     * @param string $symbol (mandatory) to buy or sell
     * @param string $quantity (mandatory) in the order
     * @param string $price (optional) for the order
     * @param string $type (optional) is determined by the symbol bu typicall LIMIT, STOP_LOSS_LIMIT etc. (default is LIMIT)
     * @param array $params (optional) additional transaction options
     * - @param string $params['timeInForce'] - GTC, IOC, FOK
     * - @param string $params['newClientOrderId'] - custom client order id
     * - @param string $params['strategyId']
     * - @param int $params['strategyType']
     * - @param string $params['icebergQty'] - used with LIMIT, STOP_LOSS_LIMIT, and TAKE_PROFIT_LIMIT to create an iceberg order
     * - @param string $params['newOrderRespType'] - set the response type of the order (ACK, RESULT, FULL)
     * - @param string $params['selfTradePreventionMode'] - NONE, EXPIRE_MAKER, EXPIRE_TAKER, EXPIRE_BOTH or DECREMENT (default is NONE)
     * - @param bool $params['sor'] - if true, order will be sent to SOR endpoint
     * - @param bool $params
     * @param $test bool whether to test or not, test only validates the query
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function sorOrder(string $side, string $symbol, $quantity, $price, string $type = "LIMIT", array $params = [], bool $test = false)
    {
        $params['sor'] = true;
        return $this->order($side, $symbol, $quantity, $price, $type, $params, $test);
    }

    /**
     * replaceOrder - cancels an existing order and places a new order on the same symbol
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/trading-endpoints#cancel-an-existing-order-and-send-a-new-order-trade
     *
     * @param string $side (mandatory) typically "BUY" or "SELL"
     * @param string $symbol (mandatory) to buy or sell
     * @param string $quantity (mandatory) in the order
     * @param string $price (optional) for the order
     * @param string $type (optional) is determined by the symbol bu typicall LIMIT, STOP_LOSS_LIMIT etc. (default is LIMIT)
     * @param string $cancelOrderId (optional) the orderId of the order to be replaced (mandatory if cancelOrigClientOrderId is not set)
     * @param string $cancelOrigClientOrderId (optional) the origClientOrderId of the order to be replaced (mandatory if cancelOrderId is not set)
     * @param bool $allowFailure (optional) if true new order placement will be attempted even if cancel request fails (default is false)
     * @param string $cancelRestrictions (optional) ONLY_NEW (cancel will succeed if the order status is NEW) or ONLY_PARTIALLY_FILLED (cancel will succeed if order status is PARTIALLY_FILLED)
     * @param string $orderRateLimitExceededMode (optional) DO_NOTHING (default - will only attempt to cancel the order if account has not exceeded the unfilled order rate limit) or CANCEL_ONLY (will always cancel the order)
     * @param array $params (optional) additional transaction options (same as for order())
     *
     * returns array containing the response
     * @throws \Exception
     */
    public function replaceOrder(string $side, string $symbol, $quantity, $price, string $type = "LIMIT", ?string $cancelOrderId = null, string $cancelOrigClientOrderId = null, bool $allowFailure = null, string $cancelRestrictions = null, string $orderRateLimitExceededMode = null, array $params = [])
    {
        $request = $this->createSpotOrderRequest($side, $symbol, $quantity, $price, $type, $params);
        if (!is_null($cancelOrderId)) {
            $request['cancelOrderId'] = $cancelOrderId;
        } else if (is_null($cancelOrigClientOrderId)) {
            throw new \Exception("replaceOrder(): Either cancelOrderId or cancelOrigClientOrderId must be set");
        }
        if (!is_null($cancelOrigClientOrderId)) {
            $request['cancelOrigClientOrderId'] = $cancelOrigClientOrderId;
        }
        $request['cancelReplaceMode'] = $allowFailure ? 'ALLOW_FAILURE' : 'STOP_ON_FAILURE';
        if (!is_null($cancelRestrictions)) {
            $request['cancelRestrictions'] = $cancelRestrictions;
        }
        if (!is_null($orderRateLimitExceededMode)) {
            $request['orderRateLimitExceededMode'] = $orderRateLimitExceededMode;
        }
        return $this->apiRequest("v3/order/cancelReplace", "POST", $request, true); // only request is needed, params are already added to request inside createSpotOrderRequest
    }
    /**
     * candlesticks get the candles for the given intervals
     * 1m,3m,5m,15m,30m,1h,2h,4h,6h,8h,12h,1d,3d,1w,1M
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/market-data-endpoints
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
    public function candlesticks(string $symbol, string $interval = "5m", ?int $limit = null, $startTime = null, $endTime = null, array $params = [])
    {
        if (!isset($this->charts[$symbol])) {
            $this->charts[$symbol] = [];
        }

        $request = [
            "symbol" => $symbol,
            "interval" => $interval,
        ];

        if ($limit) {
            $request["limit"] = $limit;
        }

        if ($startTime) {
            $request["startTime"] = $startTime;
        }

        if ($endTime) {
            $request["endTime"] = $endTime;
        }

        $response = $this->apiRequest("v3/klines", "GET", array_merge($request, $params));

        if (is_array($response) === false) {
            return [];
        }

        if (count($response) === 0) {
            echo "warning: v3/klines returned empty array, usually a blip in the connection or server" . PHP_EOL;
            return [];
        }

        $ticks = $this->chartData($symbol, $interval, $response);
        $this->charts[$symbol][$interval] = $ticks;
        return $ticks;
    }

    /**
     * uiCandlesticks return modified kline data, optimized for presentation of candlestick charts
     * 1m,3m,5m,15m,30m,1h,2h,4h,6h,8h,12h,1d,3d,1w,1M
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/market-data-endpoints
     *
     * $candles = $api->uiCandlesticks("BNBBTC", "5m");
     *
     * @param $symbol market symbol to get the response for, e.g. ETHUSDT
     * @param $interval string to request
     * @param $limit int limit the amount of candles
     * @param $startTime string request candle information starting from here
     * @param $endTime string request candle information ending here
     * @param $params array additional parameters
     * - @param string $params['timeZone'] (optional) default is 0 (UTC) - time zone to use for the response
     * @return array containing the response
     * @throws \Exception
     */
    public function uiCandlesticks(string $symbol, string $interval = "5m", ?int $limit = null, $startTime = null, $endTime = null, array $params = [])
    {
        $request = [
            "symbol" => $symbol,
            "interval" => $interval,
        ];

        if ($limit) {
            $request["limit"] = $limit;
        }

        if ($startTime) {
            $request["startTime"] = $startTime;
        }

        if ($endTime) {
            $request["endTime"] = $endTime;
        }

        $response = $this->apiRequest("v3/uiKlines", "GET", array_merge($request, $params));

        return $response;
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
    protected function depthData(string $symbol, array $json, ?string $product_type = null)
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
    public function chart($symbols, string $interval = "30m", ?callable $callback = null, $limit = 500)
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
    public function kline($symbols, string $interval = "30m", ?callable $callback = null)
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
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/general-endpoints
     * @link https://binance-docs.github.io/apidocs/spot/en/#system-status-system
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api
     *
     * @property int $weight 2
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function systemStatus(array $params = [])
    {
        $arr = array();
        $api_status = $this->apiRequest("v3/ping", 'GET', $params);
        if ( empty($api_status) ) {
            $arr['api']['status']  = 'ping ok';
        } else {
            $arr['api']['status']  = $api_status;
        }

        $fapi_status = $this->fapiRequest("v1/ping", 'GET', $params);
        if ( empty($fapi_status) ) {
            $arr['fapi']['status'] = 'ping ok';
        } else {
            $arr['fapi']['status'] = $fapi_status;
        }

        $arr['sapi'] = $this->sapiRequest("v1/system/status", 'GET', $params);
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
    public function accountSnapshot($type, $nbrDays = 5, $startTime = 0, $endTime = 0, array $params = [])
    {
        if ($nbrDays < 5 || $nbrDays > 30)
            $nbrDays = 5;

        $request = [
            'type' => $type,
            ];

        if ($startTime > 0)
            $request['startTime'] = $startTime;
        if ($endTime > 0)
            $request['endTime'] = $endTime;
        if ($nbrDays != 5)
            $request['limit'] = $nbrDays;

        return $this->sapiRequest("v1/accountSnapshot", 'GET', array_merge($request, $params), true);
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
    public function accountStatus(array $params = [])
    {
        $arr = array();
        $arr['sapi'] = $this->sapiRequest("v1/account/status", 'GET', $params, true);
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
    public function apiRestrictions(array $params = [])
    {
        return $this->sapiRequest("v1/account/apiRestrictions", 'GET', $params, true);
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
    public function apiTradingStatus(array $params = [])
    {
        $arr = array();
        $arr['sapi'] = $this->sapiRequest("v1/account/apiTradingStatus", 'GET', $params, true);
        return $arr;
    }

    /**
     * orderRateLimit gets the user rate limit
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/account-endpoints#query-unfilled-order-count-user_data
     *
     * $rateLimit = $api->orderRateLimit();
     *
     * @property int $weight 1
     *
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the rate limit details
     * @throws \Exception
     */
    public function orderRateLimit(array $params = [])
    {
        return $this->apiRequest("v3/rateLimit/order", 'GET', $params, true);
    }

    /**
     * preventedMatches - Get the list of orders that were expired due to STP
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/account-endpoints#query-prevented-matches-user_data
     *
     * @property int $weight 2
     * - 2 if preventedMatchId is provided
     * - 20 if orderId is provided
     *
     * @param string $symbol (mandatory) The symbol, e.g. BTCUSDT
     * @param string $preventedMatchId (optional) The ID of the prevented match (mandatory if orderId is not provided)
     * @param string $orderId (optional) The ID of the order (mandatory if preventedMatchId is not provided)
     * @param string $fromPreventedMatchId (optional) The ID of the prevented match to start from
     * @param int    $limit (optional) The number of results to return (default is 500, max is 1000)
     * @param array  $params (optional) An array of additional parameters that the API endpoint allows
     *
     * @return array with error message or the rate limit details
     * @throws \Exception
     */
    public function preventedMatches(string $symbol, ?string $preventedMatchId = null, ?string $orderId = null, ?string $fromPreventedMatchId = null, ?int $limit = null, array $params = []) {
        $request = [
            'symbol' => $symbol,
        ];

        if (is_null($preventedMatchId) && is_null($orderId)) {
            throw new \Exception("Either preventedMatchId or orderId must be provided");
        } else if ($preventedMatchId) {
            $request['preventedMatchId'] = $preventedMatchId;
        } else {
            $request['orderId'] = $orderId;
        }

        if ($fromPreventedMatchId) {
            $request['fromPreventedMatchId'] = $fromPreventedMatchId;
        }

        if (!is_null($limit)) {
            $request['limit'] = $limit;
        }

        return $this->apiRequest("v3/myPreventedMatches", 'GET', array_merge($request, $params), true);
    }

    /**
     * allocations - Get the allocations resulting from SOR order placement
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/account-endpoints#query-allocations-user_data
     *
     * @property int $weight 20
     *
     * @param string $symbol (mandatory) The symbol, e.g. BTCUSDT
     * @param int    $startTime (optional) The start time of the query
     * @param int    $endTime (optional) The end time of the query
     * @param string $fromAllocationId (optional) The ID of the allocation to start from
     * @param int    $limit (optional) The number of results to return (default is 500, max is 1000)
     * @param string $orderId (optional) The ID of the order
     * @param array  $params (optional) An array of additional parameters that the API endpoint allows
     *
     * @return array with error message or the rate limit details
     * @throws \Exception
     */
    public function allocations(string $symbol, ?int $startTime = null, ?int $endTime = null, ?string $fromAllocationId = null, ?int $limit = null, ?string $orderId = null, array $params = []) {
        $request = [
            'symbol' => $symbol,
        ];

        if ($startTime) {
            $request['startTime'] = $startTime;
        }

        if ($endTime) {
            $request['endTime'] = $endTime;
        }

        if ($fromAllocationId) {
            $request['fromAllocationId'] = $fromAllocationId;
        }

        if (!is_null($limit)) {
            $request['limit'] = $limit;
        }

        if ($orderId) {
            $request['orderId'] = $orderId;
        }

        return $this->apiRequest("v3/myAllocations", 'GET', array_merge($request, $params), true);
    }

    /**
     * commissionRate - Get the commission rate for a symbol
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/account-endpoints#query-commission-rates-user_data
     *
     * @property int $weight 20
     *
     * @param string $symbol (mandatory) The symbol, e.g. BTCUSDT
     * @param array  $params (optional) An array of additional parameters that the API endpoint allows
     *
     * @return array with error message or the rate limit details
     * @throws \Exception
     */
    public function commissionRate(string $symbol, array $params = []) {
        $request = [
            'symbol' => $symbol,
        ];

        return $this->apiRequest("v3/account/commission", 'GET', array_merge($request, $params), true);
    }

    /**
     * orderAmendments - Get all amendments of a single order
     *
     * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/account-endpoints#query-order-amendments-user_data
     *
     * @property int $weight 4
     *
     * @param string $symbol (mandatory) The symbol, e.g. BTCUSDT
     * @param string $orderId (mandatory) The ID of the order
     * @param string $fromExecutionId (optional) The ID of the execution to start from
     * @param int    $limit (optional) The number of results to return (default is 500, max is 1000)
     *
     * @return array with error message or the rate limit details
     * @throws \Exception
     */
    public function orderAmendments(string $symbol, string $orderId, ?string $fromExecutionId = null, ?int $limit = null, array $params = []) {
        $request = [
            'symbol' => $symbol,
            'orderId' => $orderId,
        ];

        if ($fromExecutionId) {
            $request['fromExecutionId'] = $fromExecutionId;
        }

        if (!is_null($limit)) {
            $request['limit'] = $limit;
        }

        return $this->apiRequest("v3/order/amendments", 'GET', array_merge($request, $params), true);
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
     * @param array  $params                 (optional)   Extra flags/parameters
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function ocoOrder(string $side, string $symbol, $quantity, $price, $stopprice, $stoplimitprice = null, $stoplimittimeinforce = 'GTC', array $params = [])
    {
        $request = [
            "symbol" => $symbol,
            "side" => $side,
        ];

        if (is_numeric($quantity) === false) {
            $error = "Parameter quantity expected numeric for ' $side . ' ' . $symbol .', got " . gettype($quantity);
            trigger_error($error, E_USER_ERROR);
        } else {
            $request['quantity'] = $quantity;
        }

        if (is_numeric($price) === false) {
            $error = "Parameter price expected numeric for ' $side . ' ' . $symbol .', got " . gettype($price);
            trigger_error($error, E_USER_ERROR);
        } else {
            $request['price'] = $price;
        }

        if (is_numeric($stopprice) === false) {
            $error = "Parameter stopprice expected numeric for ' $side . ' ' . $symbol .', got " . gettype($stopprice);
            trigger_error($error, E_USER_ERROR);
        } else {
            $request['stopPrice'] = $stopprice;
        }

        if (is_null($stoplimitprice) === false && empty($stoplimitprice) === false) {
            $request['stopLimitPrice'] = $stoplimitprice;
            if ( ($stoplimittimeinforce == 'FOK') || ($stoplimittimeinforce == 'IOC') ) {
                $request['stopLimitTimeInForce'] = $stoplimittimeinforce;
            } else {
                $request['stopLimitTimeInForce'] = 'GTC'; // `Good 'till cancel`. Needed if flag `stopLimitPrice` used.
            }
        }

        // Check other flags
        foreach (array('icebergQty','stopIcebergQty','listClientOrderId','limitClientOrderId','stopClientOrderId','newOrderRespType') as $flag) {
            if ( isset($params[$flag]) && !empty($params[$flag]) )
                $request[$flag] = $params[$flag];
        }

        return $this->apiRequest("v3/order/oco", "POST", $request, true);
    }

    /**
    * avgPrice - get the average price of a symbol based on the last 5 minutes
    *
    * @link https://developers.binance.com/docs/binance-spot-api-docs/rest-api/market-data-endpoints
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
    public function avgPrice(string $symbol, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];
        $ticker = $this->apiRequest("v3/avgPrice", "GET", array_merge($request, $params));
        if (is_array($ticker) === false) {
            echo "Error: unable to fetch avg price" . PHP_EOL;
            $ticker = [];
        }
        if (empty($ticker)) {
            echo "Error: avg price was empty" . PHP_EOL;
            return null;
        }
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
    public function bswapQuote($baseAsset, $quoteAsset, $quoteQty, array $params = []) {
        $request = [
            'quoteAsset' => $quoteAsset,
            'baseAsset'  => $baseAsset,
            'quoteQty'   => $quoteQty,
        ];

        return $this->sapiRequest("v1/bswap/quote", 'GET', array_merge($request, $params), true);
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
    public function futuresTime(array $params = [])
    {
        return $this->fapiRequest("v1/time", "GET", $params);
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
    public function futuresExchangeInfo(array $params = [])
    {
        if (!$this->futuresExchangeInfo) {
            $arr = $this->fapiRequest("v1/exchangeInfo", "GET", $params);
            if ((is_array($arr) === false) || empty($arr)) {
                echo "Error: unable to fetch futures exchange info" . PHP_EOL;
                $arr = array();
                $arr['symbols'] = array();
            }
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
    public function futuresDepth(string $symbol, ?int $limit = null, array $params = [])
    {
        if (isset($symbol) === false || is_string($symbol) === false) {
            // WPCS: XSS OK.
            echo "asset: expected bool false, " . gettype($symbol) . " given" . PHP_EOL;
        }

        $request = [
            'symbol' => $symbol,
        ];
        if ($limit) {
            $request['limit'] = $limit;
        }
        $json = $this->fapiRequest("v1/depth", "GET", array_merge($request, $params));
        if (is_array($json) === false) {
            echo "Error: unable to fetch futures depth" . PHP_EOL;
            $json = [];
        }
        if (empty($json)) {
            echo "Error: futures depth were empty" . PHP_EOL;
            return $json;
        }
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
    public function futuresRecentTrades(string $symbol, ?int $limit = null, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];
        if ($limit) {
            $request['limit'] = $limit;
        }
        return $this->fapiRequest("v1/trades", "GET", array_merge($request, $params));
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
    public function futuresHistoricalTrades(string $symbol, $limit = null, $tradeId = null, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];
        if ($limit) {
            $request['limit'] = $limit;
        }
        if ($tradeId) {
            $request['fromId'] = $tradeId;
        }
        return $this->fapiRequest("v1/historicalTrades", "GET", array_merge($request, $params), true);
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
    public function futuresAggTrades(string $symbol, ?int $fromId = null, ?int $startTime = null, ?int $endTime = null, ?int $limit = null, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];
        if ($fromId) {
            $request['fromId'] = $fromId;
        }
        if ($startTime) {
            $request['startTime'] = $startTime;
        }
        if ($endTime) {
            $request['endTime'] = $endTime;
        }
        if ($limit) {
            $request['limit'] = $limit;
        }
        return $this->tradesData($this->fapiRequest("v1/aggTrades", "GET", array_merge($request, $params)));
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
    public function futuresCandlesticks(string $symbol, string $interval = '5m', ?int $limit = null, $startTime = null, $endTime = null, array $params = [])
    {
        return $this->futuresCandlesticksHelper($symbol, $interval, $limit, $startTime, $endTime, 'klines', null, $params);
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
    public function futuresContinuousCandlesticks(string $symbol, string $interval = '5m', ?int $limit = null, $startTime = null, $endTime = null, $contractType = 'PERPETUAL', array $params = [])
    {
        return $this->futuresCandlesticksHelper($symbol, $interval, $limit, $startTime, $endTime, 'continuousKlines', $contractType, $params);
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
    public function futuresIndexPriceCandlesticks(string $symbol, string $interval = '5m', ?int $limit = null, $startTime = null, $endTime = null, array $params = [])
    {
        return $this->futuresCandlesticksHelper($symbol, $interval, $limit, $startTime, $endTime, 'indexPriceKlines', null, $params);
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
    public function futuresMarkPriceCandlesticks(string $symbol, string $interval = '5m', ?int $limit = null, $startTime = null, $endTime = null, array $params = [])
    {
        return $this->futuresCandlesticksHelper($symbol, $interval, $limit, $startTime, $endTime, 'markPriceKlines', null, $params);
    }

    /**
     * futuresPremiumIndexCandlesticks get the candles for the given intervals
     * 1m,3m,5m,15m,30m,1h,2h,4h,6h,8h,12h,1d,3d,1w,1M
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/market-data/rest-api/Premium-Index-Kline-Data
     *
     * $candles = $api->futuresPremiumIndexCandlesticks("ETHBTC", "5m");
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
    public function futuresPremiumIndexCandlesticks(string $symbol, string $interval = '5m', ?int $limit = null, $startTime = null, $endTime = null, array $params = [])
    {
        return $this->futuresCandlesticksHelper($symbol, $interval, $limit, $startTime, $endTime, 'premiumIndexKlines', null, $params);
    }

    /**
     * futuresCandlesticksHelper
     * helper for routing the futuresCandlesticks, futuresContinuousCandlesticks, futuresIndexPriceCandlesticks, futuresMarkPriceCandlesticks and futuresPremiumIndexKlines
     */
    private function futuresCandlesticksHelper($symbol, $interval, $limit, $startTime, $endTime, $klineType, $contractType = null, $params = [])
    {
        if (!isset($this->charts['futures'])) {
            $this->charts['futures'] = [];
        }
        if (!isset($this->charts['futures'][$symbol])) {
            $this->charts['futures'][$symbol] = [];
        }
        if (!isset($this->charts['futures'][$symbol][$contractType])) {
            $this->charts['futures'][$symbol][$contractType] = [];
        }
        if (!isset($this->charts['futures'][$symbol][$contractType][$interval])) {
            $this->charts['futures'][$symbol][$contractType][$interval] = [];
        }
        $request = [
            'interval' => $interval,
        ];
        if ($klineType === 'continuousKlines' || $klineType === 'indexPriceKlines') {
            $request['pair'] = $symbol;
        } else {
            $request['symbol'] = $symbol;
        }
        if ($limit) {
            $request['limit'] = $limit;
        }
        if ($startTime) {
            $request['startTime'] = $startTime;
        }
        if ($endTime) {
            $request['endTime'] = $endTime;
        }
        if ($contractType) {
            $request['contractType'] = $contractType;
        }

        $response = $this->fapiRequest("v1/{$klineType}", 'GET', array_merge($request, $params));

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
    public function futuresMarkPrice(?string $symbol = null, array $params = [])
    {
        $request = [];
        if ($symbol) {
            $request['symbol'] = $symbol;
        }
        return $this->fapiRequest("v1/premiumIndex", "GET", array_merge($request, $params));
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
    public function futuresFundingRateHistory(?string $symbol = null, ?int $limit = null, $startTime = null, $endTime = null, array $params = [])
    {
        $request = [];
        if ($symbol) {
            $request['symbol'] = $symbol;
        }
        if ($limit) {
            $request['limit'] = $limit;
        }
        if ($startTime) {
            $request['startTime'] = $startTime;
        }
        if ($endTime) {
            $request['endTime'] = $endTime;
        }
        return $this->fapiRequest("v1/fundingRate", "GET", array_merge($request, $params));
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
    public function futuresFundingInfo(array $params = [])
    {
        return $this->fapiRequest("v1/fundingInfo", "GET", $params);
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
    public function futuresPrevDay(?string $symbol = null, array $params = [])
    {
        $request = [];
        if ($symbol) {
            $request['symbol'] = $symbol;
        }
        return $this->fapiRequest("v1/ticker/24hr", "GET", array_merge($request, $params));
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
    public function futuresPrice(string $symbol, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];
        $ticker = $this->fapiRequest("v1/ticker/price", "GET", array_merge($request, $params));
        if (!isset($ticker['price'])) {
            echo "Error: unable to fetch futures price for $symbol" . PHP_EOL;
            return null;
        }
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
    public function futuresPrices(array $params = [])
    {
        return $this->priceData($this->fapiRequest("v1/ticker/price", "GET", $params));
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
    public function futuresPriceV2(string $symbol, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];
        $ticker = $this->fapiRequest("v2/ticker/price", "GET", $request);
        if (!isset($ticker['price'])) {
            echo "Error: unable to fetch futures price for $symbol" . PHP_EOL;
            return null;
        }
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
    public function futuresPricesV2(array $params = [])
    {
        return $this->priceData($this->fapiRequest("v2/ticker/price", "GET", $params));
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
    public function futuresSymbolOrderBookTicker(?string $symbol = null, array $params = []): array
    {
        $request = [];
        if ($symbol) {
            $request['symbol'] = $symbol;
        }
        return $this->fapiRequest("v1/ticker/bookTicker", "GET", array_merge($request, $params));
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
    public function futuresDeliveryPrice(string $symbol, array $params = []): array
    {
        $request = [
            'pair' => $symbol,
        ];

        return $this->fapiDataRequest("delivery-price", "GET", array_merge($request, $params));
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
    public function futuresOpenInterest(string $symbol, array $params = []): array
    {
        $request = [
            'symbol'=> $symbol,
        ];
        return $this->fapiRequest("v1/openInterest", 'GET', array_merge($request, $params));
    }

    /**
     * sapieriodLimitStartEndFuturesDataRequest
     * helper for routing GET methods that require symbol, period, limit, startTime and endTime
     */
    private function sapieriodLimitStartEndFuturesDataRequest($symbol, $period, $limit, $startTime, $endTime, $url, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
            'period' => $period,
        ];
        if ($limit) {
            $request['limit'] = $limit;
        }
        if ($startTime) {
            $request['startTime'] = $startTime;
        }
        if ($endTime) {
            $request['endTime'] = $endTime;
        }
        return $this->fapiDataRequest($url, 'GET', array_merge($request, $params));
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
    public function futuresOpenInterestHistory(string $symbol, string $period = '5m', ?int $limit = null, $startTime = null, $endTime = null, array $params = [])
    {
        return $this->sapieriodLimitStartEndFuturesDataRequest($symbol, $period, $limit, $startTime, $endTime, 'openInterestHist', $params);
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
    public function futuresTopLongShortPositionRatio(string $symbol, string $period = '5m', ?int $limit = null, $startTime = null, $endTime = null, array $params = [])
    {
        return $this->sapieriodLimitStartEndFuturesDataRequest($symbol, $period, $limit, $startTime, $endTime, 'topLongShortPositionRatio', $params);
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
    public function futuresTopLongShortAccountRatio(string $symbol, string $period = '5m', ?int $limit = null, $startTime = null, $endTime = null, array $params = [])
    {
        return $this->sapieriodLimitStartEndFuturesDataRequest($symbol, $period, $limit, $startTime, $endTime, 'topLongShortAccountRatio', $params);
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
    public function futuresGlobalLongShortAccountRatio(string $symbol, string $period = '5m', ?int $limit = null, $startTime = null, $endTime = null, array $params = [])
    {
        return $this->sapieriodLimitStartEndFuturesDataRequest($symbol, $period, $limit, $startTime, $endTime, 'globalLongShortAccountRatio', $params);
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
    public function futuresTakerLongShortRatio(string $symbol, string $period = '5m', ?int $limit = null, $startTime = null, $endTime = null, array $params = [])
    {
        return $this->sapieriodLimitStartEndFuturesDataRequest($symbol, $period, $limit, $startTime, $endTime, 'takerlongshortRatio', $params);
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
    public function futuresBasis(string $symbol, string $period = '5m', int $limit = 30, $startTime = null, $endTime = null, $contractType = 'PERPETUAL', array $params = [])
    {
        $request = [
            'pair' => $symbol,
            'period' => $period,
            'contractType' => $contractType,
        ];
        if ($limit) {
            $request['limit'] = $limit;
        }
        if ($startTime) {
            $request['startTime'] = $startTime;
        }
        if ($endTime) {
            $request['endTime'] = $endTime;
        }
        return $this->fapiDataRequest("basis", 'GET', array_merge($request, $params));
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
    public function futuresIndexInfo(string $symbol, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];
        return $this->fapiRequest("v1/indexInfo", 'GET', array_merge($request, $params));
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
    public function futuresAssetIndex(?string $symbol = null, array $params = [])
    {
        $request = [];
        if ($symbol) {
            $request['symbol'] = $symbol;
        }
        return $this->fapiRequest("v1/assetIndex", 'GET', array_merge($request, $params));
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
    public function futuresConstituents(string $symbol, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];
        return $this->fapiRequest("v1/constituents", 'GET', array_merge($request, $params));
    }

    /**
     * createFuturesOrderRequest
     * helper for creating the request for futures order
     * @return array containing the request
     * @throws \Exception
     */
    protected function createFuturesOrderRequest(string $side, string $symbol, $quantity = null, $price = null, string $type = 'LIMIT', array $params = []) {
        $request = [
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
            if (isset($params['closePosition']) && $params['closePosition'] === true) {
                // WPCS: XSS OK.
                echo "warning: closePosition is set to true, quantity will be ignored" . PHP_EOL;
            } else {
                $request['quantity'] = $quantity;
            }
        }

        if ($price && is_string($price) === false) {
            // WPCS: XSS OK.
            echo "warning: price expected string got " . gettype($price) . PHP_EOL;
        }

        if ($type === "LIMIT" || $type === "STOP_LOSS_LIMIT" || $type === "TAKE_PROFIT_LIMIT" || $type === "STOP" || $type === "TAKE_PROFIT") {
            $request["price"] = $price;
            if (!isset($params['timeInForce'])) {
                $request['timeInForce'] = 'GTC';
            }
        }

        if (isset($params['reduceOnly'])) {
            $reduceOnly = $params['reduceOnly'];
            if ($reduceOnly === true) {
                $request['reduceOnly'] = 'true';
            } else {
                $request['reduceOnly'] = 'false';
            }
            unset($params['reduceOnly']);
        }

        if (!isset($params['newClientOrderId'])) {
            $request['newClientOrderId'] = $this->generateFuturesClientOrderId();
        }

        if (isset($params['closePosition'])) {
            $closePosition = $params['closePosition'];
            if ($closePosition === true) {
                $request['closePosition'] = 'true';
            } else {
                $request['closePosition'] = 'false';
            }
            unset($params['closePosition']);
        }

        if (isset($params['priceProtect'])) {
            $priceProtect = $params['priceProtect'];
            if ($priceProtect === true) {
                $request['priceProtect'] = 'TRUE';
            } else {
                $request['priceProtect'] = 'FALSE';
            }
            unset($params['priceProtect']);
        }

        return array_merge($request, $params);
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
     * @param array $params (optional) additional transaction options
     * - @param string $params['positionSide'] position side, "BOTH" for One-way Mode; "LONG" or "SHORT" for Hedge Mode (mandatory for Hedge Mode)
     * - @param string $params['timeInForce']
     * - @param bool   $params['reduceOnly'] default false (Cannot be sent in Hedge Mode; cannot be sent with closePosition=true)
     * - @param string $params['newClientOrderId'] new client order id
     * - @param string $params['stopPrice'] stop price (Used with STOP/STOP_MARKET or TAKE_PROFIT/TAKE_PROFIT_MARKET orders)
     * - @param bool   $params['closePosition'] Close-All (used with STOP_MARKET or TAKE_PROFIT_MARKET orders)
     * - @param string $params['activationPrice'] Used with TRAILING_STOP_MARKET orders, default as the latest price (supporting different workingType)
     * - @param string $params['callbackRate'] Used with TRAILING_STOP_MARKET orders, min 0.1, max 5 where 1 for 1%
     * - @param string $params['workingType'] stopPrice triggered by: "MARK_PRICE", "CONTRACT_PRICE". Default "CONTRACT_PRICE"
     * - @param bool   $params['priceProtect'] Used with STOP/STOP_MARKET or TAKE_PROFIT/TAKE_PROFIT_MARKET orders (default false)
     * - @param string $params['newOrderRespType'] response type, default "RESULT", other option is "ACK"
     * - @param string $params['priceMatch'] only avaliable for LIMIT/STOP/TAKE_PROFIT order; can be set to OPPONENT/ OPPONENT_5/ OPPONENT_10/ OPPONENT_20: /QUEUE/ QUEUE_5/ QUEUE_10/ QUEUE_20; Can't be passed together with price
     * - @param string $params['selfTradePreventionMode'] EXPIRE_TAKER:expire taker order when STP triggers/ EXPIRE_MAKER:expire taker order when STP triggers/ EXPIRE_BOTH:expire both orders when STP triggers; default NONE
     * - @param string $params['goodTillDate'] order cancel time for timeInForce GTD, mandatory when timeInforce set to GTD; order the timestamp only retains second-level precision, ms part will be ignored; The goodTillDate timestamp must be greater than the current time plus 600 seconds and smaller than 253402300799000
     * - @param int    $params['recvWindow']
     * @param $test bool whether to test or not, test only validates the query
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresOrder(string $side, string $symbol, $quantity = null, $price = null, string $type = 'LIMIT', array $params = [], $test = false)
    {
        $request = $this->createFuturesOrderRequest($side, $symbol, $quantity, $price, $type, $params);
        $isAlgoOrder = $this->isAlgoFuturesOrderType($request['type']);
        if ($isAlgoOrder) {
            $algoRequest = $this->createAlgoFuturesOrderRequest($request);
            return $this->fapiRequest('v1/algoOrder', 'POST', $algoRequest, true);
        } else {
            // regular order
            $qstring = ($test === false) ? 'v1/order' : 'v1/order/test';
            return $this->fapiRequest($qstring, 'POST', $request, true);
        }
    }

    /**
     * isAlgoFuturesOrderType helper to determine if an order type is an algo order
     * @param string $type order type
     * @return bool true if it is an algo order type
     */
    protected function isAlgoFuturesOrderType(string $type): bool
    {
        $algoOrderTypes = [
            'STOP',
            'STOP_MARKET',
            'TAKE_PROFIT',
            'TAKE_PROFIT_MARKET',
            'TRAILING_STOP_MARKET',
        ];
        if (in_array($type, $algoOrderTypes)) {
            return true;
        }
        return false;
    }

    /**
     * createAlgoFuturesOrderRequest
     * helper for creating the request for algo futures order
     * @param array $request regular order request
     * @return array containing the request
     */
    protected function createAlgoFuturesOrderRequest(array $request): array
    {
        $request['algoType'] = 'CONDITIONAL';
        if (!isset($request['clientAlgoId'])) {
            $newClientOrderId = $request['newClientOrderId'];
            $request['clientAlgoId'] = $newClientOrderId;
            unset($request['newClientOrderId']);
        }
        if (!isset($request['triggerPrice']) && isset($request['stopPrice'])) {
            $request['triggerPrice'] = $request['stopPrice'];
            unset($request['stopPrice']);
        }
        return $request;
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
     * @param array $params (optional) addtional options for order type
     * - @param string $params['positionSide'] position side, "BOTH" for One-way Mode; "LONG" or "SHORT" for Hedge Mode (mandatory for Hedge Mode)
     * - @param string $params['timeInForce']
     * - @param bool   $params['reduceOnly'] default false (Cannot be sent in Hedge Mode; cannot be sent with closePosition=true)
     * - @param string $params['newClientOrderId'] new client order id
     * - @param string $params['stopPrice'] stop price (Used with STOP/STOP_MARKET or TAKE_PROFIT/TAKE_PROFIT_MARKET orders)
     * - @param bool   $params['closePosition'] Close-All (used with STOP_MARKET or TAKE_PROFIT_MARKET orders)
     * - @param string $params['activationPrice'] Used with TRAILING_STOP_MARKET orders, default as the latest price (supporting different workingType)
     * - @param string $params['callbackRate'] Used with TRAILING_STOP_MARKET orders, min 0.1, max 5 where 1 for 1%
     * - @param string $params['workingType'] stopPrice triggered by: "MARK_PRICE", "CONTRACT_PRICE". Default "CONTRACT_PRICE"
     * - @param bool   $params['priceProtect'] Used with STOP/STOP_MARKET or TAKE_PROFIT/TAKE_PROFIT_MARKET orders (default false)
     * - @param string $params['newOrderRespType'] response type, default "RESULT", other option is "ACK"
     * - @param string $params['priceMatch'] only avaliable for LIMIT/STOP/TAKE_PROFIT order; can be set to OPPONENT/ OPPONENT_5/ OPPONENT_10/ OPPONENT_20: /QUEUE/ QUEUE_5/ QUEUE_10/ QUEUE_20; Can't be passed together with price
     * - @param string $params['selfTradePreventionMode'] EXPIRE_TAKER:expire taker order when STP triggers/ EXPIRE_MAKER:expire taker order when STP triggers/ EXPIRE_BOTH:expire both orders when STP triggers; default NONE
     * - @param string $params['goodTillDate'] order cancel time for timeInForce GTD, mandatory when timeInforce set to GTD; order the timestamp only retains second-level precision, ms part will be ignored; The goodTillDate timestamp must be greater than the current time plus 600 seconds and smaller than 253402300799000
     * - @param int    $params['recvWindow']
     * @return array with error message or the order details
     */
    public function futuresBuy(string $symbol, $quantity = null, $price = null, string $type = 'LIMIT', array $params = [])
    {
        return $this->futuresOrder('BUY', $symbol, $quantity, $price, $type, $params);
    }

    /**
     * futuresBuyTest attempts to create a TEST futures buy order
     *
     * @see futuresBuy()
     *
     * params and return value are the same as @see futuresBuy()
     */
    public function futuresBuyTest(string $symbol, $quantity = null, $price = null, string $type = 'LIMIT', array $params = [])
    {
        return $this->futuresOrder('BUY', $symbol, $quantity, $price, $type, $params, true);
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
     * @param array $params (optional) addtional options for order type
     * - @param string $params['positionSide'] position side, "BOTH" for One-way Mode; "LONG" or "SHORT" for Hedge Mode (mandatory for Hedge Mode)
     * - @param string $params['timeInForce']
     * - @param bool   $params['reduceOnly'] default false (Cannot be sent in Hedge Mode; cannot be sent with closePosition=true)
     * - @param string $params['newClientOrderId'] new client order id
     * - @param string $params['stopPrice'] stop price (Used with STOP/STOP_MARKET or TAKE_PROFIT/TAKE_PROFIT_MARKET orders)
     * - @param bool   $params['closePosition'] Close-All (used with STOP_MARKET or TAKE_PROFIT_MARKET orders)
     * - @param string $params['activationPrice'] Used with TRAILING_STOP_MARKET orders, default as the latest price (supporting different workingType)
     * - @param string $params['callbackRate'] Used with TRAILING_STOP_MARKET orders, min 0.1, max 5 where 1 for 1%
     * - @param string $params['workingType'] stopPrice triggered by: "MARK_PRICE", "CONTRACT_PRICE". Default "CONTRACT_PRICE"
     * - @param bool   $params['priceProtect'] Used with STOP/STOP_MARKET or TAKE_PROFIT/TAKE_PROFIT_MARKET orders (default false)
     * - @param string $params['newOrderRespType'] response type, default "RESULT", other option is "ACK"
     * - @param string $params['priceMatch'] only avaliable for LIMIT/STOP/TAKE_PROFIT order; can be set to OPPONENT/ OPPONENT_5/ OPPONENT_10/ OPPONENT_20: /QUEUE/ QUEUE_5/ QUEUE_10/ QUEUE_20; Can't be passed together with price
     * - @param string $params['selfTradePreventionMode'] EXPIRE_TAKER:expire taker order when STP triggers/ EXPIRE_MAKER:expire taker order when STP triggers/ EXPIRE_BOTH:expire both orders when STP triggers; default NONE
     * - @param string $params['goodTillDate'] order cancel time for timeInForce GTD, mandatory when timeInforce set to GTD; order the timestamp only retains second-level precision, ms part will be ignored; The goodTillDate timestamp must be greater than the current time plus 600 seconds and smaller than 253402300799000
     * - @param int    $params['recvWindow']
     * @return array with error message or the order details
     */
    public function futuresSell(string $symbol, $quantity = null, $price = null, string $type = 'LIMIT', array $params = [])
    {
        return $this->futuresOrder('SELL', $symbol, $quantity, $price, $type, $params);
    }

    /**
     * futuresSellTest attempts to create a TEST futures sell order
     *
     * @see futuresSell()
     *
     * params and return value are the same as @see futuresSell()
     */
    public function futuresSellTest(string $symbol, $quantity = null, $price = null, $type = null, array $params = [])
    {
        if ($type === null) {
            $type = 'LIMIT';
        }
        return $this->futuresOrder('SELL', $symbol, $quantity, $price, $type, $params, true);
    }

    /**
     * createBatchOrdersRequest
     * helper for creating the request for multiple futures orders
     * @param array $orders (mandatory) array of orders to be placed
     * objects in the array should contain literally the same keys as the @see futuresOrder but without the $params[recvWindow]
     *
     * @return array containing the request
     * @throws \Exception
     */
    protected function createBatchOrdersRequest(array $orders, bool $edit = false)
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
            if (!isset($order['params'])) {
                $order['params'] = [];
            }
            if (!isset($order['type'])) {
                $order['type'] = 'LIMIT';
            }
            $formatedOrder = $this->createFuturesOrderRequest(
                $order['side'],
                $order['symbol'],
                $order['quantity'],
                $order['price'],
                $order['type'],
                $order['params']
            );
            if (isset($formatedOrder['recvWindow'])) {
                // remove recvWindow from the order
                unset($formatedOrder['recvWindow']);
            }
            if ($edit) {
                if (isset($order['orderId'])) {
                    $formatedOrder['orderId'] = $order['orderId'];
                }
                if (isset($order['origClientOrderId'])) {
                    $formatedOrder['origClientOrderId'] = $order['origClientOrderId'];
                }
                unset($formatedOrder['type']);
                unset($formatedOrder['newClientOrderId']);
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
     * objects in the array should contain literally the same keys as the @see futuresOrder but without the $params['recvWindow']
     * @param array  $params   (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response or error message
     * @throws \Exception
     */
    public function futuresBatchOrders(array $orders, array $params = [])
    {
        $formatedOrders = $this->createBatchOrdersRequest($orders);
        if (count($formatedOrders) > 5) {
            throw new \Exception('futuresBatchOrders(): max 5 orders allowed');
        }
        if (count($formatedOrders) < 1) {
            throw new \Exception('futuresBatchOrders(): at least 1 order required');
        }
        $request = [];

        // current endpoint accepts orders list as a json string in the query string
        $encodedOrders = json_encode($formatedOrders);
        $url = 'v1/batchOrders?batchOrders=' . $encodedOrders;
        return $this->fapiRequest($url, 'POST', array_merge($request, $params), true);
    }

    /**
     * futuresEditOrder edits the limit order
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Modify-Order
     *
     * @param string $symbol (mandatory) market symbol
     * @param string $side (mandatory) "BUY" or "SELL"
     * @param string $orderId (optional) order id to be modified (mandatory if $params['origClientOrderId'] is not set)
     * @param string $quantity (optional) of the order (Cannot be sent for orders with closePosition=true (Close-All))
     * @param string $price (mandatory) price per unit
     * @param array $params (optional) additional options
     * - @param string $params['priceMatch'] only avaliable for LIMIT/STOP/TAKE_PROFIT order; can be set to OPPONENT/ OPPONENT_5/ OPPONENT_10/ OPPONENT_20: /QUEUE/ QUEUE_5/ QUEUE_10/ QUEUE_20; Can't be passed together with price
     * - @param int    $params['recvWindow']
     * - @param string $params['origClientOrderId'] client order id to be modified (mandatory if $orderId is not set)
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresEditOrder(string $symbol, string $side, string $quantity, string $price, $orderId = null, array $params = [])
    {
        $request = $this->createFuturesOrderRequest($side, $symbol, $quantity, $price, 'LIMIT', $params);
        $origClientOrderId = null;
        if (isset($params['origClientOrderId'])) {
            $origClientOrderId = $params['origClientOrderId'];
            $request['origClientOrderId'] = $origClientOrderId;
        }
        if (!$origClientOrderId && !$orderId) {
            throw new \Exception('futuresEditOrder(): either orderId or origClientOrderId must be set');
        }
        if ($orderId) {
            $request['orderId'] = $orderId;
        }
        unset($request['type']);
        unset($request['newClientOrderId']);
        return $this->fapiRequest("v1/order", 'PUT', $request, true);
    }

    /**
     * futuresEditOrders edits the multiple limit orders
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Modify-Multiple-Orders
     *
     * @param array $orders (mandatory) array of orders to be modified
     * objects in the array should contain literally the same keys as the @see futuresEditOrder but without the $params['recvWindow']
     * @param array  $params   (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response or error message
     * @throws \Exception
     */
    public function futuresEditOrders(array $orders, array $params = [])
    {
        $formatedOrders = $this->createBatchOrdersRequest($orders, true);
        $request = [];

        // current endpoint accepts orders list as a json string in the query string
        $request['batchOrders'] = json_encode($formatedOrders);
        return $this->fapiRequest("v1/batchOrders", 'PUT', array_merge($request, $params), true);
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
     * @param array  $params   (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresOrderAmendment(string $symbol, $orderId = null, $origClientOrderId = null, $startTime = null, $endTime = null, $limit = null, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];
        if ($orderId) {
            $request['orderId'] = $orderId;
        }
        if ($origClientOrderId) {
            $request['origClientOrderId'] = $origClientOrderId;
        }
        if ($startTime) {
            $request['startTime'] = $startTime;
        }
        if ($endTime) {
            $request['endTime'] = $endTime;
        }
        if ($limit) {
            $request['limit'] = $limit;
        }

        return $this->fapiRequest("v1/orderAmendment", 'GET', array_merge($request, $params), true);
    }

    /**
     * futuresCancel cancels a futures order
     *
     * $orderid = "123456789";
     * $order = $api->futuresCancel("BNBBTC", $orderid);
     *
     * @param string $symbol (mandatory) market symbol (e.g. ETHUSDT)
     * @param string $orderid (optional) the orderid to cancel (mandatory if $params['origClientOrderId'] is not set)
     * @param array  $params (optional) additional options
     * - @param string $params['origClientOrderId'] original client order id to cancel
     * - @param int    $params['recvWindow'] the time in milliseconds to wait for a response
     *
     * @return array with error message or the order details
     * @throws \Exception
     */
    public function futuresCancel(string $symbol, $orderid, $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];
        if ($orderid) {
            $request['orderId'] = $orderid;
        } else if (!isset($params['origClientOrderId'])) {
            throw new \Exception('futuresCancel(): either orderId or origClientOrderId must be set');
        }
        return $this->fapiRequest("v1/order", 'DELETE', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the orders details
     * @throws \Exception
     */
    public function futuresCancelBatchOrders(string $symbol, $orderIdList = null, $origClientOrderIdList = null, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];
        if ($orderIdList) {
            $idsString = json_encode($orderIdList);
            // remove quotes and spaces
            $request['orderIdList'] = str_replace(' ', '', str_replace('"', '', str_replace("'", '', $idsString)));
        } else if ($origClientOrderIdList) {
            // remove spaces between the ids
            $request['origClientOrderIdList'] = str_replace(', ', ',', json_encode($origClientOrderIdList));
        } else {
            throw new \Exception('futuresCancelBatchOrders(): either orderIdList or origClientOrderIdList must be set');
        }

        return $this->fapiRequest("v1/batchOrders", 'DELETE', array_merge($request, $params), true);
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
    public function futuresCancelOpenOrders(string $symbol, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];

        return $this->fapiRequest("v1/allOpenOrders", 'DELETE', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the orders details
     * @throws \Exception
     */
    public function futuresCountdownCancelAllOrders(string $symbol, int $countdownTime, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
            'countdownTime' => $countdownTime,
        ];

        return $this->fapiRequest("v1/countdownCancelAll", 'POST', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the order details
     * @throws \Exception
     */
    public function futuresOrderStatus(string $symbol, $orderId = null, $origClientOrderId = null, array $params = [], )
    {
        $request = [
            'symbol' => $symbol,
        ];
        if ($orderId) {
            $request['orderId'] = $orderId;
        } else if ($origClientOrderId) {
            $request['origClientOrderId'] = $origClientOrderId;
        } else {
            throw new \Exception('futuresOrderStatus(): either orderId or origClientOrderId must be set');
        }

        return $this->fapiRequest("v1/order", 'GET', array_merge($request, $params), true);
    }

    /**
     * futuresAlgoOrderStatus gets the details of a futures algo order
     *
     * @link https://developers.binance.com/docs/derivatives/usds-margined-futures/trade/rest-api/Query-Algo-Order
     *
     * $order = $api->futuresAlgoOrderStatus("123456789");
     *
     * @param string $algoId (optional) order id to get the response for (mandatory if clientAlgoId is not set)
     * @param string $clientAlgoId (optional) original client order id to get the response for (mandatory if algoId is not set)
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the order details
     * @throws \Exception
     */
    public function futuresAlgoOrderStatus($algoId = null, $clientAlgoId = null, array $params = [])
    {
        $request = [];
        if ($algoId) {
            $request['algoId'] = $algoId;
        } else if ($clientAlgoId) {
            $request['clientAlgoId'] = $clientAlgoId;
        } else {
            throw new \Exception('futuresAlgoOrderStatus(): either algoId or clientAlgoId must be set');
        }

        return $this->fapiRequest("v1/algoOrder", 'GET', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the orders details
     * @throws \Exception
     */
    public function futuresAllOrders(string $symbol, $startTime = null, $endTime = null, $limit = null, $orderId = null, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];
        if ($startTime) {
            $request['startTime'] = $startTime;
        }
        if ($endTime) {
            $request['endTime'] = $endTime;
        }
        if ($limit) {
            $request['limit'] = $limit;
        }
        if ($orderId) {
            $request['orderId'] = $orderId;
        }

        return $this->fapiRequest("v1/allOrders", 'GET', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the orders details
     * @throws \Exception
     */
    public function futuresOpenOrders($symbol = null, array $params = [])
    {
        $request = [];
        if ($symbol) {
            $request['symbol'] = $symbol;
        }

        return $this->fapiRequest("v1/openOrders", 'GET', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the order details
     * @throws \Exception
     */
    public function futuresOpenOrder(string $symbol, $orderId = null, $origClientOrderId = null, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];
        if ($orderId) {
            $request['orderId'] = $orderId;
        } else if ($origClientOrderId) {
            $request['origClientOrderId'] = $origClientOrderId;
        } else {
            throw new \Exception('futuresOpenOrder(): either orderId or origClientOrderId must be set');
        }

        return $this->fapiRequest("v1/openOrder", 'GET', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the orders details
     * @throws \Exception
     */
    public function futuresForceOrders($symbol = null, $startTime = null, $endTime = null, $limit = null, $autoCloseType = null, array $params = [])
    {
        $request = [];
        if ($symbol) {
            $request['symbol'] = $symbol;
        }
        if ($startTime) {
            $request['startTime'] = $startTime;
        }
        if ($endTime) {
            $request['endTime'] = $endTime;
        }
        if ($limit) {
            $request['limit'] = $limit;
        }
        if ($autoCloseType) {
            $request['autoCloseType'] = $autoCloseType;
        }

        return $this->fapiRequest("v1/forceOrders", 'GET', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the trades details
     * @throws \Exception
     */
    public function futuresMyTrades(string $symbol, $startTime = null, $endTime = null, $limit = null, $orderId = null, $fromId = null, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];
        if ($startTime) {
            $request['startTime'] = $startTime;
        }
        if ($endTime) {
            $request['endTime'] = $endTime;
        }
        if ($limit) {
            $request['limit'] = $limit;
        }
        if ($orderId) {
            $request['orderId'] = $orderId;
        }
        if ($fromId) {
            $request['fromId'] = $fromId;
        }

        return $this->fapiRequest("v1/userTrades", 'GET', array_merge($request, $params), true);
    }

    /**
     * futuresHistory
     * another name for futuresMyTrades (for naming compatibility with spot)
     * @deprecated
     */
    public function futuresHistory(string $symbol, $startTime = null, $endTime = null, $limit = null, $orderId = null, $fromId = null, array $params = [])
    {
        return $this->futuresMyTrades($symbol, $startTime, $endTime, $limit, $orderId, $fromId, $params);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresSetMarginMode(string $symbol, string $marginType, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
            'marginType' => $marginType,
        ];

        return $this->fapiRequest("v1/marginType", 'POST', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresPositionMode(array $params = [])
    {
        return $this->fapiRequest("v1/positionSide/dual", 'GET', $params, true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresSetPositionMode(bool $dualSidePosition, array $params = [])
    {
        $request = [
            'dualSidePosition' => $dualSidePosition ? 'true' : 'false',
        ];

        return $this->fapiRequest("v1/positionSide/dual", 'POST', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresSetLeverage(int $leverage, string $symbol, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
            'leverage' => $leverage,
        ];

        return $this->fapiRequest("v1/leverage", 'POST', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresMultiAssetsMarginMode(array $params = [])
    {
        return $this->fapiRequest("v1/multiAssetsMargin", 'GET', $params, true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresSetMultiAssetsMarginMode(bool $multiAssetsMarginMode, array $params = [])
    {
        $request = [
            'multiAssetsMarginMode' => $multiAssetsMarginMode ? 'true' : 'false',
        ];

        return $this->fapiRequest("v1/multiAssetsMarginMode", 'POST', array_merge($request, $params), true);
    }

    /**
     * modifyMarginHelper helper for adding or removing margin
     *
     * @see futuresAddMargin() and futuresReduceMargin()
     *
     * @return array containing the response
     * @throws \Exception
     */
    protected function modifyMarginHelper(string $symbol, string $amount, $addOrReduce, $positionSide = null, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
            'amount' => $amount,
            'type' => $addOrReduce,
        ];
        if ($positionSide) {
            $request['positionSide'] = $positionSide;
        }

        return $this->fapiRequest("v1/positionMargin", 'POST', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresAddMargin(string $symbol, string $amount, $positionSide = null, array $params = [])
    {
        return $this->modifyMarginHelper($symbol, $amount, 1, $positionSide, $params);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresReduceMargin(string $symbol, string $amount, $positionSide = null, array $params = [])
    {
        return $this->modifyMarginHelper($symbol, $amount, 2, $positionSide, $params);
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
     * @param array  $params      (optional) an array of additional parameters that the API endpoint afllows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     * @param string $api_version (optional) API version, "v2" or "v3" (default is v3)
     *
     * @return array with error message or the position details
     * @throws \Exception
     */
    public function futuresPositions($symbol = null, array $params = [], string $api_version = 'v3')
    {
        $request = [];
        if ($symbol) {
            $request['symbol'] = $symbol;
        }

        if ($api_version !== 'v2' && $api_version !== 'v3') {
            throw new \Exception('futuresPositions(): api_version must be either v2 or v3');
        }
        return $this->fapiRequest($api_version . "/positionRisk", 'GET', array_merge($request, $params), true);
    }

    /** futuresPositionsV2
     * @see futuresPositions
     */
    public function futuresPositionsV2($symbol = null, array $params = [])
    {
        return $this->futuresPositions($symbol, $params, 'v2');
    }

    /**
     * futuresPositionsV3
     * @see futuresPositions
     */
    public function futuresPositionsV3($symbol = null, array $params = [])
    {
        return $this->futuresPositions($symbol, $params, 'v3');
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
     * @param array  $params (optional) an array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     * @param string $api_version (optional) API version, "v2" or "v3" (default is v3)
     *
     * @return array with error message or the position details
     * @throws \Exception
     */
    public function futuresPosition(string $symbol, array $params = [], string $api_version = 'v3')
    {
        return $this->futuresPositions($symbol, $params, $api_version);
    }

    /**
     * futuresPositionV2
     * @see futuresPosition
     */
    public function futuresPositionV2(string $symbol, array $params = [])
    {
        return $this->futuresPositionsV2($symbol, $params, 'v2');
    }

    /**
     * futuresPositionV3
     * @see futuresPosition
     */
    public function futuresPositionV3(string $symbol, array $params = [])
    {
        return $this->futuresPositionsV3($symbol, $params, 'v3');
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the ADL quantile details
     * @throws \Exception
     */
    public function futuresAdlQuantile($symbol = null, array $params = [])
    {
        $request = [];
        if ($symbol) {
            $request['symbol'] = $symbol;
        }

        return $this->fapiRequest("v1/adlQuantile", 'GET', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     */
    public function futuresPositionMarginChangeHistory(string $symbol, $startTime = null, $endTime = null, $limit = null, $addOrReduce = null, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];
        if ($startTime) {
            $request['startTime'] = $startTime;
        }
        if ($endTime) {
            $request['endTime'] = $endTime;
        }
        if ($limit) {
            $request['limit'] = $limit;
        }
        if ($addOrReduce) {
            if (is_numeric($addOrReduce)) {
                $request['addOrReduce'] = $addOrReduce;
            } else if (is_string($addOrReduce)) {
                $addOrReduce = strtoupper($addOrReduce);
                if ($addOrReduce === 'ADD' || $addOrReduce === '1') {
                    $request['addOrReduce'] = 1;
                } else if ($addOrReduce === 'REDUCE' || $addOrReduce === '2') {
                    $request['addOrReduce'] = 2;
                } else {
                    throw new \Exception('futuresPositionMarginChangeHistory(): addOrReduce must be "ADD" or "REDUCE" or 1 or 2');
                }
            } else {
                throw new \Exception('futuresPositionMarginChangeHistory(): addOrReduce must be "ADD" or "REDUCE" or 1 or 2');
            }
        }

        return $this->fapiRequest("v1/positionMargin/history", 'GET', array_merge($request, $params), true);
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
     * @param array  $params (optional) an array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     * @param string $api_version (optional) API version, "v2" or "v3" (default is v3)
     *
     * @return array with error message or the balance details
     * @throws \Exception
     */
    public function futuresBalances(array $params = [], string $api_version = 'v3')
    {
        if ($api_version !== 'v2' && $api_version !== 'v3') {
            throw new \Exception('futuresBalances(): api_version must be either v2 or v3');
        }
        return $this->balances('futures', $params, 'v3');
    }

    /**
     * futuresBalancesV2
     * see futuresBalances
     */
    public function futuresBalancesV2(array $params = [])
    {
        return $this->futuresBalances($params, 'v2');
    }

    /**
     * futuresBalancesV3
     * see futuresBalances
     */
    public function futuresBalancesV3(array $params = [])
    {
        return $this->futuresBalances($params, 'v3');
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
     * @param array  $params (optional) an array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     * @param string $api_version (optional) API version, "v2" or "v3" (default is v3)
     *
     * @return array with error message or array of all the account information
     * @throws \Exception
     */
    public function futuresAccount(array $params = [], string $api_version = 'v3')
    {
        if ($api_version !== 'v2' && $api_version !== 'v3') {
            throw new \Exception('futuresAccount(): api_version must be either v2 or v3');
        }

        return $this->fapiRequest($api_version . "/account", "GET", $params, true);
    }

    /**
     * futuresAccountV2
     * see futuresAccount
     */
    public function futuresAccountV2(array $params = [])
    {
        return $this->futuresAccount($params, 'v2');
    }

    /**
     * futuresAccountV3
     * see futuresAccount
     */
    public function futuresAccountV3(array $params = [])
    {
        return $this->futuresAccount($params, 'v3');
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the trade fee details
     * @throws \Exception
     */
    public function futuresTradeFee(string $symbol, array $params = [])
    {
        $request = [
            'symbol' => $symbol,
        ];

        return $this->fapiRequest("v1/commissionRate", 'GET', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the account configuration details
     * @throws \Exception
     */
    public function futuresAccountConfig(array $params = [])
    {
        return $this->fapiRequest("v1/accountConfig", 'GET', $params, true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the margin mode details
     * @throws \Exception
     */
    public function futuresMarginModes($symbol = null, array $params = [])
    {
        $request = [];
        if ($symbol) {
            $request['symbol'] = $symbol;
        }

        return $this->fapiRequest("v1/symbolConfig", 'GET', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the rate limit details
     * @throws \Exception
     */
    public function futuresOrderRateLimit(array $params = [])
    {
        return $this->fapiRequest("v1/rateLimit/order", 'GET', $params, true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the leverage details
     * @throws \Exception
     */
    public function futuresLeverages($symbol = null, array $params = [])
    {
        $request = [];
        if ($symbol) {
            $request['symbol'] = $symbol;
        }

        return $this->fapiRequest("v1/leverageBracket", 'GET', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the income details
     * @throws \Exception
     */
    public function futuresLedger($symbol = null, $incomeType = null, $startTime = null, $endTime = null, $limit = null, $page = null, array $params = [])
    {
        $request = [];
        if ($symbol) {
            $request['symbol'] = $symbol;
        }
        if ($incomeType) {
            $request['incomeType'] = $incomeType;
        }
        if ($startTime) {
            $request['startTime'] = $startTime;
        }
        if ($endTime) {
            $request['endTime'] = $endTime;
        }
        if ($limit) {
            $request['limit'] = $limit;
        }
        if ($page) {
            $request['page'] = $page;
        }

        return $this->fapiRequest("v1/income", 'GET', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the trading status details
     * @throws \Exception
     */
    public function futuresTradingStatus($symbol = null, array $params = [])
    {
        $request = [];
        if ($symbol) {
            $request['symbol'] = $symbol;
        }

        return $this->fapiRequest("v1/apiTradingStatus", 'GET', array_merge($request, $params), true);
    }

    /**
     * futuresDownloadId
     * helper for other metods for getting download id
     */
    protected function futuresDownloadId($startTime, $endTime, ?array $params = null, string $url = '')
    {
        $request = [
            'startTime' => $startTime,
            'endTime' => $endTime,
        ];

        return $this->fapiRequest($url, 'GET', array_merge($request, $params), true);
    }

    /**
     * futuresDownloadLinkByDownloadId
     * helper for other metods for getting download link by download id
     */
    protected function futuresDownloadLinkByDownloadId(string $downloadId, ?array $params = null, string $url = '')
    {
        $request = [
            'downloadId' => $downloadId,
        ];

        return $this->fapiRequest($url, 'GET', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the response
     * @throws \Exception
     */
    public function futuresDownloadIdForTransactions(int $startTime, int $endTime, array $params = [])
    {
        return $this->futuresDownloadId($startTime, $endTime, $params, "v1/income/asyn");
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the download link
     * @throws \Exception
     */
    public function futuresDownloadTransactionsByDownloadId(string $downloadId, array $params = [])
    {
        return $this->futuresDownloadLinkByDownloadId($downloadId, $params, "v1/income/asyn/id");
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the response
     * @throws \Exception
     */
    public function futuresDownloadIdForOrders(int $startTime, int $endTime, array $params = [])
    {
        return $this->futuresDownloadId($startTime, $endTime, $params, "v1/order/asyn");
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the download link
     * @throws \Exception
     */
    public function futuresDownloadOrdersByDownloadId(string $downloadId, array $params = [])
    {
        return $this->futuresDownloadLinkByDownloadId($downloadId, $params, "v1/order/asyn/id");
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the response
     * @throws \Exception
     */
    public function futuresDownloadIdForTrades(int $startTime, int $endTime, array $params = [])
    {
        return $this->futuresDownloadId($startTime, $endTime, $params, "v1/trade/asyn");
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array with error message or the download link
     * @throws \Exception
     */
    public function futuresDownloadTradesByDownloadId(string $downloadId, array $params = [])
    {
        return $this->futuresDownloadLinkByDownloadId($downloadId, $params, "v1/trade/asyn/id");
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresFeeBurn(bool $flag, array $params = [])
    {
        $request = [
            'feeBurn' => $flag ? 'true' : 'false',
        ];

        return $this->fapiRequest("v1/feeBurn", 'POST', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function futuresFeeBurnStatus(array $params = [])
    {
        return $this->fapiRequest("v1/feeBurn", 'GET', $params, true);
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
    public function convertExchangeInfo($fromAsset = null, $toAsset = null, array $params = [])
    {
        $request = [];
        if ($fromAsset) {
            $request['fromAsset'] = $fromAsset;
        }
        if ($toAsset) {
            $request['toAsset'] = $toAsset;
        }
        return $this->fapiRequest("v1/convert/exchangeInfo", 'GET', array_merge($request, $params));
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function convertSend(string $fromAsset, string $toAsset, $fromAmount = null, $toAmount = null, $validTime = null, array $params = [])
    {
        $request = [
            'fromAsset' => $fromAsset,
            'toAsset' => $toAsset,
        ];
        if ($fromAmount) {
            $request['fromAmount'] = $fromAmount;
        } else if ($toAmount) {
            $request['toAmount'] = $toAmount;
        } else {
            throw new \Exception('convertSendRequest(): fromAmount or toAmount must be set');
        }
        if ($validTime) {
            $request['validTime'] = $validTime;
        }

        return $this->fapiRequest("v1/convert/getQuote", 'POST', array_merge($request, $params), true);
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
     * @param array  $params (optional)  An array of additional parameters that the API endpoint allows
     * - @param int  $params['recvWindow'] (optional) the time in milliseconds to wait for the response
     *
     * @return array containing the response
     * @throws \Exception
     */
    public function convertAccept(string $quoteId, array $params = [])
    {
        $request = [
            'quoteId' => $quoteId,
        ];
        return $this->fapiRequest("v1/convert/acceptQuote", 'POST', array_merge($request, $params), true);
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
    public function convertStatus($orderId = null, $quoteId = null, array $params = [])
    {
        $request = [];
        if ($orderId) {
            $request['orderId'] = $orderId;
        } else if ($quoteId) {
            $request['quoteId'] = $quoteId;
        } else {
            throw new \Exception('convertStatus(): orderId or quoteId must be set');
        }
        return $this->fapiRequest("v1/convert/orderStatus", 'GET', array_merge($request, $params), true);
    }
}
