<?php

namespace Antlion\SilverwindSite\Elements;

use Antlion\SilverwindBlocks\Controllers\BlockController;
use Antlion\SilverwindBlocks\Style;
use Antlion\SilverwindBlocks\Traits\ArrangesFields;
use Antlion\SilverwindBlocks\Traits\HasColumns;
use Antlion\SilverwindBlocks\Traits\SwiperSettings;
use DNADesign\Elemental\Models\BaseElement;
use Antlion\SilverwindSite\Pages\ServicePage;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Model\List\SS_List;
use SilverStripe\Versioned\GridFieldArchiveAction;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchButton;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * Service pages as a grid or a carousel of cards.
 *
 * Built on silverwind-blocks' shared conventions: the same Slideshow settings
 * and swSwiper component as its Carousel block, the same column fields as its
 * Section grid, and its field arrangement (ArrangesFields). The template is
 * templates/Antlion/SilverwindSite/Elements/ElementServices.ss.
 *
 * @property string $Source       featured | all | selected
 * @property string $Appearance   grid | carousel
 * @method ManyManyList<ServicePage> Services()
 */
class ElementServices extends BaseElement
{
    use ArrangesFields;
    use HasColumns;
    use SwiperSettings;

    private static string $table_name = 'ElementServices';

    private static string $singular_name = 'Services';

    private static string $plural_name = 'Services blocks';

    private static string $description = 'Service pages as a grid or carousel of cards';

    private static string $icon = 'font-icon-p-list';

    private static string $controller_class = BlockController::class;

    private static bool $uses_swiper = true;

    // Show/hide rules (display logic) only run in the full edit form
    private static bool $inline_editable = false;

    private static array $db = [
        ...self::SWIPER_DB,
        ...self::COLUMNS_DB,
        'Intro' => 'HTMLText',
        'Source' => "Enum('featured,all,selected','featured')",
        'Appearance' => "Enum('grid,carousel','grid')",
        'Gap' => Style::DB_SPACING,
        'PerViewSmall' => 'Decimal(3,1)',
        'PerViewMedium' => 'Decimal(3,1)',
        'PerViewLarge' => 'Decimal(3,1)',
    ];

    private static array $defaults = [
        ...self::SWIPER_DEFAULTS,
        'Loop' => false,
        'Speed' => 500,
        'ColumnsSmall' => 1,
        'ColumnsMedium' => 2,
        'ColumnsLarge' => 3,
        'PerViewSmall' => 1.2,
        'PerViewMedium' => 2,
        'PerViewLarge' => 3,
    ];

    private static array $many_many = [
        'Services' => ServicePage::class,
    ];

    private static array $many_many_extraFields = [
        'Services' => [
            'SortOrder' => 'Int',
        ],
    ];

    public function getType(): string
    {
        return _t(__CLASS__ . '.BlockType', 'Services');
    }

    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->removeByName([...array_keys(self::SWIPER_DB), 'Source', 'Services']);

            // The choice that changes what the other fields mean goes first
            $fields->insertBefore('Title', OptionsetField::create('Source', _t(__CLASS__ . '.Source', 'Which services'), [
                'featured' => _t(__CLASS__ . '.SourceFeatured', 'Featured services (ticked "Featured service" on the page)'),
                'all' => _t(__CLASS__ . '.SourceAll', 'All services'),
                'selected' => _t(__CLASS__ . '.SourceSelected', 'Choose services'),
            ]));

            $this->introField($fields);

            if ($this->isInDB()) {
                $grid = GridField::create('Services', _t(__CLASS__ . '.Services', 'Services'), $this->Services()->sort('SortOrder'));
                $grid->getConfig()
                    ->removeComponentsByType([
                        GridFieldAddExistingAutocompleter::class,
                        GridFieldDeleteAction::class,
                        GridFieldArchiveAction::class,
                    ])
                    ->addComponents(
                        GridFieldOrderableRows::create('SortOrder'),
                        GridFieldAddExistingSearchButton::create(),
                        GridFieldDeleteAction::create(true) // unlink, never delete the page
                    );
                $fields->addFieldToTab('Root.Main', Wrapper::create($grid)
                    ->setName('ServicesWrapper')
                    ->displayIf('Source')->isEqualTo('selected')->end());
            }

            $this->addLayoutGroup($fields, [
                DropdownField::create('Appearance', _t(__CLASS__ . '.Appearance', 'Show as'), [
                    'grid' => _t(__CLASS__ . '.Grid', 'Grid'),
                    'carousel' => _t(__CLASS__ . '.Carousel', 'Carousel (scrolls sideways)'),
                ]),
                Wrapper::create($this->columnsField(_t(__CLASS__ . '.Columns', 'Cards per row')))
                    ->setName('ColumnsWrapper')
                    ->displayIf('Appearance')->isEqualTo('grid')->end(),
                Wrapper::create(
                    FieldGroup::create(_t(__CLASS__ . '.PerView', 'Cards visible'), [
                        NumericField::create('PerViewSmall', _t(__CLASS__ . '.Phones', 'Phones'))->setScale(1),
                        NumericField::create('PerViewMedium', _t(__CLASS__ . '.Tablets', 'Tablets'))->setScale(1),
                        NumericField::create('PerViewLarge', _t(__CLASS__ . '.Desktops', 'Desktops'))->setScale(1),
                    ])->setName('PerView')->setDescription(_t(__CLASS__ . '.PerViewDescription', 'Fractions (e.g. 1.2) show part of the next card as a hint to scroll.'))
                )->setName('PerViewWrapper')->displayIf('Appearance')->isEqualTo('carousel')->end(),
                DropdownField::create('Gap', _t(__CLASS__ . '.Gap', 'Space between cards'), Style::spacingOptions()),
            ]);

            $this->addSlideshowGroup($fields, $this->swiperFields());
            $fields->fieldByName('Root.Main.BlockSlideshow')
                ?->setTitle(_t(__CLASS__ . '.SlideshowGroup', 'Slideshow (carousel only)'));
        });

        return $this->arrangeMainTab(parent::getCMSFields());
    }

    /**
     * True when the block renders as a carousel rather than a grid.
     */
    public function IsCarousel(): bool
    {
        return $this->Appearance === 'carousel';
    }

    /**
     * The published service pages to show, in display order.
     */
    public function getServiceList(): SS_List
    {
        return match ($this->Source) {
            'selected' => $this->Services()->sort('SortOrder'),
            'all' => ServicePage::get(),
            default => ServicePage::get()->filter('Featured', true),
        };
    }

    /** Grid classes: columns per screen size and the gap */
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

    public function getSwiperOptions(): array
    {
        $perView = fn ($value, $fallback) => max(1, round((float) ($value ?: $fallback), 1));
        $small = $perView($this->PerViewSmall, 1.2);
        $medium = $perView($this->PerViewMedium, 2);
        $large = $perView($this->PerViewLarge, 3);

        return $this->swiperBaseOptions($this->getServiceList()->count(), max($small, $medium, $large)) + [
            'spaceBetween' => Style::pixels($this->Gap),
            'slidesPerView' => $small,
            'breakpoints' => [
                640 => ['slidesPerView' => $medium],
                1024 => ['slidesPerView' => $large],
            ],
        ];
    }

    protected function swiperRecord(): DataObject
    {
        return $this;
    }

    public function getSummary(): string
    {
        $count = $this->getServiceList()->count();
        $label = match ($this->Source) {
            'selected' => _t(__CLASS__ . '.SummarySelected', '{count} chosen service(s)', ['count' => $count]),
            'all' => _t(__CLASS__ . '.SummaryAll', 'All services ({count})', ['count' => $count]),
            default => _t(__CLASS__ . '.SummaryFeatured', 'Featured services ({count})', ['count' => $count]),
        };

        return $label . ($this->IsCarousel() ? ' · ' . _t(__CLASS__ . '.Carousel', 'carousel') : '');
    }

    protected function provideBlockSchema(): array
    {
        $schema = parent::provideBlockSchema();
        $schema['content'] = $this->getSummary();

        return $schema;
    }
}
