<?php

namespace Antlion\SilverwindSite\Models;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\CompositeValidator;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\LinkField\Models\Link;

/**
 * A link that opens a modal (<dialog>) on the current page instead of navigating.
 *
 * The editor pastes the target modal's identifier - shown on that Modal block's
 * Settings tab, e.g. "modal-23" (a bare number is also accepted and gets the
 * "modal-" prefix added). That value goes in data-modal-target, which
 * silverwind-blocks' modals script uses to open <dialog id="modal-23">.
 *
 * getURL() intentionally returns '' - a modal trigger is not a navigation, and a
 * "#modal-23" href just gets rewritten by SSViewer's hash-link handling into an
 * ugly absolute URL. Templates render a <button data-modal-target="$ModalTarget">
 * (silverwind-blocks' Button.ss does this for any link with a ModalTarget).
 *
 * This is a general LinkField option (allowed on every LinkField), unlike
 * Antlion\ElementModal\Model\ModalButton which is locked to the element-modal
 * block.
 *
 * @property string $ModalID
 */
class ModalTriggerLink extends Link
{
    private static string $table_name = 'App_ModalTriggerLink';

    private static string $singular_name = 'Modal trigger';

    private static string $plural_name = 'Modal triggers';

    private static array $db = [
        'ModalID' => 'Varchar(100)',
    ];

    private static $icon = 'font-icon-attention';

    private static int $menu_priority = 60;

    public function getCMSFields(): FieldList
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->replaceField('ModalID', TextField::create(
                'ModalID',
                _t(__CLASS__ . '.MODAL_ID_FIELD', 'Modal ID')
            )->setDescription(_t(
                __CLASS__ . '.MODAL_ID_DESCRIPTION',
                'The ID of the modal to open, shown on that Modal block\'s Settings tab '
                . '(e.g. "modal-23"). A plain number is also accepted.'
            )));

            // A modal trigger never leaves the page.
            $fields->removeByName('OpenInNew');
        });

        return parent::getCMSFields();
    }

    public function getCMSCompositeValidator(): CompositeValidator
    {
        $validator = parent::getCMSCompositeValidator();
        $validator->addValidator(RequiredFieldsValidator::create(['ModalID']));
        return $validator;
    }

    protected function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        $this->ModalID = static::normaliseModalID($this->ModalID);
    }

    /**
     * The bare identifier for data-modal-target, e.g. "modal-23".
     */
    public function getModalTarget(): string
    {
        return static::normaliseModalID($this->ModalID);
    }

    public function getURL(): string
    {
        // A modal trigger does not navigate; the button carries data-modal-target
        // instead. Returning a "#modal-x" here would only be mangled by SSViewer's
        // hash-link rewriting.
        return '';
    }

    public function getDescription(): string
    {
        $target = $this->getModalTarget();
        return $target ? 'Opens ' . $target : '';
    }

    public function getMenuTitle(): string
    {
        return _t(__CLASS__ . '.LINKLABEL', 'Modal trigger');
    }

    /**
     * Accepts "modal-23", "#modal-23", " 23 " -> "modal-23"; "" -> "".
     */
    private static function normaliseModalID(?string $value): string
    {
        $value = ltrim(trim((string) $value), '#');

        if ($value === '') {
            return '';
        }

        return ctype_digit($value) ? 'modal-' . $value : $value;
    }
}
