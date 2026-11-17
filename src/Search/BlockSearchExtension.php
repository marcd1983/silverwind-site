<?php

namespace Antlion\SilverwindSite\Search;

use Page;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\Versioned\Versioned;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;
use Throwable;
use SilverStripe\View\Requirements_Backend;

/**
 * Makes block-built pages searchable.
 *
 * Silverstripe's site search only reads SiteTree.Content, which is empty on
 * pages built from Elemental blocks. This keeps a plain-text copy of a
 * page's blocks (elemental's getElementsForSearch()) in Page.SearchText,
 * with a FULLTEXT index, separately for the draft and live versions:
 *
 *   page saved / published        -> that version is refreshed
 *   block saved / published /
 *   unpublished / archived        -> its page's version is refreshed
 *
 * SiteSearchForm queries it alongside the usual page fields, and
 * SearchExcerpt() shows the matching passage in results.
 * `sake tasks:rebuild-search-text` refreshes every page.
 *
 * @property string $SearchText
 * @extends Extension<Page>
 */
class BlockSearchExtension extends Extension
{
    private static array $db = [
        'SearchText' => 'Text',
    ];

    /**
     * Page fields (or getters) also indexed, when a page type has them:
     * service summaries, store-location names, addresses and notes.
     */
    private static array $search_text_fields = ['Summary', 'LocationName', 'FullAddress', 'Notes'];

    private static array $indexes = [
        'SearchText' => [
            'type' => 'fulltext',
            'columns' => ['SearchText'],
        ],
    ];

    public function updateCMSFields($fields): void
    {
        $fields->removeByName('SearchText');
    }

    public function onAfterWrite(): void
    {
        static::refresh($this->getOwner(), Versioned::get_stage() ?: Versioned::DRAFT);
    }

    public function onAfterPublish(): void
    {
        static::refresh($this->getOwner(), Versioned::LIVE);
    }

    /**
     * Recomputes one version's SearchText and stores it with a direct
     * UPDATE (no write(), so no new version and no write hooks).
     */
    public static function refresh(DataObject $page, string $stage): void
    {
        if (!$page instanceof Page || !$page->isInDB()) {
            return;
        }

        $text = Versioned::withVersionedMode(function () use ($page, $stage) {
            Versioned::set_stage($stage);
            /** @var Page|null $version */
            $version = Page::get()->byID($page->ID);

            return $version ? static::textFor($version) : null;
        });

        if ($text === null) {
            return; // e.g. not published yet
        }

        $table = $page->getSchema()->tableName(Page::class) . ($stage === Versioned::LIVE ? '_' . Versioned::LIVE : '');
        SQLUpdate::create("\"{$table}\"", ['"SearchText"' => $text], ['"ID"' => $page->ID])->execute();
    }

    /** Plain text of the page's blocks, plus anything extensions add */
    private static function textFor(Page $page): string
    {
        $text = '';
        if ($page->hasMethod('ElementalArea')) {
            $text = static::blockText($page);
        }

        foreach ((array) $page->config()->get('search_text_fields') as $field) {
            if ($page->hasField($field) || $page->hasMethod('get' . $field)) {
                $text .= ' ' . strip_tags((string) $page->obj($field));
            }
        }

        $page->extend('updateSearchText', $text);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5)) ?? '');
    }

    /**
     * Each block's text, like elemental's getElementsForSearch(), but one
     * block that can't render outside a request (e.g. a form needing a
     * session) is skipped instead of losing the whole page.
     */
    private static function blockText(Page $page): string
    {
        $area = $page->ElementalArea();
        if (!$area || !$area->exists()) {
            return '';
        }

        $themes = SSViewer::get_themes();
        $backend = Requirements::backend();
        // Front-end templates, and keep any CSS/JS they register out of this response
        SSViewer::set_themes(SSViewer::config()->get('themes'));
        Requirements::set_backend(Requirements_Backend::create());

        $parts = [];
        try {
            foreach ($area->Elements() as $element) {
                if (!$element->getSearchIndexable()) {
                    continue;
                }
                try {
                    $parts[] = $element->getContentForSearchIndex();
                } catch (Throwable $e) {
                    Injector::inst()->get(LoggerInterface::class)->warning(sprintf(
                        'Search text: skipped block #%d (%s) on page #%d: %s',
                        $element->ID,
                        $element->ClassName,
                        $page->ID,
                        $e->getMessage()
                    ));
                }
            }
        } finally {
            SSViewer::set_themes($themes);
            Requirements::set_backend($backend);
        }

        return implode(' ', array_filter($parts));
    }

    /**
     * A short passage for search results, around the search words where
     * possible: the meta description, else the block text, else Content.
     */
    public function SearchExcerpt($query = null): ?DBHTMLText
    {
        $owner = $this->getOwner();
        $source = $owner->MetaDescription ?: $owner->SearchText ?: strip_tags((string) $owner->Content);
        if (!$source) {
            return null;
        }

        $summary = DBField::create_field('Text', $source)->ContextSummary(220, (string) $query);

        return DBField::create_field('HTMLFragment', $summary);
    }
}
