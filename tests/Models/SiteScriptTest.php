<?php

namespace Antlion\SilverwindSite\Tests\Models;

use Antlion\SilverwindSite\Models\SiteScript;
use SilverStripe\Dev\SapphireTest;

class SiteScriptTest extends SapphireTest
{
    public function testScriptsBecomeInertWithTheirSrcAndTypeKept(): void
    {
        $code = '<script async src="https://example.com/a.js"></script><script type="module">go()</script>';

        $inert = SiteScript::makeInert($code, 'Analytics');

        $this->assertStringContainsString(
            '<script type="text/plain" data-cookie-consent="Analytics" async data-src="https://example.com/a.js">',
            $inert
        );
        $this->assertStringContainsString('<script type="text/plain" data-cookie-consent="Analytics" data-type="module">go()', $inert);
        $this->assertStringNotContainsString(' src=', $inert);
    }

    public function testIframesImagesAndLinksDontLoad(): void
    {
        $inert = SiteScript::makeInert(
            '<iframe src="https://x.example/f"></iframe><img src="https://x.example/p.gif"><link rel="stylesheet" href="https://x.example/s.css">',
            'Marketing'
        );

        $this->assertStringContainsString('<iframe data-cookie-consent="Marketing" data-src="https://x.example/f">', $inert);
        $this->assertStringContainsString('<img data-cookie-consent="Marketing" data-src="https://x.example/p.gif">', $inert);
        $this->assertStringContainsString('data-href="https://x.example/s.css"', $inert);
        $this->assertStringNotContainsString(' src=', $inert);
        $this->assertStringNotContainsString(' href=', $inert);
    }

    public function testNoscriptFallbacksAreDropped(): void
    {
        $inert = SiteScript::makeInert('<script>a()</script><noscript><img src="https://x.example/pixel"></noscript>', 'Analytics');

        $this->assertStringNotContainsString('noscript', $inert);
        $this->assertStringNotContainsString('pixel', $inert);
    }

    public function testGroupNameIsEscaped(): void
    {
        $this->assertStringContainsString(
            'data-cookie-consent="a&quot;b"',
            SiteScript::makeInert('<script>x()</script>', 'a"b')
        );
    }

    public function testNecessaryAlwaysLoadsAndOthersWaitForConsent(): void
    {
        $code = '<script>x()</script>';

        $this->assertSame($code, SiteScript::create(['Code' => $code, 'ConsentGroup' => 'Necessary'])->renderCode());
        $this->assertStringContainsString('type="text/plain"', SiteScript::create(['Code' => $code, 'ConsentGroup' => 'Analytics'])->renderCode());
        $this->assertStringContainsString(
            'type="text/plain"',
            SiteScript::create(['Code' => $code, 'ConsentGroup' => 'NotAConfiguredGroup'])->renderCode(),
            'a group that is no longer configured stays off'
        );
    }
}
