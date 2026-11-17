<?php

namespace Antlion\SilverwindSite\Admin;

use SilverStripe\Admin\ModelAdmin;
use Antlion\SilverwindSite\Models\FormSubmission;

class FormSubmissionAdmin extends ModelAdmin
{
    private static $menu_title = 'Form Submissions';
    private static $url_segment = 'form-submissions';

    private static $managed_models = [
        FormSubmission::class,
    ];

    private static $menu_icon_class = 'font-icon-edit-write';
}
