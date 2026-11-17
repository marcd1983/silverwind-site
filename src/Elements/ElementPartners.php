<?php

namespace Antlion\SilverwindSite\Elements;

use Antlion\SilverwindBlocks\Style;
use Antlion\SilverwindBlocks\Traits\ArrangesFields;
use Antlion\SilverwindBlocks\Traits\HasColumns;
use Antlion\SilverwindSite\Models\Partner;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Model\List\SS_List;
use SilverStripe\ORM\ManyManyList;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchButton;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * Partners from the Partners admin: a strip of logos, or cards with logo,
 * summary and buttons. A partner without a logo shows its name instead.
 * Template: templates/Antlion/SilverwindSite/Elements/ElementPartners.ss.
 *
 * @property string $Source   all | selected
 * @property string $Display  logos | cards
 * @property bool   $Greyscale
 * @method ManyManyList<Partner> Partners()
 */
class ElementPartners extends BaseElement
{
    use ArrangesFields;
    use HasColumns;

    private static string $table_name = 'ElementPartners';

    private static string $singular_name = 'Partners';

    private static string $plural_name = 'Partners blocks';

    private static string $description = 'Partner logos or cards from the Partners admin';

    private static string $icon = 'font-icon-block-layout-2';

    private static bool $inline_editable = false;

    private static array $db = [
        ...self::COLUMNS_DB,
        'Intro' => 'HTMLText',
        'Source' => "Enum('all,selected','all')",
        'Display' => "Enum('logos,cards','logos')",
        'Greyscale' => 'Boolean',
        'Gap' => Style::DB_SPACING,
    ];

    private static array $defaults = [
        'Greyscale' => true,
        'ColumnsSmall' => 2,
        'ColumnsMedium' => 3,
        'ColumnsLarge' => 5,
    ];

    private static array $many_many = [
        'Partners' => Partner::class,
    ];

    private static array $many_many_extraFields = [
        'Partners' => ['SortOrder' => 'Int'],
    ];

    public function getType(): string
    {
        return _t(__CLASS__ . '.BlockType', 'Partners');
    }

    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->removeByName(['Source', 'Partners', 'Display', 'Greyscale']);

            $fields->insertBefore('Title', OptionsetField::create('Display', _t(__CLASS__ . '.Display', 'Show as'), [
                'logos' => _t(__CLASS__ . '.Logos', 'Logos: a row of partner logos'),
                'cards' => _t(__CLASS__ . '.Cards', 'Cards: logo, summary and buttons'),
            ]));
            $fields->insertAfter('Display', OptionsetField::create('Source', _t(__CLASS__ . '.Source', 'Which partners'), [
                'all' => _t(__CLASS__ . '.SourceAll', 'All, in the Partners admin\'s order'),
                'selected' => _t(__CLASS__ . '.SourceSelected', 'Choose partners'),
            ]));

            $this->introField($fields);

            if ($this->isInDB()) {
                $grid = GridField::create('Partners', _t(__CLASS__ . '.Partners', 'Partners'), $this->Partners()->sort('SortOrder'));
                $grid->getConfig()
                    ->removeComponentsByType([GridFieldAddExistingAutocompleter::class, GridFieldDeleteAction::class])
                    ->addComponents(
                        GridFieldOrderableRows::create('SortOrder'),
                        GridFieldAddExistingSearchButton::create(),
                        GridFieldDeleteAction::create(true)
                    );
                $fields->addFieldToTab('Root.Main', Wrapper::create($grid)
                    ->setName('PartnersWrapper')
                    ->displayIf('Source')->isEqualTo('selected')->end());
            }

            $this->addLayoutGroup($fields, [
                $this->columnsField(_t(__CLASS__ . '.Columns', 'Per row')),
                Wrapper::create(
                    CheckboxField::create('Greyscale', _t(__CLASS__ . '.Greyscale', 'Grey logos, in colour on hover'))
                )->setName('GreyscaleWrapper')->displayIf('Display')->isEqualTo('logos')->end(),
                DropdownField::create('Gap', _t(__CLASS__ . '.Gap', 'Space between'), Style::spacingOptions()),
            ]);
        });

        return $this->arrangeMainTab(parent::getCMSFields());
    }

    public function getPartnerList(): SS_List
    {
        return $this->Source === 'selected'
            ? $this->Partners()->sort('SortOrder')
            : Partner::get();
    }

    public function getGridClass(): string
    {
        return 'grid ' . Style::gap($this->Gap) . ' ' . $this->columnClasses('grid');
    }

    /** Card titles sit one level below the block title (when it's shown) */
    public function getCardHeading(): string
    {
        $level = (int) substr($this->getHeadingLevel(), 1);
        if ($this->Title && $this->ShowTitle) {
            $level++;
        }

        return 'h' . min(6, max(2, $level));
    }

    public function getSummary(): string
    {
        return _t(__CLASS__ . '.Summary', '{count} partner(s)', ['count' => $this->getPartnerList()->count()])
            . ' · ' . ($this->Display === 'cards' ? _t(__CLASS__ . '.CardsLabel', 'cards') : _t(__CLASS__ . '.LogosLabel', 'logos'));
    }

    protected function provideBlockSchema(): array
    {
        $schema = parent::provideBlockSchema();
        $schema['content'] = $this->getSummary();

        return $schema;
    }
}
