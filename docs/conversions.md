# Conversions

Sends a conversion to the [Flamix Conversion APP](https://conversion.app.flamix.solutions). Both `setCode()` and `setDomain()` are required, every failure is an `Exception`.

## PHP

Every identifier from the cookies, see [identifiers.md](identifiers.md):

```php
use Flamix\Conversions\Conversion;

try {
    Conversion::setCode('YOUR_CODE')->setDomain('example.com')->addFromCookie();
    // false without cookies, otherwise the identifiers that were sent
} catch (Exception $e) {
    // one request per identifier: all are tried, then the messages are thrown joined with '; '
}
```

One UID, with a price:

```php
Conversion::setCode('YOUR_CODE')->setDomain('example.com')->add('UID', 150, 'USD');
Conversion::getInstance()->addFromCookie(150, 'USD'); // code and domain are static, set once per request
```

```php
Conversion::add('UID', 150);   // no currency: the price is not sent
```

Throws on a missing code or domain, on a cURL failure (5 s connect, 10 s total) and on a `{"status": "error", "msg": "..."}` response.
