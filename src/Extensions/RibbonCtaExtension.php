<?php

namespace Antlion\SilverwindSite\Extensions;

use Antlion\SilverwindSite\Models\RibbonCta;
use Antlion\SilverwindSite\Elements\ElementRibbonCta;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\SiteConfig\SiteConfig;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * The ribbon shown above the footer on a page.
 *
 * The site-wide default is chosen on a ribbon in the Ribbon CTAs admin ("Show
 * above the footer on every page", stored as SiteConfig.FooterRibbonID); each
 * page can inherit it, turn it off, or show a different ribbon. The theme's Page.ss renders
 * $BeforeFooter just after <main>.
 *
 * @property string $FooterRibbonMode inherit | none | custom
 * @method RibbonCta FooterRibbon()
 * @extends Extension<SiteTree>
 */
class RibbonCtaExtension extends Extension
{
    private static array $db = [
        'FooterRibbonMode' => "Enum('inherit,none,custom','inherit')",
    ];

    private static array $has_one = [
        'FooterRibbon' => RibbonCta::class,
    ];

    /** The has_one is otherwise scaffolded onto the content tab too */
    public function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName(['FooterRibbonMode', 'FooterRibbonID', 'FooterRibbon']);
    }

    public function updateSettingsFields(FieldList $fields): void
    {
        $fields->addFieldsToTab('Root.Settings', [
            OptionsetField::create('FooterRibbonMode', _t(__CLASS__ . '.Mode', 'Ribbon above the footer'), [
                'inherit' => _t(__CLASS__ . '.Inherit', 'The site-wide ribbon (chosen in Ribbon CTAs)'),
                'none' => _t(__CLASS__ . '.None', 'None on this page'),
                'custom' => _t(__CLASS__ . '.Custom', 'A different ribbon'),
            ]),
            Wrapper::create(
                DropdownField::create('FooterRibbonID', _t(__CLASS__ . '.Ribbon', 'Ribbon'), RibbonCta::get()->map('ID', 'Title')->toArray())
                    ->setEmptyString(_t(__CLASS__ . '.Choose', 'Choose a ribbon…'))
            )->setName('FooterRibbonWrapper')->displayIf('FooterRibbonMode')->isEqualTo('custom')->end(),
        ]);
    }

    /**
     * The ribbon to show above the footer: the page's choice or the site
     * default, if it's within its dates and not already placed on the page
     * as a Ribbon CTA block.
     */
    public function getCurrentFooterRibbon(): ?RibbonCta
    {
        $owner = $this->getOwner();

        $ribbon = match ($owner->FooterRibbonMode) {
            'none' => null,
            'custom' => $owner->FooterRibbon(),
            default => SiteConfig::current_site_config()->FooterRibbon(),
        };

        if (!$ribbon || !$ribbon->exists() || !$ribbon->isCurrent()) {
            return null;
        }

        return $this->pageHasRibbonBlock($ribbon->ID) ? null : $ribbon;
    }

    /** Rendered by the theme between </main> and the footer */
    public function BeforeFooter(): ?DBHTMLText
    {
        return $this->getCurrentFooterRibbon()?->RenderedFor(true);
    }

    private function pageHasRibbonBlock(int $ribbonID): bool
    {
        $owner = $this->getOwner();
        if (!$owner->hasMethod('ElementalArea')) {
            return false;
        }
        $area = $owner->ElementalArea();

        return $area && $area->exists()
            && ElementRibbonCta::get()->filter(['ParentID' => $area->ID, 'RibbonID' => $ribbonID])->exists();
    }
}
