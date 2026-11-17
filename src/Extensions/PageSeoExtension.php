<?php

namespace Antlion\SilverwindSite\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Assets\Image;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\CMS\Controllers\RootURLController;
use Antlion\SilverwindSite\Schema\Schema;
use Antlion\SilverwindSite\Schema\SchemaProvider;

class PageSeoExtension extends Extension
{
    private static $db = [];
    public function updateCMSFields(FieldList $fields): void
    {
    }

    public function SchemaJSON(): string
    {
        $page = $this->owner;
        $graph = [];
        $conf  = SiteConfig::current_site_config();
        $base  = rtrim(Director::absoluteBaseURL(), '/') . '/';
// 1) Organization (global, from SiteConfigExtension). hasMethod() rather
        //    than method_exists(): the method is added by an extension.
        $hasOrg = false;
        if ($conf && $conf->hasMethod('getOrganizationSchemaNode')) {
            $org = $conf->getOrganizationSchemaNode();
            if (is_array($org) && array_filter($org)) {
                $graph[] = $org;
                $hasOrg = true;
            }
        }

        // 2) WebSite. The search target is the real SearchForm results action
        //    (see SiteSearchForm), routed via the homepage.
        $searchBase = Controller::join_links($base, RootURLController::get_homepage_link(), 'SearchForm');
        $website = [
            '@type'        => 'WebSite',
            '@id'          => $base . '#website',
            'url'          => $base,
            'name'         => $conf?->Title ?: null,
            'potentialAction' => [
                '@type'        => 'SearchAction',
                'target'       => $searchBase . '?Search={query}&action_results=Search',
                'query-input'  => 'required name=query',
            ],
        ];
        if ($hasOrg) {
            $website['publisher'] = ['@id' => $base . '#org'];
        }
        $graph[] = $website;
// 3) Breadcrumbs (fallback if getBreadcrumbItems isn’t available)
        $items = [];
        $pos   = 0;
        if (method_exists($page, 'getBreadcrumbItems')) {
            $crumbs = $page->getBreadcrumbItems(20, null, true);
            if ($crumbs && $crumbs->count()) {
        // Ensure "Home" first
                $homeURL = $base;
                $first   = $crumbs->first();
                if (!$first || rtrim($first->AbsoluteLink(), '/') !== rtrim($homeURL, '/')) {
                    $items[] = ['@type' => 'ListItem','position' => ++$pos,'name' => 'Home','item' => $homeURL];
                }
                foreach ($crumbs as $node) {
                    $items[] = [
                    '@type'    => 'ListItem',
                    'position' => ++$pos,
                    'name'     => $node->Title,
                    'item'     => rtrim($node->AbsoluteLink(), '/') . '/',
                    ];
                }
            }
        } else {
        // Minimal fallback: Home + current page
            $items = [
                ['@type' => 'ListItem','position' => ++$pos,'name' => 'Home','item' => $base],
                ['@type' => 'ListItem','position' => ++$pos,'name' => $page->Title, 'item' => rtrim($page->AbsoluteLink(), '/') . '/'],
            ];
        }

        if (count($items) >= 2) {
            $graph[] = [
                '@type'           => 'BreadcrumbList',
                '@id'             => rtrim($page->AbsoluteLink(), '/') . '/#breadcrumbs',
                'itemListElement' => $items,
            ];
        }

        // 4) Let the page and Elemental blocks contribute extra nodes
        if (interface_exists(SchemaProvider::class)) {
            if ($page instanceof SchemaProvider) {
                $graph = array_merge($graph, (array) $page->provideSchema());
            }
            // ElementalPageExtension adds ElementalArea() (not Elements()), and
            // only via extension, so hasMethod() is needed to see it.
            if ($page->hasMethod('ElementalArea') && ($area = $page->ElementalArea()) && $area->exists()) {
                foreach ($area->Elements() as $el) {
                    if ($el instanceof SchemaProvider) {
                        $graph = array_merge($graph, (array) $el->provideSchema());
                    }
                }
            }
        }

        // 5) Extension hook
        $pageAbsURL = rtrim($page->AbsoluteLink(), '/') . '/';
        $page->extend('updateSchemaGraph', $graph, $pageAbsURL);
// 6) Clean + emit
        $graph = array_values(array_filter($graph, fn($n) => is_array($n) && array_filter($n)));
        return Schema::json([
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        ]);
    }
}
