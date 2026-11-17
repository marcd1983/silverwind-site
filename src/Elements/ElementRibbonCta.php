<?php

namespace Antlion\SilverwindSite\Elements;

use Antlion\SilverwindSite\Models\RibbonCta;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;

/**
 * Places a Ribbon CTA from the shared library (Ribbon CTAs admin) on a page.
 *
 * The ribbon brings its own heading, text, buttons, background and schedule;
 * this block only decides where it goes. Outside the ribbon's dates the
 * block renders nothing. Template: templates/Antlion/SilverwindSite/Elements/ElementRibbonCta.ss.
 *
 * @property int $RibbonID
 * @method RibbonCta Ribbon()
 */
class ElementRibbonCta extends BaseElement
{
    private static string $table_name = 'ElementRibbonCta';

    private static string $singular_name = 'Ribbon CTA';

    private static string $plural_name = 'Ribbon CTAs';

    private static string $description = 'A call-to-action band from the Ribbon CTAs library';

    private static string $icon = 'font-icon-block-banner';

    private static array $has_one = [
        'Ribbon' => RibbonCta::class,
    ];

    // The ribbon has its own heading; the block title is just a CMS label
    private static array $defaults = [
        'ShowTitle' => false,
    ];

    public function getType(): string
    {
        return _t(__CLASS__ . '.BlockType', 'Ribbon CTA');
    }

    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->removeByName('RibbonID');

            $ribbons = RibbonCta::get()->map('ID', 'Title')->toArray();
            $fields->addFieldToTab('Root.Main', DropdownField::create('RibbonID', _t(__CLASS__ . '.Ribbon', 'Ribbon'), $ribbons)
                ->setEmptyString(_t(__CLASS__ . '.ChooseRibbon', 'Choose a ribbon…'))
                ->setDescription(_t(
                    __CLASS__ . '.RibbonDescription',
                    'Ribbons are created and scheduled under "Ribbon CTAs" in the CMS menu. The block title is only a label here; the ribbon shows its own heading.'
                )));

            $ribbon = $this->Ribbon();
            if ($ribbon->exists() && !$ribbon->isCurrent()) {
                $fields->addFieldToTab('Root.Main', LiteralField::create('RibbonNotCurrent', sprintf(
                    '<p class="alert alert-warning">%s</p>',
                    _t(__CLASS__ . '.NotCurrent', 'This ribbon is outside its dates ({schedule}), so the block is hidden right now.', [
                        'schedule' => $ribbon->getScheduleSummary(),
                    ])
                )));
            }
        });

        return parent::getCMSFields();
    }

    /** The ribbon if it's set and within its dates */
    public function getActiveRibbon(): ?RibbonCta
    {
        $ribbon = $this->Ribbon();

        return $ribbon->exists() && $ribbon->isCurrent() ? $ribbon : null;
    }

    /** A band like Hero/Section: touches neighbouring bands (silverwind-blocks spacing) */
    public function getHasOwnBackground(): bool
    {
        return true;
    }

    public function getSummary(): string
    {
        $ribbon = $this->Ribbon();
        if (!$ribbon->exists()) {
            return _t(__CLASS__ . '.NoRibbon', 'No ribbon chosen');
        }

        return $ribbon->Title . ' · ' . $ribbon->getStatusLabel();
    }

    protected function provideBlockSchema(): array
    {
        $schema = parent::provideBlockSchema();
        $schema['content'] = $this->getSummary();

        return $schema;
    }
}
