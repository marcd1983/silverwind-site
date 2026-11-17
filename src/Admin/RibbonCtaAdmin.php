<?php

namespace Antlion\SilverwindSite\Admin;

use SilverStripe\Admin\ModelAdmin;
use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;
use Antlion\SilverwindSite\Models\RibbonCta;

class RibbonCtaAdmin extends ModelAdmin
{
    private static $url_segment = 'ribbon-ctas';
    private static $menu_title = 'Ribbon CTAs';
    private static $menu_icon_class = 'font-icon-block-banner';
    private static $managed_models = [
        RibbonCta::class
    ];

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);
        $gridField = $form->Fields()->fieldByName($this->sanitiseClassName(RibbonCta::class));
        if ($gridField) {
            $config = $gridField->getConfig();
            $config->addComponent(new GridFieldSortableRows('SortOrder'));
        }

        return $form;
    }
}
