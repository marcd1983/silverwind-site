<?php

namespace Antlion\SilverwindSite\Tests\Elements;

use Antlion\SilverwindSite\Elements\ElementServices;
use Antlion\SilverwindSite\Pages\ServiceHolderPage;
use Antlion\SilverwindSite\Pages\ServicePage;
use SilverStripe\Dev\SapphireTest;

class ElementServicesTest extends SapphireTest
{
    protected $usesDatabase = true;

    private array $services = [];

    protected function setUp(): void
    {
        parent::setUp();

        $holder = ServiceHolderPage::create(['Title' => 'Services']);
        $holder->write();
        foreach (['Design' => true, 'Paving' => true, 'Lighting' => false] as $title => $featured) {
            $page = ServicePage::create(['Title' => $title, 'ParentID' => $holder->ID, 'Featured' => $featured]);
            $page->write();
            $this->services[$title] = $page;
        }
    }

    public function testFeaturedAllAndChosen(): void
    {
        $block = ElementServices::create(['Source' => 'featured']);
        $this->assertEqualsCanonicalizing(['Design', 'Paving'], $block->getServiceList()->column('Title'));

        $block->Source = 'all';
        $this->assertCount(3, $block->getServiceList());

        $block->Source = 'selected';
        $block->write();
        $block->Services()->add($this->services['Lighting'], ['SortOrder' => 1]);
        $this->assertSame(['Lighting'], $block->getServiceList()->column('Title'));
    }

    public function testCarouselOptionsFollowTheSettings(): void
    {
        $block = ElementServices::create(['Source' => 'all', 'Appearance' => 'carousel', 'Loop' => true, 'Gap' => 'lg']);

        $this->assertTrue($block->IsCarousel());
        $options = $block->getSwiperOptions();
        $this->assertSame(36, $options['spaceBetween']);
        $this->assertSame(['slidesPerView' => 2.0], $options['breakpoints'][640]);
        $this->assertFalse($options['loop'], 'three services never fill three-wide slides plus one, so no loop');
        $this->assertJson($block->getSwiperOptionsJSON());
    }

    public function testCardHeadingSitsBelowTheBlockTitle(): void
    {
        $this->assertSame('h3', ElementServices::create(['Title' => 'What we do', 'ShowTitle' => true])->getCardHeading());
        $this->assertSame('h2', ElementServices::create(['Title' => 'Hidden', 'ShowTitle' => false])->getCardHeading());
    }
}
