<?php

namespace Antlion\SilverwindSite\Pages;

use Page;
use SilverStripe\Assets\Image;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\TextareaField;

class ServicePage extends Page
{
    private static $table_name = 'ServicePage';

    private static string $singular_name = 'Service Page';

    private static string $plural_name = 'Service Pages';

    private static $description = 'Individual service detail page';

    private static string $cms_icon_class = 'font-icon-p-list';

    private static $can_be_root = false;

    private static $allowed_parents = [
        ServiceHolderPage::class,
    ];

    private static $db = [
        'Summary' => 'Varchar(255)',
        'Featured' => 'Boolean',
        'ShowOnHome' => 'Boolean',
        'SortOrder' => 'Int',
    ];

    private static $has_one = [
        'ServiceImage' => Image::class,
    ];

    private static $owns = [
        'ServiceImage',
    ];

    private static $summary_fields = [
        'ServiceImage.CMSThumbnail' => 'Image',
        'Title' => 'Title',
        'Summary' => 'Summary',
        'Featured.Nice' => 'Featured',
        'ShowOnHome.Nice' => 'Show on Home',
    ];

    private static $defaults = [
        'ShowOnHome' => false,
        'Featured' => false,
    ];

    private static $default_sort = 'SortOrder ASC, Title ASC';

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab('Root.Main', [
            TextareaField::create('Summary', 'Short Summary')
                ->setDescription('Short description for cards and listings'),
            CheckboxField::create('Featured', 'Featured service'),
            CheckboxField::create('ShowOnHome', 'Show on homepage'),
            UploadField::create("ServiceImage", "Service Image")
                ->setAllowedFileCategories('image/supported')
                ->setFolderName('Uploads/Service Image'),

        ], 'ElementalArea');

        return $fields;
    }
}
