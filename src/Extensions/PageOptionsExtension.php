<?php

namespace Antlion\SilverwindSite\Extensions;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\ToggleCompositeField;

/**
 * Page fields the Silverwind theme uses: a page image and description (child
 * grid cards, mega menus), the child grid's columns or hiding it, hiding the
 * title bar, and opening the page's link in a new tab. Applied to the site's
 * Page class, so the columns live on its Page table.
 *
 * @property bool   $HideChildren
 * @property bool   $HidePageTitle
 * @property string $ChildGridColumns
 * @property string $Description
 * @property bool   $OpenInNew
 */
class PageOptionsExtension extends Extension
{
    private static array $db = [
        'HideChildren' => 'Boolean',
        'HidePageTitle' => 'Boolean',
        'ChildGridColumns' => 'Enum("3,4,5,6", "4")',
        'Description' => 'Varchar(255)',
        'OpenInNew' => 'Boolean',
    ];

    private static array $has_one = [
        'Photo' => Image::class,
    ];

    private static array $owns = ['Photo'];

    public function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName(['Photo', 'ChildGridColumns', 'HideChildren', 'HidePageTitle', 'Description', 'OpenInNew']);

        $options = ToggleCompositeField::create('PageOptions', _t(__CLASS__ . '.PageOptions', 'Page options'), [
            UploadField::create('Photo', _t(__CLASS__ . '.Photo', 'Page image'))
                ->setAllowedFileCategories('image/supported')
                ->setFolderName('Uploads/Page Photos')
                ->setDescription(_t(__CLASS__ . '.PhotoDescription', 'Shown where this page appears as a card (child grids, mega menus).')),
            TextField::create('Description', _t(__CLASS__ . '.Description', 'Short description'))
                ->setDescription(_t(__CLASS__ . '.DescriptionDescription', 'For cards and menus.')),
            DropdownField::create('ChildGridColumns', _t(__CLASS__ . '.ChildGridColumns', 'Child grid columns'), [
                '3' => '3', '4' => '4', '5' => '5', '6' => '6',
            ]),
            CheckboxField::create('HideChildren', _t(__CLASS__ . '.HideChildren', 'Hide the grid of child pages')),
            CheckboxField::create('HidePageTitle', _t(__CLASS__ . '.HidePageTitle', 'Hide the title bar')),
            CheckboxField::create('OpenInNew', _t(__CLASS__ . '.OpenInNew', 'Open links to this page in a new tab')),
        ])->setHeadingLevel(4);

        // Order the toggle groups: Page options, Menu dropdown options, SEO, Metadata
        if ($metadata = $fields->fieldByName('Root.Main.Metadata')) {
            $fields->removeByName('Metadata');
            $fields->fieldByName('Root.Main.SEO')
                ? $fields->insertAfter('SEO', $metadata)
                : $fields->addFieldToTab('Root.Main', $metadata);
        }
        if ($fields->fieldByName('Root.Main.MegaMenuOptions')) {
            $fields->insertBefore('MegaMenuOptions', $options);
        } else {
            $fields->addFieldToTab('Root.Main', $options);
        }
    }
}
