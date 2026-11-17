<?php

namespace Antlion\SilverwindSite\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Antlion\SilverwindSite\Models\SiteScript;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Director;
use Antlion\SilverwindSite\Models\RibbonCta;

class SiteConfigExtension extends Extension
{
    private static $db = [
        // Global social URLs (used for sameAs and header/footer)
        'Facebook'      => 'Varchar(512)',
        'LinkedIn'      => 'Varchar(512)',
        'Instagram'     => 'Varchar(512)',
        'Youtube'       => 'Varchar(512)',
        'Twitter'       => 'Varchar(128)',
    ];

    private static $has_one = [
        'SiteLogo'       => Image::class,   // header + Organization schema
        'FooterRibbon'   => RibbonCta::class, // site-wide default; set from the Ribbon CTAs admin
    ];

    /** Third-party snippets, each tied to a cookie consent group */
    private static $has_many = [
        'Scripts' => SiteScript::class,
    ];

    public function updateCMSFields(FieldList $fields): void
    {
        $fields->addFieldsToTab('Root.Main', [
            UploadField::create('SiteLogo', 'Site logo')
            ->setFolderName('Uploads/SiteLogo'),
        ]);

        // FooterRibbon (has_one) is edited on the ribbon itself: Ribbon CTAs admin,
        // "Show above the footer on every page". Keep it off this form.
        $fields->removeByName(['FooterRibbon', 'FooterRibbonID']);

        $fields->addFieldsToTab('Root.Social', [
            TextField::create('Facebook', 'Facebook URL'),
            TextField::create('LinkedIn', 'LinkedIn URL'),
            TextField::create('Instagram', 'Instagram URL'),
            TextField::create('Youtube', 'YouTube URL'),
            TextField::create('Twitter', 'Twitter URL/handle'),
        ]);
        $fields->removeByName('Scripts');
        $config = GridFieldConfig_RecordEditor::create();
        $config->addComponent(GridFieldOrderableRows::create('Sort'));
        $fields->addFieldsToTab('Root.Scripts', [
            LiteralField::create('ScriptsHelp', '<p class="alert alert-info">' . _t(
                __CLASS__ . '.ScriptsHelp',
                'Analytics, tag managers and other third-party snippets. Each one loads only after the visitor accepts its cookie group in the cookie banner.'
            ) . '</p>'),
            GridField::create('Scripts', _t(__CLASS__ . '.Scripts', 'Scripts'), $this->getOwner()->Scripts(), $config),
        ]);
    }

    /**
     * Rendered scripts for one placement (head, body-start, body-end):
     * allowed ones as-is, the rest inert until consent (see SiteScript).
     */
    public function ScriptsFor(string $placement): DBHTMLText
    {
        $html = '';
        foreach ($this->getOwner()->Scripts()->filter(['Enabled' => true, 'Placement' => $placement]) as $script) {
            $html .= $script->renderCode() . "\n";
        }

        return DBField::create_field('HTMLFragment', $html);
    }

    /**
     * schema.org Organization node for the site-wide JSON-LD graph (consumed
     * by PageSeoExtension::SchemaJSON()). Its @id ("{base}#org") is what the
     * WebSite node's "publisher" points at.
     */
    public function getOrganizationSchemaNode(): array
    {
        $config = $this->getOwner();
        $base   = rtrim(Director::absoluteBaseURL(), '/') . '/';

        if (!$config->Title) {
            return [];
        }

        $node = [
            '@type' => 'Organization',
            '@id'   => $base . '#org',
            'name'  => $config->Title,
            'url'   => $base,
        ];

        $logo = $config->SiteLogo();
        if ($logo && $logo->exists()) {
            $node['logo'] = $logo->getAbsoluteURL();
        }

        $sameAs = array_values(array_filter([
            $config->Facebook,
            $config->LinkedIn,
            $config->Instagram,
            $config->Youtube,
            $this->twitterURL((string) $config->Twitter),
        ]));
        if ($sameAs) {
            $node['sameAs'] = $sameAs;
        }

        return $node;
    }

    /**
     * The Twitter field accepts either a URL or a handle ("@acme" / "acme").
     */
    private function twitterURL(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }

        return 'https://x.com/' . ltrim($value, '@');
    }
}
