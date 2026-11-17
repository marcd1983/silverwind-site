<?php

namespace Antlion\SilverwindSite\Tasks;

use Antlion\SilverwindSite\Search\BlockSearchExtension;
use Page;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Recomputes Page.SearchText (block text for site search) for every page,
 * draft and live. Normally kept current by BlockSearchExtension; run this
 * after installing it, importing content, or changing block templates.
 */
class RebuildSearchTextTask extends BuildTask
{
    protected static string $commandName = 'rebuild-search-text';

    protected string $title = 'Rebuild search text';

    protected static string $description = 'Re-indexes the text of every page\'s blocks for site search (draft and live).';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $count = 0;
        $pages = Versioned::withVersionedMode(function () {
            Versioned::set_stage(Versioned::DRAFT);
            return Page::get()->toArray();
        });

        foreach ($pages as $page) {
            BlockSearchExtension::refresh($page, Versioned::DRAFT);
            BlockSearchExtension::refresh($page, Versioned::LIVE);
            $count++;
        }

        $output->writeln("Re-indexed {$count} pages.");

        return Command::SUCCESS;
    }
}
