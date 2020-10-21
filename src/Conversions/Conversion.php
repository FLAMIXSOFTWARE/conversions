<?php

namespace Flamix\Conversions;

/**
* Conversion
*/
class Conversion
{
    private static $instances;
    private static $url = 'https://conversion.app.flamix.solutions';
    private static $code;
    private static $domain;


    protected function __construct() {}
    protected function __clone() {}
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize a singleton.");
    }


    public static function getInstance(): Conversion
    {
        if( empty(self::$instances) )
            self::$instances = new static;
        
        return self::$instances;
    }

    /**
     * Set your API code
     *
     * @param string $code
     * @return mixed
     */
    public static function setCode( string $code )
    {
        self::$code = $code;
        return self::$instances;
    }

    /**
     * Set your DOMAIN (for auth)
     *
     * @param string $domain
     * @return mixed
     */
    public static function setDomain( string $domain )
    {
        self::$domain = $domain;
        return self::$instances;
    }

    /**
     * Send data to App
     *
     * @param $uid User Id
     * @param int $price
     * @param string $currency
     * @throws \Exception
     */
    public static function add( $uid, $price = 0, $currency = '' )
    {
        if(!isset($uid))
            throw new \Exception();
        else
            $post = array( 'uid' => $uid );

        if(empty(self::$code))
            throw new \Exception('Set your API code whith setCode(\'YOUR_CODE\')');

        if(empty(self::$domain))
            throw new \Exception('Set your DOMAIN^ which you use in APP whith setDomain(\'YOUR_DOMAIN\')');
        $post['DOMAIN'] = self::$domain;

        if($price > 0 && !empty($currency)) {
            $post['price'] = $price;
            $post['currency'] = $currency;
        }

        $ch = curl_init(self::$url . '/api/conversion/add/' . self::$code . '/' );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTREDIR, 3);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

        $response = curl_exec($ch);
        $arResponse = json_decode($response, true);
        
        if(is_array($arResponse) && $arResponse['status'] == 'error')
            throw new \Exception($arResponse['msg']);

        curl_close($ch);
    }


    /**
     * Check COOKIE and if its isset - send to service
     *
     * @param int $price
     * @param string $currency
     * @return array|bool
     * @throws \Exception
     */
    public static function addFromCookie( $price = 0, $currency = '' )
    {
        $metrics = self::getFromCookie();
        if(!$metrics)
            return false;

        foreach ($metrics as $metric)
            self::add($metric, $price, $currency);

        return $metrics;
    }

    /**
     * Return USER_ID as array
     *
     * @return array|bool
     */
    public static function getFromCookie()
    {
        $return = array();

        if(isset($_COOKIE['_ym_uid']) && !empty($_COOKIE['_ym_uid']))
            $return['_ym_uid'] = $_COOKIE['_ym_uid'];

        if(isset($_COOKIE['_ga']) && !empty($_COOKIE['_ga']))
            $return['_ga'] = $_COOKIE['_ga'];

        if(isset($_COOKIE['_fbp']) && !empty($_COOKIE['_fbp']))
            $return['_fbp'] = $_COOKIE['_fbp'];

        if(empty($return))
            return false;

        return $return;
    }

    /**
     * Prepared UID form COOKIE in standard format
     *
     * @return bool|string
     */
    public static function getPreparedUID()
    {
        $metrics = self::getFromCookie();

        if(empty($metrics))
            return false;

        return implode(';', $metrics);
    }

    /**
     * Create input to use on site
     *
     * @param string $name
     * @return bool|string
     */
    public static function getInput( string $name = 'UF_CRM_FX_CONVERSION' )
    {
        $metrics = self::getPreparedUID();

        if(!$metrics || empty($metrics))
            return false;

        return "<input type='hidden' name='{$name}' value='$metrics' />";
    }
}
