<?php

namespace Flamix\Conversions;

use Exception;

/** Reads the visitor's analytics cookies into a UID and sends conversions to the Flamix Conversion APP. */
class Conversion
{
    private static self $instances;

    private static string $url = 'https://conversion.app.flamix.solutions';

    private static string $code;

    private static string $domain;

    private static int $connectTimeout = 5;

    private static int $timeout = 10;

    /** Singleton: use getInstance(). */
    protected function __construct() {}

    /** Singleton: cloning is not allowed. */
    protected function __clone() {}

    /** @throws \Exception always: the singleton cannot be unserialized. */
    public function __wakeup()
    {
        throw new Exception("Can't unserialize a singleton!");
    }

    public static function getInstance(): self
    {
        if (empty(self::$instances)) {
            self::$instances = new static;
        }

        return self::$instances;
    }

    public static function setCode(string $code): self
    {
        self::$code = $code;

        return self::getInstance();
    }

    public static function setDomain(string $domain): self
    {
        self::$domain = $domain;

        return self::getInstance();
    }

    /**
     * Send one conversion; price is sent only together with a currency.
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

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new Exception("Conversion request failed: {$error}");
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $arResponse = json_decode($response, true);

        if (is_array($arResponse) && ($arResponse['status'] ?? '') === 'error') {
            throw new Exception($arResponse['msg'] ?? 'Unknown error from the conversion service.');
        }

        if ($status >= 400) {
            throw new Exception("Conversion request failed: HTTP {$status}");
        }
    }

    /**
     * Send every cookie identifier as its own conversion; errors are thrown together after all are tried.
     *
     * @return array<string, string>|false
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
     * The visitor's identifiers keyed by cookie name.
     *
     * @return array<string, string>
     */
    public static function getFromCookie(): array
    {
        $cookie = static function (string $name): string {
            // name[]=... arrives as an array, and every caller below expects a string.
            return is_string($_COOKIE[$name] ?? null) ? $_COOKIE[$name] : '';
        };

        if (! empty($cookie('_ym_uid'))) {
            $return['_ym_uid'] = $cookie('_ym_uid');
        }

        if (! empty($cookie('_ga'))) {
            $return['_ga'] = $cookie('_ga');

            if (! empty($cookie('gclid'))) {
                $return['_ga'] .= '|GCLID.' . $cookie('gclid');
            } elseif (! empty($cookie('_gcl_aw'))) {
                // [\w-]: gclids contain '-', and \w alone cuts them there.
                preg_match('/GCL\.\d+\.([\w-]+)/', $cookie('_gcl_aw'), $matches);

                if (! empty($matches[1])) {
                    $return['_ga'] .= '|GCLID.' . $matches[1];
                }
            }
        }

        if (! empty($cookie('_fbp'))) {
            $return['_fbp'] = $cookie('_fbp');
        }

        if (! empty($cookie('_ttp'))) {
            $return['_ttp'] = $cookie('_ttp');

            if (! empty($cookie('ttclid'))) {
                $return['_ttp'] .= '|TTCLID.' . $cookie('ttclid');
            }
        }

        return $return ?? [];
    }

    /**
     * The identifiers joined with ';', or false without any.
     *
     * @return string|false
     */
    public static function getPreparedUID()
    {
        $metrics = self::getFromCookie();

        return empty($metrics) ? false : implode(';', $metrics);
    }

    /**
     * A hidden input holding getPreparedUID(), or false without any identifier.
     *
     * @return string|false
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
