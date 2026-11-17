<?php

namespace Antlion\SilverwindSite\Admin;

use Antlion\SilverwindSite\Models\Partner;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;
use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;

class PartnersAdmin extends ModelAdmin
{
    private static string $menu_title = 'Partners';
    private static string $url_segment = 'partners';
    private static string $menu_icon_class = 'font-icon-rocket';
    private static int $menu_priority = 2;

    private static array $managed_models = [
        Partner::class,
    ];

    // Optional: makes the default list sorted by SortOrder
    // (dragging updates SortOrder, so this keeps the list stable)
    protected $modelClass = Partner::class;

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        // ModelAdmin creates a GridField with the model class name as the field name
        $gridField = $form->Fields()->fieldByName($this->sanitiseClassName($this->modelClass));

        if ($gridField instanceof GridField) {
            $gridField->getConfig()->addComponent(new GridFieldSortableRows('SortOrder'));
        }

        return $form;
    }
}
