<?php

namespace Antlion\SilverwindSite\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\LinkField\Form\MultiLinkField;
use SilverStripe\LinkField\Models\Link;

/**
 * Lets a top-level page choose which mega menu panel layout
 * (silverwind's Includes/Nav/MegaTabs, MegaList, MegaGallery or Dropdown)
 * it renders as when it appears as a Menu(1) item in the desktop mega
 * nav, how many columns the List/Gallery layouts lay their children out
 * in, (Tabs only) a shared set of promo/CTA links rendered once in that
 * panel, and (List only) whether to hide the trailing "view all" card
 * for the page itself. Only shown for top-level pages (ParentID == 0)
 * since that's the only place the header (Includes/Header.ss) reads these
 * fields -- child pages never render their own mega panel, so the
 * options would be meaningless (and confusing) on anything else.
 */
class MegaMenuExtension extends Extension
{
    private static $db = [
        'MegaMenuLayout'  => "Enum('Tabs,List,Gallery,Dropdown', 'Dropdown')",
        'MegaMenuColumns' => "Enum('3,4,5,6', '4')",
        'HideParentCard'  => 'Boolean',
    ];

    private static $has_many = [
        'Links' => Link::class . '.Owner',
    ];

    private static $owns = [
        'Links',
    ];

    public function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName(['MegaMenuLayout', 'MegaMenuColumns', 'Links', 'HideParentCard']);

        if ((int) $this->owner->ParentID !== 0) {
            return;
        }

        $layout = DropdownField::create('MegaMenuLayout', 'Menu Dropdown layout', [
            'Dropdown' => 'Dropdown (simple list)',
            'List'     => 'Lists (link columns)',
            'Gallery'  => 'Gallery (image grid)',
            'Tabs'     => 'Tabs (with product images)',

        ]);

        // Only List and Gallery lay their children out in columns -- Tabs
        // and Dropdown have their own fixed structure, so this field would
        // do nothing there.
        $columns = DropdownField::create('MegaMenuColumns', 'Number of columns', [
            '3' => '3 columns',
            '4' => '4 columns',
            '5' => '5 columns',
            '6' => '6 columns',
        ]);
        $columns->displayIf('MegaMenuLayout')->isEqualTo('List')
            ->orIf('MegaMenuLayout')->isEqualTo('Gallery');

        // Shared promo/CTA links rendered once in the Tabs panel (below the
        // vertical tab list), not per-product -- Lists/Gallery/Dropdown have
        // no equivalent spot for them, so this only makes sense for Tabs.
        $links = MultiLinkField::create('Links', 'Tabs panel links');
        $links->displayIf('MegaMenuLayout')->isEqualTo('Tabs');

        // The List layout renders a trailing "view all [this page]" card
        // (using this page's own Photo/Link) after the link columns -- see
        // MegaMenuList.ss. Only List has that card, so this only makes
        // sense there.
        $hideParentCard = CheckboxField::create(
            'HideParentCard',
            'Hide "view all" card in mega menu'
        );
        $hideParentCard->displayIf('MegaMenuLayout')->isEqualTo('List');

        $fields->addFieldToTab('Root.Main', ToggleCompositeField::create(
            'MegaMenuOptions',
            'Menu Dropdown Options',
            [$layout, $columns, $links, $hideParentCard]
        )->setHeadingLevel(4));
    }
}
