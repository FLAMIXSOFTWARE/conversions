<?php

namespace Flamix\Conversions;

use Exception;

class Conversion
{
    private static self $instances;
    private static string $url = 'https://conversion.app.flamix.solutions';
    private static string $code;
    private static string $domain;

    protected function __construct(){}
    protected function __clone(){}
    public function __wakeup()
    {
        throw new Exception("Can't serialize a singleton!");
    }

    public static function getInstance(): self
    {
        if (empty(self::$instances)) {
            self::$instances = new static;
        }

        return self::$instances;
    }

    /**
     * Set your API code.
     *
     * @param string $code
     * @return self
     */
    public static function setCode(string $code): self
    {
        self::$code = $code;
        return self::$instances;
    }

    /**
     * Set your DOMAIN (for auth).
     *
     * @param string $domain
     * @return self
     */
    public static function setDomain(string $domain): self
    {
        self::$domain = $domain;
        return self::$instances;
    }

    /**
     * Send data to App.
     *
     * @param string $uid User Id
     * @param float $price
     * @param string $currency
     * @throws \Exception
     */
    public static function add(string $uid, float $price = 0, string $currency = '')
    {
        $post = ['uid' => $uid];

        if (empty(self::$code)) {
            throw new Exception("Set your API code with setCode('YOUR_CODE')");
        }

        if (empty(self::$domain)) {
            throw new Exception("Set your DOMAIN which you use in APP with setDomain('YOUR_DOMAIN')");
        }

        $post['DOMAIN'] = self::$domain;

        if ($price > 0 && !empty($currency)) {
            $post['price'] = $price;
            $post['currency'] = $currency;
        }

        $ch = curl_init(self::$url . '/api/conversion/add/' . self::$code . '/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTREDIR, 3);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

        $response = curl_exec($ch);
        $arResponse = json_decode($response, true);

        if (is_array($arResponse) && $arResponse['status'] == 'error') {
            throw new Exception($arResponse['msg']);
        }

        curl_close($ch);
    }


    /**
     * Check COOKIE and if its isset - send to service.
     *
     * @param float $price
     * @param string $currency
     * @return array|bool
     * @throws \Exception
     */
    public static function addFromCookie(float $price = 0, string $currency = '')
    {
        $metrics = self::getFromCookie();
        if (!$metrics) {
            return false;
        }

        foreach ($metrics as $metric) {
            self::add($metric, $price, $currency);
        }

        return $metrics;
    }

    /**
     * Return USER_ID as array
     *
     * @return array
     */
    public static function getFromCookie(): array
    {
        if (!empty($_COOKIE['_ym_uid'] ?? null)) {
            $return['_ym_uid'] = $_COOKIE['_ym_uid'];
        }

        if (!empty($_COOKIE['_ga'] ?? null)) {
            $return['_ga'] = $_COOKIE['_ga'];

            // Add GCLID if exist.
            if (!empty($_COOKIE['gclid'] ?? null)) {
                $return['_ga'] .= '|GCLID.' . $_COOKIE['gclid'];
            } else if (!empty($_COOKIE['_gcl_aw'] ?? null)) {
                preg_match('/GCL\.\d+\.(\w+)/', $_COOKIE['_gcl_aw'], $matches);
                if (!empty($matches[1] ?? null)) {
                    $return['_ga'] .= '|GCLID.' . $matches[1];
                }
            }
        }

        if (!empty($_COOKIE['_fbp'] ?? null)) {
            $return['_fbp'] = $_COOKIE['_fbp'];
        }

        return $return ?? [];
    }

    /**
     * Prepared UID form COOKIE in standard format.
     *
     * @return bool|string
     */
    public static function getPreparedUID()
    {
        $metrics = self::getFromCookie();
        return empty($metrics) ? false : implode(';', $metrics);
    }

    /**
     * Create input to use on site.
     *
     * @param string $name
     * @return bool|string
     */
    public static function getInput(string $name = 'UF_CRM_FX_CONVERSION')
    {
        $metrics = self::getPreparedUID();
        return empty($metrics) ? false : "<input type='hidden' name='{$name}' value='$metrics' />";
    }
}