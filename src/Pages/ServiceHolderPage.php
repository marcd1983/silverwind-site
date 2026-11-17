<?php

namespace Antlion\SilverwindSite\Pages;

use Page;
use SilverStripe\ORM\DataList;

class ServiceHolderPage extends Page
{
    private static $table_name = 'ServiceHolderPage';

    private static string $singular_name = 'Service Holder Page';

    private static string $plural_name = 'Service Holder Pages';

    private static $description = 'Holder page for service detail pages';

    private static string $icon_class = 'font-icon-p-list';

    private static $allowed_children = [
        ServicePage::class,
    ];

    private static $db = [
        'GridColumn' => "Enum('2,3,4,5,6','4')",
    ];
}
