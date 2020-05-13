# conversionapp
Cover to API - https://conversion.app.flamix.solutions

## Usage

```php
try {
    \Flamix\Conversions\Conversion::getInstance()->setCode('YOR_CODE')->setDomain('example.com')->addFromCookie();
} catch (Exception $e) {
    //Handle ERROR
    $e->getMessage();
}
```
