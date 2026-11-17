<?php

namespace Antlion\SilverwindSite\Elements;

use Antlion\SilverwindBlocks\Controllers\BlockController;
use Antlion\SilverwindBlocks\Style;
use Antlion\SilverwindBlocks\Traits\ArrangesFields;
use Antlion\SilverwindBlocks\Traits\HasColumns;
use Antlion\SilverwindBlocks\Traits\SwiperSettings;
use Antlion\SilverwindSite\Models\Testimony;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Model\List\SS_List;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ManyManyList;
use Symbiote\GridFieldExtensions\GridFieldAddExistingSearchButton;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * Client testimonials from the Testimonies admin, as a carousel or grid of
 * quotes. Same conventions as the Services block (silverwind-blocks'
 * Slideshow and column fields). Template: templates/Antlion/SilverwindSite/Elements/ElementTestimonials.ss.
 *
 * @property string $Source      all | selected
 * @property string $Appearance  carousel | grid
 * @method ManyManyList<Testimony> Testimonies()
 */
class ElementTestimonials extends BaseElement
{
    use ArrangesFields;
    use HasColumns;
    use SwiperSettings;

    private static string $table_name = 'ElementTestimonials';

    private static string $singular_name = 'Testimonials';

    private static string $plural_name = 'Testimonials blocks';

    private static string $description = 'Client quotes from the Testimonies admin, as a carousel or grid';

    private static string $icon = 'font-icon-chat';

    private static string $controller_class = BlockController::class;

    private static bool $uses_swiper = true;

    private static bool $inline_editable = false;

    private static array $db = [
        ...self::SWIPER_DB,
        ...self::COLUMNS_DB,
        'Intro' => 'HTMLText',
        'Source' => "Enum('all,selected','all')",
        'Appearance' => "Enum('carousel,grid','carousel')",
        'Gap' => Style::DB_SPACING,
    ];

    private static array $defaults = [
        ...self::SWIPER_DEFAULTS,
        'Autoplay' => true,
        'AutoplayDelay' => 8000,
        'ColumnsSmall' => 1,
        'ColumnsMedium' => 2,
        'ColumnsLarge' => 3,
    ];

    private static array $many_many = [
        'Testimonies' => Testimony::class,
    ];

    private static array $many_many_extraFields = [
        'Testimonies' => ['SortOrder' => 'Int'],
    ];

    public function getType(): string
    {
        return _t(__CLASS__ . '.BlockType', 'Testimonials');
    }

    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->removeByName([...array_keys(self::SWIPER_DB), 'Source', 'Testimonies']);

            $fields->insertBefore('Title', OptionsetField::create('Source', _t(__CLASS__ . '.Source', 'Which testimonials'), [
                'all' => _t(__CLASS__ . '.SourceAll', 'All, in the Testimonies admin\'s order'),
                'selected' => _t(__CLASS__ . '.SourceSelected', 'Choose testimonials'),
            ]));

            $this->introField($fields);

            if ($this->isInDB()) {
                $grid = GridField::create('Testimonies', _t(__CLASS__ . '.Testimonies', 'Testimonials'), $this->Testimonies()->sort('SortOrder'));
                $grid->getConfig()
                    ->removeComponentsByType([GridFieldAddExistingAutocompleter::class, GridFieldDeleteAction::class])
                    ->addComponents(
                        GridFieldOrderableRows::create('SortOrder'),
                        GridFieldAddExistingSearchButton::create(),
                        GridFieldDeleteAction::create(true)
                    );
                $fields->addFieldToTab('Root.Main', Wrapper::create($grid)
                    ->setName('TestimoniesWrapper')
                    ->displayIf('Source')->isEqualTo('selected')->end());
            }

            $this->addLayoutGroup($fields, [
                DropdownField::create('Appearance', _t(__CLASS__ . '.Appearance', 'Show as'), [
                    'carousel' => _t(__CLASS__ . '.Carousel', 'Carousel, one quote at a time'),
                    'grid' => _t(__CLASS__ . '.Grid', 'Grid'),
                ]),
                Wrapper::create($this->columnsField(_t(__CLASS__ . '.Columns', 'Quotes per row')))
                    ->setName('ColumnsWrapper')
                    ->displayIf('Appearance')->isEqualTo('grid')->end(),
                DropdownField::create('Gap', _t(__CLASS__ . '.Gap', 'Space between quotes'), Style::spacingOptions()),
            ]);

            $this->addSlideshowGroup($fields, $this->swiperFields());
            $fields->fieldByName('Root.Main.BlockSlideshow')
                ?->setTitle(_t(__CLASS__ . '.SlideshowGroup', 'Slideshow (carousel only)'));
        });

        return $this->arrangeMainTab(parent::getCMSFields());
    }

    public function IsCarousel(): bool
    {
        return $this->Appearance === 'carousel';
    }

    public function getTestimonyList(): SS_List
    {
        return $this->Source === 'selected'
            ? $this->Testimonies()->sort('SortOrder')
            : Testimony::get();
    }

    public function getGridClass(): string
    {
        return 'grid ' . Style::gap($this->Gap) . ' ' . $this->columnClasses('grid');
    }

    public function getSwiperOptions(): array
    {
        return $this->swiperBaseOptions($this->getTestimonyList()->count()) + [
            'spaceBetween' => Style::pixels($this->Gap),
            'slidesPerView' => 1,
            'autoHeight' => true,
        ];
    }

    protected function swiperRecord(): DataObject
    {
        return $this;
    }

    public function getSummary(): string
    {
        $count = $this->getTestimonyList()->count();

        return _t(__CLASS__ . '.Summary', '{count} testimonial(s)', ['count' => $count])
            . ($this->IsCarousel() ? ' · ' . _t(__CLASS__ . '.CarouselLabel', 'carousel') : '');
    }

    protected function provideBlockSchema(): array
    {
        $schema = parent::provideBlockSchema();
        $schema['content'] = $this->getSummary();

        return $schema;
    }
}
