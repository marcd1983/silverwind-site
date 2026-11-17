<?php

namespace Antlion\SilverwindSite\Search;

use Page;
use SilverStripe\CMS\Search\SearchForm;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\TextField;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\ORM\DB;

/**
 * Site search over page fields and block text (Page.SearchText, see
 * BlockSearchExtension), using MySQL FULLTEXT in boolean mode:
 *
 *   - each word matches as a prefix ("patio" finds "patios")
 *   - any word can match; pages matching more words rank higher
 *   - title / menu title / content / meta description matches count
 *     three times as much as block text
 *   - hidden-from-search pages and pages the visitor can't view are left out
 */
class SiteSearchForm extends SearchForm
{
    private const string PAGE_FIELDS = '"SiteTree"."Title", "SiteTree"."MenuTitle", "SiteTree"."Content", "SiteTree"."MetaDescription"';

    /** Words shorter than InnoDB's default ft_min_token_size (3) can't match */
    private const int MIN_WORD_LENGTH = 3;

    private const int MAX_WORDS = 8;

    /**
     * The search form for a page controller, styled for the Silverwind
     * theme (a daisyUI "join" of field and button). Use it from the site's
     * PageController::SearchForm(), which overrides the one
     * FulltextSearchable adds.
     */
    public static function forController(RequestHandler $controller): static
    {
        $request = $controller->getRequest();

        $fields = FieldList::create(
            TextField::create('Search', false, (string) $request?->getVar('Search'))
                ->setAttribute('type', 'search')
                ->setAttribute('placeholder', _t(__CLASS__ . '.Placeholder', 'Search...'))
                ->setAttribute('aria-label', _t(__CLASS__ . '.Label', 'Search'))
                ->addExtraClass('join-item')
        );
        $actions = FieldList::create(
            FormAction::create('results', _t(__CLASS__ . '.Button', 'Search'))
                ->setAttribute('type', 'submit')
                ->addExtraClass('btn btn-primary join-item')
        );

        return static::create($controller, 'SearchForm', $fields, $actions);
    }

    public function getResults(): PaginatedList
    {
        $request = $this->getRequestHandler()->getRequest();
        $boolean = static::booleanQuery((string) $request->requestVar('Search'));

        $results = ArrayList::create();
        if ($boolean !== '') {
            $quoted = DB::get_conn()->quoteString($boolean);
            $pageMatch = 'MATCH(' . self::PAGE_FIELDS . ") AGAINST ({$quoted} IN BOOLEAN MODE)";
            $blockMatch = "MATCH(\"Page\".\"SearchText\") AGAINST ({$quoted} IN BOOLEAN MODE)";

            $results = Page::get()
                ->filter('ShowInSearch', true)
                ->where("({$pageMatch}) OR ({$blockMatch})")
                ->orderBy("({$pageMatch} * 3 + {$blockMatch}) DESC, \"SiteTree\".\"Title\" ASC")
                ->filterByCallback(fn (Page $page) => $page->canView());
        }

        return PaginatedList::create($results, $request)->setPageLength($this->getPageLength());
    }

    /**
     * Visitor input to a safe boolean-mode query: letters/digits only, each
     * word with a trailing * (prefix match), at most MAX_WORDS words.
     */
    public static function booleanQuery(string $input): string
    {
        $clean = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $input) ?? '';
        $words = array_filter(
            preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fn (string $word) => mb_strlen($word) >= self::MIN_WORD_LENGTH
        );

        $words = array_slice(array_values(array_unique(array_map('mb_strtolower', $words))), 0, self::MAX_WORDS);

        return implode(' ', array_map(fn (string $word) => $word . '*', $words));
    }
}
