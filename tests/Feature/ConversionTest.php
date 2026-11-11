<?php

namespace Flamix\Conversions\Tests\Feature;

use Flamix\Conversions\Conversion;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ConversionTest extends TestCase
{
    private array $cookies;

    private array $state = [];

    protected function setUp(): void
    {
        $this->cookies = $_COOKIE;
        $_COOKIE = [];

        foreach (['url', 'code', 'domain'] as $name) {
            $property = $this->property($name);
            // Uninitialized typed static: '' is what add() treats the same way.
            $this->state[$name] = $property->isInitialized() ? $property->getValue() : '';
        }
    }

    protected function tearDown(): void
    {
        $_COOKIE = $this->cookies;

        foreach ($this->state as $name => $value) {
            $this->property($name)->setValue(null, $value);
        }
    }

    public function test_every_identifier_is_collected_with_its_click_id(): void
    {
        $_COOKIE = [
            '_ttp' => 'ttp1',
            'ttclid' => 'tt-1',
            '_fbp' => 'fb.1.1598192425982.77587948',
            '_ga' => 'GA1.2.885407728.1598192418',
            'gclid' => 'Cj0KCQ',
            '_ym_uid' => '1559040249567571161',
        ];

        $this->assertSame([
            '_ym_uid' => '1559040249567571161',
            '_ga' => 'GA1.2.885407728.1598192418|GCLID.Cj0KCQ',
            '_fbp' => 'fb.1.1598192425982.77587948',
            '_ttp' => 'ttp1|TTCLID.tt-1',
        ], Conversion::getFromCookie());

        $this->assertSame(
            '1559040249567571161;GA1.2.885407728.1598192418|GCLID.Cj0KCQ;fb.1.1598192425982.77587948;ttp1|TTCLID.tt-1',
            Conversion::getPreparedUID()
        );
    }

    public function test_gclid_is_taken_from_the_google_ads_cookie_whole(): void
    {
        $_COOKIE = ['_ga' => 'GA1.2.1.2', '_gcl_aw' => 'GCL.1700000000.Cj0KCQ-ab_c'];

        $this->assertSame('GA1.2.1.2|GCLID.Cj0KCQ-ab_c', Conversion::getPreparedUID());
    }

    public function test_array_cookies_are_skipped(): void
    {
        $_COOKIE = ['_ga' => ['x'], '_ym_uid' => ['x'], '_fbp' => 'fb.1', '_ttp' => 'ttp1', 'ttclid' => ['x']];

        $this->assertSame(['_fbp' => 'fb.1', '_ttp' => 'ttp1'], Conversion::getFromCookie());

        $_COOKIE = ['_ga' => 'GA1.2.1.2', 'gclid' => ['x'], '_gcl_aw' => ['x']];

        $this->assertSame('GA1.2.1.2', Conversion::getPreparedUID());
    }

    public function test_a_visitor_without_cookies_gets_nothing(): void
    {
        $this->assertSame([], Conversion::getFromCookie());
        $this->assertFalse(Conversion::getPreparedUID());
        $this->assertFalse(Conversion::getInput());
        $this->assertFalse(Conversion::setCode('code')->setDomain('example.com')->addFromCookie());
    }

    public function test_the_hidden_input_is_escaped(): void
    {
        $_COOKIE = ['_fbp' => "fb.1'><script>"];

        $this->assertSame(
            "<input type='hidden' name='UF_CRM_FX_CONVERSION' value='fb.1&#039;&gt;&lt;script&gt;' />",
            Conversion::getInput()
        );
        $this->assertStringContainsString("name='a&#039;b'", Conversion::getInput("a'b"));
    }

    public function test_a_conversion_needs_a_code(): void
    {
        $this->expectExceptionMessage('setCode');

        Conversion::setCode('')->setDomain('example.com')->add('UID');
    }

    public function test_a_conversion_needs_a_domain(): void
    {
        $this->expectExceptionMessage('setDomain');

        Conversion::setCode('code')->setDomain('')->add('UID');
    }

    public function test_every_identifier_is_tried_before_the_errors_are_thrown(): void
    {
        $this->property('url')->setValue(null, 'http://127.0.0.1:1');
        $_COOKIE = ['_ym_uid' => '1', '_fbp' => 'fb.1'];

        $this->expectExceptionMessageMatches('/^Conversion request failed: .+; Conversion request failed: /');

        Conversion::setCode('code')->setDomain('example.com')->addFromCookie(150, 'USD');
    }

    private function property(string $name): ReflectionProperty
    {
        $property = new ReflectionProperty(Conversion::class, $name);
        // PHP 7.4/8.0 refuse private access without it.
        $property->setAccessible(true);

        return $property;
    }
}
