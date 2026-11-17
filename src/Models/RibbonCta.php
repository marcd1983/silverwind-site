<?php

namespace Antlion\SilverwindSite\Models;

use Antlion\SilverwindBlocks\Style;
use Antlion\SilverwindBlocks\Traits\HasAppearance;
use Antlion\SilverwindBlocks\Traits\StyleAccessors;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\LinkField\Form\MultiLinkField;
use SilverStripe\LinkField\Models\Link;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\HasManyList;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\SiteConfig\SiteConfig;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * A call-to-action band: heading, text and buttons on a colour or image.
 *
 * Ribbons live in a shared library (Ribbon CTAs admin) so one ribbon, with
 * its schedule, can be reused anywhere:
 *
 *   - as a block: Antlion\SilverwindSite\Elements\ElementRibbonCta picks one from the library
 *   - site-wide above the footer: the "Show above the footer on every page"
 *     checkbox here; each page can inherit, turn off or replace it
 *     (Antlion\SilverwindSite\Extensions\RibbonCtaExtension)
 *
 * Background, overlay and text colour use silverwind-blocks' HasAppearance,
 * so the fields and behaviour match its Hero and Section blocks. Rendered by
 * templates/Antlion/SilverwindSite/Models/RibbonCta.ss via RenderedFor().
 *
 * @property string $Title
 * @property bool   $HideTitle
 * @property string $Content
 * @property string $StartDate
 * @property string $EndDate
 * @property string $Layout     split | stacked
 * @property string $Size       sm | md | lg
 * @method HasManyList<Link> Links()
 */
class RibbonCta extends DataObject implements PermissionProvider
{
    use HasAppearance;
    use StyleAccessors;

    private static string $table_name = 'RibbonCta';

    private static string $singular_name = 'Ribbon CTA';

    private static string $plural_name = 'Ribbon CTAs';

    private static string $description = 'Call-to-action band, reusable as a block or above the footer';

    private static array $db = [
        ...self::BACKGROUND_DB,
        'Title' => 'Varchar(255)',
        'HideTitle' => 'Boolean',
        'Content' => 'HTMLText',
        'StartDate' => 'Date',
        'EndDate' => 'Date',
        'SortOrder' => 'Int',
        'Layout' => "Enum('split,stacked','split')",
        'HorizontalAlignment' => Style::DB_HORIZONTAL,
        'Size' => "Enum('sm,md,lg','md')",
    ];

    private static array $has_one = self::BACKGROUND_IMAGES;

    private static array $has_many = [
        'Links' => Link::class . '.Owner',
    ];

    private static array $owns = ['Links', 'BackgroundImage', 'MobileBackgroundImage'];

    private static array $cascade_deletes = ['Links'];

    private static array $cascade_duplicates = ['Links'];

    private static array $defaults = [
        ...self::GRADIENT_DEFAULTS,
        'OverlayOpacity' => 50,
        'HorizontalAlignment' => 'center',
    ];

    private static array $summary_fields = [
        'Title' => 'Title',
        'ScheduleSummary' => 'Shows',
        'StatusLabel' => 'Status',
        'FooterLabel' => 'Above footer',
    ];

    /** Pending change to the site-wide footer ribbon, applied after write */
    private ?bool $siteFooterChange = null;

    private static string $default_sort = 'SortOrder ASC, Title ASC';

    public function getCMSFields(): FieldList
    {
        $fields = FieldList::create(TabSet::create('Root'));

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', _t(__CLASS__ . '.Title', 'Heading')),
            CheckboxField::create('HideTitle', _t(__CLASS__ . '.HideTitle', 'Hide heading')),
            HTMLEditorField::create('Content', _t(__CLASS__ . '.Content', 'Text'))->setRows(4),
            MultiLinkField::create('Links', _t(__CLASS__ . '.Links', 'Buttons')),
            FieldGroup::create(_t(__CLASS__ . '.Schedule', 'Show between (optional)'), [
                DateField::create('StartDate', _t(__CLASS__ . '.StartDate', 'From')),
                DateField::create('EndDate', _t(__CLASS__ . '.EndDate', 'Until')),
            ])->setName('Schedule')->setDescription(_t(
                __CLASS__ . '.ScheduleDescription',
                'Outside these dates the ribbon is hidden everywhere it\'s used. Leave empty to always show.'
            )),
            CheckboxField::create('IsSiteFooterRibbon', _t(__CLASS__ . '.IsSiteFooterRibbon', 'Show above the footer on every page'))
                ->setDescription($this->siteFooterDescription()),
            ToggleCompositeField::create('RibbonLayout', _t(__CLASS__ . '.LayoutGroup', 'Layout'), [
                OptionsetField::create('Layout', _t(__CLASS__ . '.Layout', 'Arrangement'), [
                    'split' => _t(__CLASS__ . '.LayoutSplit', 'Text on the left, buttons on the right'),
                    'stacked' => _t(__CLASS__ . '.LayoutStacked', 'Buttons below the text'),
                ]),
                Wrapper::create(
                    DropdownField::create('HorizontalAlignment', _t(__CLASS__ . '.Alignment', 'Alignment'), Style::horizontalOptions())
                )->setName('AlignmentWrapper')->displayIf('Layout')->isEqualTo('stacked')->end(),
                DropdownField::create('Size', _t(__CLASS__ . '.Size', 'Padding'), [
                    'sm' => _t(__CLASS__ . '.SizeSmall', 'Compact'),
                    'md' => _t(__CLASS__ . '.SizeMedium', 'Standard'),
                    'lg' => _t(__CLASS__ . '.SizeLarge', 'Spacious'),
                ]),
            ])->setHeadingLevel(4),
            ToggleCompositeField::create('RibbonBackground', _t(__CLASS__ . '.BackgroundGroup', 'Background'), [
                ...$this->backgroundFields('Uploads/Ribbons'),
            ])->setHeadingLevel(4)
                ->setDescription(_t(__CLASS__ . '.BackgroundDefault', 'With no colour or image, the theme\'s primary colour is used.')),
        ]);

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }

    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if ($this->StartDate && $this->EndDate && $this->EndDate < $this->StartDate) {
            $result->addFieldError('EndDate', _t(__CLASS__ . '.EndBeforeStart', 'The end date is before the start date.'));
        }

        return $result;
    }

    protected function onBeforeWrite(): void
    {
        parent::onBeforeWrite();
        $this->OverlayOpacity = max(0, min(100, (int) $this->OverlayOpacity));
    }

    // ---- Site-wide footer ribbon ----------------------------------------------
    // Stored as SiteConfig.FooterRibbonID (one ribbon site-wide), but edited
    // here, on the ribbon, via the "Show above the footer" checkbox. Pages can
    // turn it off or swap it on their Settings tab (RibbonCtaExtension).

    public function getIsSiteFooterRibbon(): bool
    {
        return $this->isInDB() && (int) SiteConfig::current_site_config()->FooterRibbonID === (int) $this->ID;
    }

    /** Called by the CMS form when saving the checkbox */
    public function setIsSiteFooterRibbon($value): static
    {
        $value = (bool) $value;
        if ($value !== $this->getIsSiteFooterRibbon()) {
            $this->siteFooterChange = $value;
            // write() skips onAfterWrite() when no column changed, and this
            // checkbox isn't a column: make sure the save goes through
            $this->forceChange();
        }

        return $this;
    }

    protected function onAfterWrite(): void
    {
        parent::onAfterWrite();

        if ($this->siteFooterChange === null) {
            return;
        }
        $config = SiteConfig::current_site_config();
        $isCurrent = (int) $config->FooterRibbonID === (int) $this->ID;

        if ($this->siteFooterChange && !$isCurrent) {
            $config->FooterRibbonID = $this->ID; // replaces any other ribbon
            $config->write();
        } elseif (!$this->siteFooterChange && $isCurrent) {
            $config->FooterRibbonID = 0;
            $config->write();
        }
        $this->siteFooterChange = null;
    }

    protected function onAfterDelete(): void
    {
        parent::onAfterDelete();

        $config = SiteConfig::current_site_config();
        if ((int) $config->FooterRibbonID === (int) $this->ID) {
            $config->FooterRibbonID = 0;
            $config->write();
        }
    }

    public function getFooterLabel(): string
    {
        return $this->getIsSiteFooterRibbon() ? _t(__CLASS__ . '.SiteWide', 'Every page') : '';
    }

    private function siteFooterDescription(): string
    {
        $current = SiteConfig::current_site_config()->FooterRibbon();
        $other = $current && $current->exists() && (int) $current->ID !== (int) $this->ID
            ? ' ' . _t(__CLASS__ . '.ReplacesCurrent', 'Ticking this replaces "{title}", which is shown there now.', ['title' => $current->Title])
            : '';

        return _t(__CLASS__ . '.SiteFooterDescription', 'One ribbon can be shown site-wide, within its dates. Pages can hide it or show a different one on their Settings tab.') . $other;
    }

    /**
     * Whether the ribbon should show today (dates are inclusive).
     */
    public function isCurrent(): bool
    {
        $today = DBDatetime::now()->Format('y-MM-dd');

        return (!$this->StartDate || $this->StartDate <= $today)
            && (!$this->EndDate || $this->EndDate >= $today);
    }

    public function getStatusLabel(): string
    {
        $today = DBDatetime::now()->Format('y-MM-dd');

        return match (true) {
            $this->StartDate && $this->StartDate > $today => _t(__CLASS__ . '.Scheduled', 'Scheduled'),
            $this->EndDate && $this->EndDate < $today => _t(__CLASS__ . '.Expired', 'Expired'),
            default => _t(__CLASS__ . '.Showing', 'Showing'),
        };
    }

    public function getScheduleSummary(): string
    {
        $format = fn ($date) => $this->dbObject($date)->Nice();

        return match (true) {
            $this->StartDate && $this->EndDate => $format('StartDate') . ' – ' . $format('EndDate'),
            (bool) $this->StartDate => _t(__CLASS__ . '.From', 'From {date}', ['date' => $format('StartDate')]),
            (bool) $this->EndDate => _t(__CLASS__ . '.Until', 'Until {date}', ['date' => $format('EndDate')]),
            default => _t(__CLASS__ . '.Always', 'Always'),
        };
    }

    /** No colour and no image: the template falls back to the primary colour */
    public function getUsesDefaultBackground(): bool
    {
        return !$this->getBackgroundHex() && !$this->getHasBackgroundImage();
    }

    /**
     * The ribbon's markup. $fullBleed: the ribbon spans the screen (footer
     * ribbon, full-width block), so its content lines up with the site
     * container; otherwise it's a rounded box filling its column.
     */
    public function RenderedFor($fullBleed = false): DBHTMLText
    {
        return $this->customise(['FullBleed' => (bool) $fullBleed])->renderWith(self::class);
    }

    // ---- Permissions: managed via the RIBBON_* codes (Security > Groups) ----

    public function canView($member = null)
    {
        return Permission::check(['RIBBON_VIEW', 'RIBBON_EDIT', 'ADMIN'], 'any', $member);
    }

    public function canEdit($member = null)
    {
        return Permission::check(['RIBBON_EDIT', 'ADMIN'], 'any', $member);
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check(['RIBBON_CREATE', 'ADMIN'], 'any', $member);
    }

    public function canDelete($member = null)
    {
        return Permission::check(['RIBBON_DELETE', 'ADMIN'], 'any', $member);
    }

    public function providePermissions(): array
    {
        $category = _t(__CLASS__ . '.PermissionCategory', 'Ribbon CTAs');

        return [
            'RIBBON_VIEW' => ['name' => _t(__CLASS__ . '.PermView', 'View ribbon CTAs'), 'category' => $category],
            'RIBBON_EDIT' => ['name' => _t(__CLASS__ . '.PermEdit', 'Edit ribbon CTAs'), 'category' => $category],
            'RIBBON_CREATE' => ['name' => _t(__CLASS__ . '.PermCreate', 'Create ribbon CTAs'), 'category' => $category],
            'RIBBON_DELETE' => ['name' => _t(__CLASS__ . '.PermDelete', 'Delete ribbon CTAs'), 'category' => $category],
        ];
    }
}
