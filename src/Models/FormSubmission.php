<?php

namespace Antlion\SilverwindSite\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\CMS\Model\SiteTree;

class FormSubmission extends DataObject
{
    private static string $table_name = 'FormSubmission';

    private static $db = [
        'FormName'        => 'Varchar(255)',   // e.g. "Promo enquiry", "Location contact"
        'FormAction'      => 'Varchar(255)',   // e.g. "PromoForm", "ContactForm"
        'Context'         => 'Varchar(255)',   // e.g. promo title, department name, staff name
        'ContextLink'     => 'Varchar(255)',   // URL to promo/page/etc
        'SubmitterName'   => 'Varchar(255)',
        'SubmitterEmail'  => 'Varchar(255)',
        'SubmitterPhone'  => 'Varchar(100)',
        'Message'         => 'Text',
        'SentTo'          => 'Text',           // comma-separated recipients
        'Status'          => "Enum('New,Emailed,Error','New')",
        'ErrorMessage'    => 'Text',
        'RawData'         => 'Text',           // JSON of full form payload
    ];

    private static $has_one = [
        'Page' => SiteTree::class,            // Which page the form was on
    ];

    private static $summary_fields = [
        'Created.Nice'     => 'Submitted',
        'FormName'         => 'Form',
        'Page.Title'       => 'Page',
        'Context'          => 'Context',
        'SubmitterName'    => 'Name',
        'SubmitterEmail'   => 'Email',
        'Status'           => 'Status',
    ];

    private static $default_sort = 'Created DESC';
}
