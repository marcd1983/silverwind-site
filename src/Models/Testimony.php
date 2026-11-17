<?php

namespace Antlion\SilverwindSite\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;

class Testimony extends DataObject
{
    private static $table_name = 'Testimony';
    private static $singular_name = 'Testimony';
    private static $plural_name   = 'Testimonies';
    private static $description   = 'Client Testimony';

    private static $db = [
        'Title'   => 'Varchar(255)',
        'Testimony' => 'Text',
        'SortOrder'    => 'Int',
    ];
    private static $default_sort = 'SortOrder ASC, Title ASC';

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        $fields->removeByName([
            'Title',
            'Testimony',
            'SortOrder',
        ]);

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Client Name')->setMaxLength(255),
            TextareaField::create('Testimony', 'Client Testimony')->setRows(8),
        ]);

        return $fields;
    }
}
