<?php

namespace Antlion\SilverwindSite\Pages;

use PageController;
use SilverStripe\ORM\DataList;

class ServiceHolderPageController extends PageController
{
    private static $allowed_actions = [];

    public function getServices(): DataList
    {
        return ServicePage::get()->sort([
            'SortOrder' => 'ASC',
            'Title' => 'ASC',
        ]);
    }
}
