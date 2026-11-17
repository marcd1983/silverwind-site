<?php

namespace Antlion\SilverwindSite\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Assets\Image;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\LinkField\Models\Link;
use SilverStripe\LinkField\Form\MultiLinkField;
use SilverStripe\AssetAdmin\Forms\UploadField;

class Partner extends DataObject
{
    private static $table_name = 'Partner';

    private static $singular_name = 'Partner';
    private static $plural_name   = 'Partners';
    private static $description   = 'Partners';

    private static $db = [
        'Title'   => 'Varchar(255)',
        'Summary' => 'Text',
        'SortOrder'    => 'Int',
    ];

    private static $has_one = [
        'Image' => Image::class,
    ];

    private static $has_many = [
        'Links' => Link::class . '.Owner',
    ];

    private static $owns = [
        'Image',
        'Links',
    ];

    private static $default_sort = 'SortOrder ASC, Title ASC';

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        $fields->removeByName([
            'Title',
            'Summary',
            'SortOrder',
            'Image',
            'Links',
        ]);

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Partner Name')->setMaxLength(255),
            TextareaField::create('Summary', 'Summary')->setRows(8),
            UploadField::create('Image', 'Partner Logo')
                ->setFolderName('Uploads/Partner Logos')
                ->setAllowedFileCategories('image/supported'),
            MultiLinkField::create('Links', 'Button Links'),
        ]);

        return $fields;
    }
}
