<?php

namespace Antlion\SilverwindSite\Tests\Models;

use Antlion\SilverwindSite\Models\RibbonCta;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\SiteConfig\SiteConfig;

class RibbonCtaTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        DBDatetime::set_mock_now('2026-06-15 12:00:00');
    }

    public function testIsCurrentWithoutDatesAlwaysShows(): void
    {
        $ribbon = RibbonCta::create(['Title' => 'Always']);

        $this->assertTrue($ribbon->isCurrent());
        $this->assertSame('Showing', $ribbon->getStatusLabel());
        $this->assertSame('Always', $ribbon->getScheduleSummary());
    }

    public function testDatesAreInclusive(): void
    {
        $this->assertTrue(RibbonCta::create(['StartDate' => '2026-06-15'])->isCurrent(), 'starts today');
        $this->assertTrue(RibbonCta::create(['EndDate' => '2026-06-15'])->isCurrent(), 'ends today');
        $this->assertTrue(RibbonCta::create(['StartDate' => '2026-06-01', 'EndDate' => '2026-06-30'])->isCurrent());
    }

    public function testScheduledAndExpired(): void
    {
        $future = RibbonCta::create(['StartDate' => '2026-06-16']);
        $past = RibbonCta::create(['EndDate' => '2026-06-14']);

        $this->assertFalse($future->isCurrent());
        $this->assertSame('Scheduled', $future->getStatusLabel());
        $this->assertFalse($past->isCurrent());
        $this->assertSame('Expired', $past->getStatusLabel());
    }

    public function testEndBeforeStartIsInvalid(): void
    {
        $ribbon = RibbonCta::create(['Title' => 'Backwards', 'StartDate' => '2026-06-20', 'EndDate' => '2026-06-10']);

        $this->assertFalse($ribbon->validate()->isValid());
    }

    public function testOverlayOpacityIsClamped(): void
    {
        $ribbon = RibbonCta::create(['Title' => 'Overlay', 'OverlayOpacity' => 250]);
        $ribbon->write();

        $this->assertSame(100, (int) $ribbon->OverlayOpacity);
    }

    public function testSiteFooterCheckboxSetsAndClearsTheSiteWideRibbon(): void
    {
        $a = RibbonCta::create(['Title' => 'A']);
        $a->write();
        $b = RibbonCta::create(['Title' => 'B']);
        $b->write();

        $a->IsSiteFooterRibbon = true;
        $a->write();
        $this->assertSame($a->ID, $this->footerRibbonID());
        $this->assertTrue($a->IsSiteFooterRibbon);

        // Ticking another replaces it
        $b->IsSiteFooterRibbon = true;
        $b->write();
        $this->assertSame($b->ID, $this->footerRibbonID());
        $this->assertFalse(RibbonCta::get()->byID($a->ID)->IsSiteFooterRibbon);

        // Saving A unticked leaves B alone
        $a->IsSiteFooterRibbon = false;
        $a->write();
        $this->assertSame($b->ID, $this->footerRibbonID());

        // Unticking the current one clears it, even with no other change
        $b->IsSiteFooterRibbon = false;
        $b->write();
        $this->assertSame(0, $this->footerRibbonID());
    }

    public function testDeletingTheSiteWideRibbonClearsIt(): void
    {
        $ribbon = RibbonCta::create(['Title' => 'Footer']);
        $ribbon->IsSiteFooterRibbon = true;
        $ribbon->write();

        $ribbon->delete();

        $this->assertSame(0, $this->footerRibbonID());
    }

    public function testDefaultBackgroundOnlyWithoutColourOrImage(): void
    {
        $this->assertTrue(RibbonCta::create()->getUsesDefaultBackground());
        $this->assertFalse(RibbonCta::create(['BackgroundColor' => '#c8963e'])->getUsesDefaultBackground());
    }

    private function footerRibbonID(): int
    {
        return (int) SiteConfig::get()->first()->FooterRibbonID;
    }
}
