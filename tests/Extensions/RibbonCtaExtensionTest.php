<?php

namespace Antlion\SilverwindSite\Tests\Extensions;

use Antlion\SilverwindSite\Models\RibbonCta;
use DNADesign\Elemental\Models\ElementalArea;
use Antlion\SilverwindSite\Elements\ElementRibbonCta;
use Page;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\SiteConfig\SiteConfig;

class RibbonCtaExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    private RibbonCta $siteWide;

    private RibbonCta $other;

    protected function setUp(): void
    {
        parent::setUp();
        DBDatetime::set_mock_now('2026-06-15 12:00:00');

        $this->siteWide = RibbonCta::create(['Title' => 'Site-wide']);
        $this->siteWide->write();
        $this->other = RibbonCta::create(['Title' => 'Other']);
        $this->other->write();

        $config = SiteConfig::current_site_config();
        $config->FooterRibbonID = $this->siteWide->ID;
        $config->write();
    }

    public function testPagesInheritTheSiteWideRibbon(): void
    {
        $page = $this->page(['FooterRibbonMode' => 'inherit']);

        $this->assertSame($this->siteWide->ID, $page->getCurrentFooterRibbon()?->ID);
        $this->assertStringContainsString('Site-wide', (string) $page->BeforeFooter());
    }

    public function testPagesCanTurnItOff(): void
    {
        $page = $this->page(['FooterRibbonMode' => 'none']);

        $this->assertNull($page->getCurrentFooterRibbon());
        $this->assertNull($page->BeforeFooter());
    }

    public function testPagesCanShowADifferentRibbon(): void
    {
        $page = $this->page(['FooterRibbonMode' => 'custom', 'FooterRibbonID' => $this->other->ID]);

        $this->assertSame($this->other->ID, $page->getCurrentFooterRibbon()?->ID);
    }

    public function testRibbonOutsideItsDatesIsHidden(): void
    {
        $this->siteWide->EndDate = '2026-06-01';
        $this->siteWide->write();

        $this->assertNull($this->page()->getCurrentFooterRibbon());
    }

    public function testNotRepeatedWhenThePageAlreadyHasItAsABlock(): void
    {
        $page = $this->page();
        $area = ElementalArea::get()->byID($page->ElementalAreaID) ?? ElementalArea::create();
        if (!$area->isInDB()) {
            $area->write();
            $page->ElementalAreaID = $area->ID;
            $page->write();
        }
        ElementRibbonCta::create(['ParentID' => $area->ID, 'RibbonID' => $this->siteWide->ID])->write();

        $this->assertNull($page->getCurrentFooterRibbon());
    }

    private function page(array $fields = []): Page
    {
        $page = Page::create(['Title' => 'Test page', ...$fields]);
        $page->write();

        return $page;
    }
}
