<?php

namespace Antlion\SilverwindSite\Extensions;

use Antlion\SilverwindSite\Search\SiteSearchForm;
use SilverStripe\Core\Extension;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\SS_List;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Template hooks the Silverwind theme calls on every page, applied to the
 * site's PageController:
 *
 *   $SiteScripts('head' | 'body-start' | 'body-end')  consent-gated scripts
 *   $Locations                                         store locations (footer)
 *
 * The site's PageController::SearchForm() should return
 * SiteSearchForm::forController($this) to search block text too.
 */
class SiteControllerExtension extends Extension
{
    public function SiteScripts(string $placement): DBHTMLText
    {
        return SiteConfig::current_site_config()->ScriptsFor($placement);
    }

    /** antlion/store-location's locations, or an empty list without it */
    public function getLocations(): SS_List
    {
        $class = 'Antlion\\StoreLocation\\Pages\\StoreLocationPage';

        return class_exists($class) ? $class::get()->sort('Title') : ArrayList::create();
    }
}
