<?php

namespace Antlion\SilverwindSite\Models;

use Innoweb\CookieConsent\CookieConsent;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * A snippet of third-party HTML (analytics, tag manager, chat widget...)
 * added from Settings > Scripts, tied to a cookie consent group.
 *
 * Scripts in the "Necessary" group always load. Any other group loads only
 * once the visitor has accepted it: until then the snippet is printed inert
 * (see renderCode()) and the theme's consent-gate script switches it on
 * when consent is given, without a reload.
 *
 * @property string $Title
 * @property string $Code
 * @property string $Placement     head | body-start | body-end
 * @property string $ConsentGroup  a group from CookieConsent.cookies config
 * @property bool   $Enabled
 */
class SiteScript extends DataObject
{
    private static string $table_name = 'SiteScript';

    private static string $singular_name = 'Script';

    private static string $plural_name = 'Scripts';

    private static array $db = [
        'Title' => 'Varchar(255)',
        'Code' => 'Text',
        'Placement' => "Enum('head,body-start,body-end','head')",
        'ConsentGroup' => 'Varchar(64)',
        'Enabled' => 'Boolean',
        'Sort' => 'Int',
    ];

    private static array $has_one = [
        'SiteConfig' => SiteConfig::class,
    ];

    private static array $defaults = [
        'Enabled' => true,
        'ConsentGroup' => 'Analytics',
    ];

    private static array $summary_fields = [
        'Title' => 'Name',
        'PlacementLabel' => 'Where',
        'ConsentGroup' => 'Loads after consent to',
        'Enabled.Nice' => 'On',
    ];

    private static string $default_sort = 'Sort ASC, ID ASC';

    public function getCMSFields(): FieldList
    {
        return FieldList::create(
            TextField::create('Title', _t(__CLASS__ . '.Title', 'Name'))
                ->setDescription(_t(__CLASS__ . '.TitleDescription', 'For your reference, e.g. "Google Analytics".')),
            DropdownField::create('ConsentGroup', _t(__CLASS__ . '.ConsentGroup', 'Cookie group'), static::consentGroupOptions())
                ->setDescription(_t(
                    __CLASS__ . '.ConsentGroupDescription',
                    'The script only loads once a visitor accepts this group in the cookie banner. "Necessary" loads for everyone: use it only for scripts that set no tracking cookies.'
                )),
            OptionsetField::create('Placement', _t(__CLASS__ . '.Placement', 'Where'), static::placementOptions()),
            TextareaField::create('Code', _t(__CLASS__ . '.Code', 'Code'))
                ->setRows(10)
                ->addExtraClass('font-monospace')
                ->setDescription(_t(__CLASS__ . '.CodeDescription', 'Paste the snippet exactly as the provider gives it, including <script> tags.')),
            CheckboxField::create('Enabled', _t(__CLASS__ . '.Enabled', 'On'))
        );
    }

    public static function placementOptions(): array
    {
        return [
            'head' => _t(__CLASS__ . '.Head', 'In <head> (most analytics and tag managers)'),
            'body-start' => _t(__CLASS__ . '.BodyStart', 'Start of <body> (e.g. Google Tag Manager\'s second snippet)'),
            'body-end' => _t(__CLASS__ . '.BodyEnd', 'End of <body> (chat widgets, late-loading scripts)'),
        ];
    }

    public function getPlacementLabel(): string
    {
        return ['head' => 'Head', 'body-start' => 'Body start', 'body-end' => 'Body end'][$this->Placement] ?? '';
    }

    /** The groups configured for the cookie consent module, Necessary first */
    public static function consentGroupOptions(): array
    {
        $groups = array_keys((array) Config::inst()->get(CookieConsent::class, 'cookies'));

        return array_combine($groups, $groups) ?: [CookieConsent::NECESSARY => CookieConsent::NECESSARY];
    }

    public function isAllowed(): bool
    {
        $group = $this->ConsentGroup ?: CookieConsent::NECESSARY;
        if ($group === CookieConsent::NECESSARY) {
            return true;
        }

        // A group that's no longer configured can't be consented to: keep it off
        return isset(static::consentGroupOptions()[$group]) && CookieConsent::check($group);
    }

    /**
     * The snippet as it should appear in the page: unchanged when allowed,
     * otherwise inert until the theme's consent gate activates it.
     */
    public function renderCode(): string
    {
        $code = (string) $this->Code;

        return $this->isAllowed() ? $code : static::makeInert($code, $this->ConsentGroup ?: 'Unknown');
    }

    /**
     * Neutralises a snippet until consent:
     *  - <script>         becomes type="text/plain" data-cookie-consent="Group"
     *                     (the original type, if any, is kept in data-type)
     *  - src on <iframe>/<img>/<script> moves to data-src; href on <link> to data-href
     *  - <noscript> blocks are dropped (without JS there's no way to opt in
     *    here, and they usually hold a tracking pixel or iframe)
     */
    public static function makeInert(string $code, string $group): string
    {
        $group = htmlspecialchars($group, ENT_QUOTES);

        $code = preg_replace('#<noscript\b[^>]*>.*?</noscript>#is', '', $code) ?? '';

        $code = preg_replace_callback('#<script\b([^>]*)>#i', function (array $m) use ($group) {
            $attrs = $m[1];
            if (preg_match('#\stype\s*=\s*(["\']?)([^"\'\s>]+)\1#i', $attrs, $type)) {
                $attrs = str_replace($type[0], ' data-type="' . htmlspecialchars($type[2], ENT_QUOTES) . '"', $attrs);
            }
            $attrs = preg_replace('#\ssrc\s*=#i', ' data-src=', $attrs);

            return '<script type="text/plain" data-cookie-consent="' . $group . '"' . $attrs . '>';
        }, $code) ?? '';

        $code = preg_replace_callback('#<(iframe|img|link)\b([^>]*)>#i', function (array $m) use ($group) {
            $attrs = preg_replace('#\s(src|href)\s*=#i', ' data-$1=', $m[2]);

            return '<' . $m[1] . ' data-cookie-consent="' . $group . '"' . $attrs . '>';
        }, $code) ?? '';

        return $code;
    }

    public function canView($member = null)
    {
        return SiteConfig::current_site_config()->canEdit($member);
    }

    public function canEdit($member = null)
    {
        return SiteConfig::current_site_config()->canEdit($member);
    }

    public function canCreate($member = null, $context = [])
    {
        return SiteConfig::current_site_config()->canEdit($member);
    }

    public function canDelete($member = null)
    {
        return SiteConfig::current_site_config()->canEdit($member);
    }
}
