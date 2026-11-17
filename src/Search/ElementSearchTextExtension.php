<?php

namespace Antlion\SilverwindSite\Search;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Core\Extension;
use SilverStripe\Versioned\Versioned;

/**
 * Keeps the owning page's SearchText current when a block changes (see
 * BlockSearchExtension). Blocks are edited and published on their own in
 * the CMS, without the page being saved, so the page can't rely on its
 * own write hooks.
 *
 * @extends Extension<BaseElement>
 */
class ElementSearchTextExtension extends Extension
{
    public function onAfterWrite(): void
    {
        $this->refreshPage(Versioned::get_stage() ?: Versioned::DRAFT);
    }

    public function onAfterPublish(): void
    {
        $this->refreshPage(Versioned::LIVE);
    }

    public function onAfterUnpublish(): void
    {
        $this->refreshPage(Versioned::LIVE);
    }

    public function onAfterArchive(): void
    {
        $this->refreshPage(Versioned::DRAFT);
        $this->refreshPage(Versioned::LIVE);
    }

    private function refreshPage(string $stage): void
    {
        $page = $this->getOwner()->getPage();
        if ($page) {
            BlockSearchExtension::refresh($page, $stage);
        }
    }
}
