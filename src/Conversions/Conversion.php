<?php

namespace Flamix\Conversions;

use Exception;

/**
 * Conversion client for the Flamix Conversion APP.
 *
 * Collects analytics identifiers from the visitor's cookies (Google Analytics,
 * Yandex Metrika, Facebook Pixel, Google Ads click id) and sends them to the
 * Flamix Conversion service so a lead can be matched back to its ad source.
 *
 * @see https://conversion.app.flamix.solutions
 */
class Conversion
{
    /**
     * The shared singleton instance.
     *
     * @var self
     */
    private static self $instances;

    /**
     * The base URL of the Conversion service.
     *
     * @var string
     */
    private static string $url = 'https://conversion.app.flamix.solutions';

    /**
     * The API code used to authenticate requests.
     *
     * @var string
     */
    private static string $code;

    /**
     * The domain registered in the APP (used for auth).
     *
     * @var string
     */
    private static string $domain;

    /**
     * Number of seconds to wait while trying to connect.
     *
     * @var int
     */
    private static int $connectTimeout = 5;

    /**
     * Maximum number of seconds to allow the whole request to run.
     *
     * @var int
     */
    private static int $timeout = 10;

    /**
     * Prevent direct construction — use getInstance() instead.
     *
     * @return void
     */
    protected function __construct() {}

    /**
     * Prevent cloning of the singleton.
     *
     * @return void
     */
    protected function __clone() {}

    /**
     * Prevent unserialization of the singleton.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function __wakeup()
    {
        throw new Exception("Can't serialize a singleton!");
    }

    /**
     * Get the shared singleton instance.
     *
     * @return self
     */
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
     * @param  string  $code
     * @return self
     */
    public static function setCode(string $code): self
    {
        self::$code = $code;

        return self::getInstance();
    }

    /**
     * Set your DOMAIN (for auth).
     *
     * @param  string  $domain
     * @return self
     */
    public static function setDomain(string $domain): self
    {
        self::$domain = $domain;

        return self::getInstance();
    }

    /**
     * Send a single conversion to the APP.
     *
     * @param  string  $uid  User Id
     * @param  float  $price
     * @param  string  $currency
     * @return void
     *
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

        if ($price > 0 && ! empty($currency)) {
            $post['price'] = $price;
            $post['currency'] = $currency;
        }

        $ch = curl_init(self::$url . '/api/conversion/add/' . rawurlencode(self::$code) . '/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTREDIR, 3);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::$connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::$timeout);

        $response = curl_exec($ch);

        // Bail out on a transport-level failure (timeout, DNS, refused connection, ...).
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new Exception("Conversion request failed: {$error}");
        }

        curl_close($ch);

        $arResponse = json_decode($response, true);

        if (is_array($arResponse) && ($arResponse['status'] ?? '') === 'error') {
            throw new Exception($arResponse['msg'] ?? 'Unknown error from the conversion service.');
        }
    }

    /**
     * Check the COOKIE and, if present, send every metric to the service.
     *
     * Every metric is attempted even if one of them fails; any errors are
     * collected and thrown together once all of them have been processed.
     *
     * @param  float  $price
     * @param  string  $currency
     * @return array|bool
     *
     * @throws \Exception
     */
    public static function addFromCookie(float $price = 0, string $currency = '')
    {
        $metrics = self::getFromCookie();

        if (! $metrics) {
            return false;
        }

        $errors = [];

        foreach ($metrics as $metric) {
            try {
                self::add($metric, $price, $currency);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (! empty($errors)) {
            throw new Exception(implode('; ', $errors));
        }

        return $metrics;
    }

    /**
     * Return the collected USER_ID metrics as an array.
     *
     * @return array
     */
    public static function getFromCookie(): array
    {
        if (! empty($_COOKIE['_ym_uid'] ?? null)) {
            $return['_ym_uid'] = $_COOKIE['_ym_uid'];
        }

        if (! empty($_COOKIE['_ga'] ?? null)) {
            $return['_ga'] = $_COOKIE['_ga'];

            // Append the GCLID (Google Ads click id) when it is available.
            if (! empty($_COOKIE['gclid'] ?? null)) {
                $return['_ga'] .= '|GCLID.' . $_COOKIE['gclid'];
            } elseif (! empty($_COOKIE['_gcl_aw'] ?? null)) {
                preg_match('/GCL\.\d+\.(\w+)/', $_COOKIE['_gcl_aw'], $matches);

                if (! empty($matches[1] ?? null)) {
                    $return['_ga'] .= '|GCLID.' . $matches[1];
                }
            }
        }

        if (! empty($_COOKIE['_fbp'] ?? null)) {
            $return['_fbp'] = $_COOKIE['_fbp'];
        }

        if (! empty($_COOKIE['_ttp'] ?? null)) {
            $return['_ttp'] = $_COOKIE['_ttp'];

            // Append the TTCLID (TikTok click id) when it is available.
            if (! empty($_COOKIE['ttclid'] ?? null)) {
                $return['_ttp'] .= '|TTCLID.' . $_COOKIE['ttclid'];
            }
        }

        return $return ?? [];
    }

    /**
     * Return the prepared UID in the standard format.
     *
     * @return bool|string
     */
    public static function getPreparedUID()
    {
        $metrics = self::getFromCookie();

        return empty($metrics) ? false : implode(';', $metrics);
    }

    /**
     * Create a hidden input with the UID to use on the site.
     *
     * @param  string  $name
     * @return bool|string
     */
    public static function getInput(string $name = 'UF_CRM_FX_CONVERSION')
    {
        $metrics = self::getPreparedUID();

        if (empty($metrics)) {
            return false;
        }

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeMetrics = htmlspecialchars($metrics, ENT_QUOTES, 'UTF-8');

        return "<input type='hidden' name='{$safeName}' value='{$safeMetrics}' />";
    }
}
