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
     * @param string $code
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
     * @throws \Exception
     */
    public static function addFromCookie( $price = 0, $currency = '' )
    {
        /*
         * Add Yandex uid
         */
        if(isset($_COOKIE['_ym_uid']) && !empty($_COOKIE['_ym_uid']))
            self::add($_COOKIE['_ym_uid'], $price, $currency);

        /*
         * Add Google uid
         */
        if(isset($_COOKIE['_ga']) && !empty($_COOKIE['_ga']))
            self::add($_COOKIE['_ga'], $price, $currency);
    }
}
