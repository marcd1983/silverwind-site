<?php

namespace Antlion\SilverwindSite\Tests\Search;

use Antlion\SilverwindSite\Search\BlockSearchExtension;
use Antlion\SilverwindSite\Search\SiteSearchForm;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\ElementContent;
use Page;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Versioned\Versioned;

class SiteSearchTest extends FunctionalTest
{
    protected $usesDatabase = true;

    // InnoDB FULLTEXT indexes only see committed rows, so no per-test transaction
    protected $usesTransactions = false;

    public function testBooleanQueryIsSanitisedAndPrefixed(): void
    {
        $this->assertSame('patio* stone*', SiteSearchForm::booleanQuery('patio stone'));
        $this->assertSame('patio*', SiteSearchForm::booleanQuery('+patio -"a" (b)*'), 'operators and short words go');
        $this->assertSame('script* alert*', SiteSearchForm::booleanQuery('<script>alert(1)</script>'), 'deduplicated');
        $this->assertSame('patio*', SiteSearchForm::booleanQuery('Patio PATIO'), 'case-insensitive');
        $this->assertSame('', SiteSearchForm::booleanQuery('a b'));
        $this->assertCount(8, explode(' ', SiteSearchForm::booleanQuery(implode(' ', array_map(fn ($i) => "word{$i}", range(1, 20))))));
    }

    public function testBlockTextIsIndexedForDraftAndLive(): void
    {
        $page = $this->pageWithBlock('Patios', '<p>Natural stone laid on proper foundations.</p>');

        $this->assertStringContainsString('foundations', $this->searchText($page, Versioned::DRAFT));
        $this->assertSame('', $this->searchText($page, Versioned::LIVE), 'not published yet');

        $page->publishRecursive();
        $this->assertStringContainsString('foundations', $this->searchText($page, Versioned::LIVE));
    }

    public function testEditingABlockUpdatesItsPage(): void
    {
        $page = $this->pageWithBlock('Lighting', '<p>Warm uplighting.</p>');
        $block = ElementContent::get()->filter('ParentID', $page->ElementalAreaID)->first();

        $block->HTML = '<p>Low-voltage pathway lights.</p>';
        $block->write();

        $text = $this->searchText($page, Versioned::DRAFT);
        $this->assertStringContainsString('pathway', $text);
        $this->assertStringNotContainsString('uplighting', $text);
    }

    public function testSearchFindsBlockTextAndShowsAnExcerpt(): void
    {
        $this->pageWithBlock('Decking', '<p>Hardwood and composite decks built to last.</p>')->publishRecursive();
        $this->pageWithBlock('Unrelated', '<p>Nothing to see here.</p>')->publishRecursive();
        $hidden = $this->pageWithBlock('Hidden', '<p>Hardwood but hidden.</p>');
        $hidden->ShowInSearch = false;
        $hidden->write();
        $hidden->publishRecursive();

        $response = $this->get('/decking/SearchForm?Search=hardwood&action_results=Search');

        $this->assertSame(200, $response->getStatusCode());
        // Only the results list: page titles also appear in the navigation
        preg_match('#<ul class="mt-6 divide-y.*?</ul>#s', $response->getBody(), $list);
        $body = $list[0] ?? '';
        $this->assertStringContainsString('>Decking</a>', $body);
        $this->assertStringNotContainsString('>Unrelated</a>', $body);
        $this->assertStringNotContainsString('>Hidden</a>', $body, 'ShowInSearch is respected');
        $this->assertStringContainsString('<mark>Hardwood</mark>', $body);
    }

    private function pageWithBlock(string $title, string $html): Page
    {
        $page = Page::create(['Title' => $title]);
        $page->write();
        $area = ElementalArea::get()->byID($page->ElementalAreaID);
        if (!$area) {
            $area = ElementalArea::create();
            $area->write();
            $page->ElementalAreaID = $area->ID;
            $page->write();
        }
        ElementContent::create(['Title' => $title, 'HTML' => $html, 'ParentID' => $area->ID])->write();

        return $page;
    }

    private function searchText(Page $page, string $stage): string
    {
        return (string) Versioned::withVersionedMode(function () use ($page, $stage) {
            Versioned::set_stage($stage);
            return Page::get()->byID($page->ID)?->SearchText;
        });
    }
}
