<?php

namespace Antlion\SilverwindSite\Tasks;

use Antlion\SilverwindBlocks\Elements\ElementAccordion;
use Antlion\SilverwindBlocks\Elements\ElementCard;
use Antlion\SilverwindBlocks\Elements\ElementHero;
use Antlion\SilverwindBlocks\Elements\ElementSection;
use Antlion\SilverwindBlocks\Models\AccordionPanel;
use Antlion\StoreLocation\Model\StaffMember;
use Antlion\StoreLocation\Model\StoreDepartment;
use Antlion\StoreLocation\Model\StoreHolidayHour;
use Antlion\StoreLocation\Model\StoreSchedule;
use Antlion\StoreLocation\Pages\StoreLocationHolderPage;
use Antlion\StoreLocation\Pages\StoreLocationPage;
use Antlion\SilverwindSite\Models\Partner;
use Antlion\SilverwindSite\Models\RibbonCta;
use Antlion\SilverwindSite\Models\SiteScript;
use Antlion\SilverwindSite\Demo\DemoImages;
use NSWDPC\Elemental\Models\Image\ElementImage;
use Antlion\SilverwindSite\Models\Testimony;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use DNADesign\Elemental\Models\ElementContent;
use DNADesign\ElementalUserForms\Model\ElementForm;
use DNADesign\SetMenu\Model\MenuSet;
use Antlion\SilverwindSite\Elements\ElementRibbonCta;
use Antlion\SilverwindSite\Elements\ElementPartners;
use Antlion\SilverwindSite\Elements\ElementServices;
use Antlion\SilverwindSite\Elements\ElementTestimonials;
use Page;
use Antlion\SilverwindSite\Pages\ServiceHolderPage;
use Antlion\SilverwindSite\Pages\ServicePage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Dev\BuildTask;
use SilverStripe\LinkField\Models\Link;
use SilverStripe\LinkField\Models\PhoneLink;
use SilverStripe\LinkField\Models\SiteTreeLink;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\UserForms\Model\EditableFormField\EditableEmailField;
use SilverStripe\UserForms\Model\EditableFormField\EditableFormStep;
use SilverStripe\UserForms\Model\EditableFormField\EditableTextField;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Seeds a small, complete demo site for a fictional landscaping company,
 * "Fernbrook Landscapes", using this project's own building blocks:
 *
 *   Home      Hero (h1) · featured Services · "Why us" Section of Cards · Testimonials · FAQ Accordion
 *   Services  holder with every service as a grid + a scheduled Ribbon CTA block,
 *             and six Service pages
 *   About     story text + partner cards
 *   Contact   details + a userforms enquiry form; footer ribbon turned off
 *   Locations three yards on a store locator map (antlion/store-location):
 *             hours, holidays, departments, staff
 *   Scripts   a demo analytics stand-in, gated by cookie consent
 *   Library   two Ribbon CTAs (one is the site-wide footer ribbon), partners,
 *             testimonies; footer menus; site name, tagline and brand colours
 *
 * Without --reset it only adds what's missing: pages whose URL already
 * exists are skipped, and site settings and menus are left alone unless
 * empty. With --reset it archives the pages at the demo URLs (home,
 * services, about-us, contact-us; restorable from the CMS archive), deletes
 * the demo library records, and applies the demo settings and menus.
 *
 *   vendor/bin/sake tasks:seed-demo-site [--reset] [--refresh-images]
 *
 * Photos come from Pixabay when PIXABAY_API_KEY is set (Antlion\SilverwindSite\Demo\DemoImages),
 * otherwise generated placeholders.
 *   /dev/tasks/seed-demo-site?reset=1
 */
class SeedDemoSiteTask extends BuildTask
{
    protected static string $commandName = 'seed-demo-site';

    protected string $title = 'Seed demo site (Fernbrook Landscapes)';

    protected static string $description = 'Creates a small demo site: pages built from blocks, services, ribbon CTAs, partners, testimonies, menus and brand settings. Dev only. --reset replaces existing demo pages and settings.';

    private const string PHONE = '(555) 014-2290';

    private const string EMAIL = 'hello@fernbrook.example';

    private const string DEMO_SCRIPT = 'Demo analytics (console message)';

    /** store-location departments: title => email */
    private const array DEPARTMENTS = [
        'Design' => 'design@fernbrook.example',
        'Construction' => 'build@fernbrook.example',
        'Maintenance' => 'care@fernbrook.example',
    ];

    private PolyOutput $output;

    private bool $reset = false;

    private DemoImages $images;

    /** Image search per slot (silverwind-site's demo/images.json records the chosen photo IDs) */
    private const array IMAGE_QUERIES = [
        'home-hero' => ['landscaped garden lawn path', 'A landscaped garden with a lawn and stone path'],
        'card-design' => ['landscape design garden', 'A designed garden with a pond and clipped shrubs'],
        'card-crew' => ['gardener working garden', 'A gardener at work'],
        'card-guarantee' => ['stone garden steps', 'Stone garden steps'],
        'about' => ['garden plants nursery', 'Young plants ready for planting'],
        'service-garden-design' => ['formal garden landscaping', 'A formal landscaped garden'],
        'service-patios-and-paving' => ['stone patio garden', 'A stone patio in a garden'],
        'service-planting-and-lawns' => ['flower border garden', 'A colourful flower border'],
        'service-decking-and-fencing' => ['wooden deck garden terrace', 'A courtyard with wooden decking and planters'],
        'service-outdoor-lighting' => ['garden lights night', 'A garden lit at night'],
        'service-garden-maintenance' => ['lawn mowing', 'A freshly mown lawn'],
    ];

    public function getOptions(): array
    {
        return [
            new InputOption('reset', null, InputOption::VALUE_NONE, 'Archive existing pages at the demo URLs and replace demo library records, settings and menus'),
            new InputOption('refresh-images', null, InputOption::VALUE_NONE, 'Download the demo photos again (same photos, from demo/images.json)'),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        if (!Director::isDev()) {
            $output->writeln('<error>This task only runs in dev mode.</error>');
            return Command::FAILURE;
        }

        $this->output = $output;
        $this->reset = (bool) $input->getOption('reset');
        $this->images = new DemoImages(fn (string $line) => $this->say($line));
        $this->say($this->images->hasApiKey()
            ? 'Demo photos: Pixabay (PIXABAY_API_KEY set)'
            : 'Demo photos: placeholders (set PIXABAY_API_KEY in .env for real photos)');
        if ($input->getOption('refresh-images')) {
            $this->images->refresh();
            $this->refreshUsedImages();
        }

        if ($this->reset) {
            $this->say('Resetting: archiving demo pages and removing demo library records');
            $this->archiveDemoPages();
            $this->deleteLibraryRecords();
        }

        $consultation = $this->ribbon('Thinking about your garden?', [
            'Content' => '<p>Book a free, no-obligation design consultation. We\'ll visit, listen, and send you ideas within a week.</p>',
            'Layout' => 'split',
        ]);
        $nextSeason = $this->ribbon('Our next-season diary is open', [
            'Content' => '<p>Projects for the coming season are booking up. Reserve a start date now and lock in this year\'s prices.</p>',
            'Layout' => 'stacked',
            'HorizontalAlignment' => 'center',
            'BackgroundColor' => '#c8963e',
            // Scheduled relative to today, so it's always showing on a fresh seed
            'StartDate' => DBDatetime::now()->Format('y-MM-dd'),
            'EndDate' => date('Y-m-d', strtotime('+60 days', DBDatetime::now()->getTimestamp())),
        ]);

        // Home last: its hero buttons link to the other pages
        $services = $this->servicesSection($nextSeason);
        $about = $this->aboutPage();
        $contact = $this->contactPage();
        $this->locationsPage();
        $home = $this->homePage();
        $this->photoCredits($about);
        $this->images->save();

        // Ribbon buttons point at pages, so they're added once the pages exist
        if ($contact) {
            $this->ribbonLinks($consultation, [
                $this->pageLink($contact, 'Book a consultation', 'secondary'),
                $this->phoneLink('Call ' . self::PHONE, 'outline'),
            ]);
            $this->ribbonLinks($nextSeason, [$this->pageLink($contact, 'Check availability', 'neutral')]);
        }

        $this->partners();
        $this->testimonies();
        $this->demoScript();
        $this->settings($consultation);
        $this->menus($services, $about, $contact);

        $this->say('');
        $this->say($home ? 'Done.' : 'Done. The home page already existed and was left alone: run with --reset to replace it.');

        return Command::SUCCESS;
    }

    // ---- Pages ----------------------------------------------------------------

    private function homePage(): ?SiteTree
    {
        // The site's own HomePage class when it has one (the theme has a layout for it)
        $home = $this->page(class_exists('HomePage') ? 'HomePage' : Page::class, 'Home', 'home', 0, 1);
        if (!$home) {
            return null;
        }

        $this->block($home, ElementHero::class, [
            'Title' => 'Outdoor spaces, built for living',
            'HeadingTag' => 'h1',
            'Content' => '<p>Fernbrook designs, builds and looks after gardens that work as hard as the rest of your home. One team, from the first sketch to the last plant.</p>',
            'Height' => 'medium',
            'HorizontalAlignment' => 'left',
            'BackgroundColor' => '#1f3d2b',
            'BackgroundImageID' => $this->imageID('home-hero'),
            'OverlayType' => 'gradient',
            'GradientDirection' => 'right',
            'GradientStartColor' => '#10261a',
            'GradientStartOpacity' => 85,
            'GradientEndOpacity' => 15,
            'ButtonSize' => 'lg',
        ], fn (ElementHero $hero) => $this->addLinks($hero, [
            $this->pageLink('services', 'Our services', 'accent'),
            $this->pageLink('contact-us', 'Get a free quote', 'outline'),
        ]));

        $this->block($home, ElementServices::class, [
            'Title' => 'What we do',
            'Intro' => '<p>Most of our projects combine two or three of these. Tell us what you have in mind and we\'ll pull the right people together.</p>',
            'Source' => 'featured',
            'Appearance' => 'grid',
            'ColumnsSmall' => 1,
            'ColumnsMedium' => 3,
            'ColumnsLarge' => 3,
        ]);

        $this->block($home, ElementSection::class, [
            'Title' => 'Why homeowners choose Fernbrook',
            'Layout' => 'grid',
            'ColumnsSmall' => 1,
            'ColumnsMedium' => 3,
            'ColumnsLarge' => 3,
            'BackgroundColor' => '#f3f0e7',
        ], function (ElementSection $section) {
            $cards = [
                // title => [text, image slot]
                'Designed around you' => ['We start with how you actually use your outdoor space: kids, dogs, dinners, or quiet mornings. Then we design for that.', 'card-design'],
                'Built by our own crew' => ['No subcontractor roulette. The people who quote your job are the people who build it.', 'card-crew'],
                'Guaranteed for five years' => ['All hard landscaping carries a five-year workmanship guarantee, and planting is covered for its first full season.', 'card-guarantee'],
            ];
            foreach ($cards as $title => [$text, $slot]) {
                $this->nestedBlock($this->area($section, 'Elements'), ElementCard::class, [
                    'Title' => $title,
                    'Content' => "<p>{$text}</p>",
                    'CardStyle' => 'vertical',
                    'ImageID' => $this->imageID($slot),
                    'ImageRatio' => '3x2',
                ]);
            }
        });

        // Quotes come from the Testimonies admin (seeded below), so "all" picks them up
        $this->block($home, ElementTestimonials::class, [
            'Title' => 'What our clients say',
            'Source' => 'all',
            'Appearance' => 'carousel',
        ]);

        $this->block($home, ElementAccordion::class, [
            'Title' => 'Common questions',
            'OpenFirst' => true,
            'EnableFAQSchema' => true,
            'BlockWidth' => 'narrow',
        ], function (ElementAccordion $accordion) {
            $faqs = [
                'How much does a new garden cost?' => 'Most of our full garden projects fall between $15,000 and $60,000. After a free consultation we give you a fixed, itemised quote, not an estimate.',
                'How long does a typical project take?' => 'Design takes two to three weeks. Construction for an average back garden is three to six weeks, depending on the weather and materials.',
                'Do I need to be home while you work?' => 'No. We agree access at the start and send a short photo update at the end of each working day.',
                'Do you look after gardens you didn\'t build?' => 'Yes. Our maintenance plans are open to any garden within 20 miles of our yard.',
            ];
            $sort = 1;
            foreach ($faqs as $question => $answer) {
                AccordionPanel::create([
                    'Title' => $question,
                    'Content' => "<p>{$answer}</p>",
                    'Sort' => $sort++,
                    'AccordionID' => $accordion->ID,
                ])->write();
            }
        });

        return $this->publish($home);
    }

    private function servicesSection(RibbonCta $seasonalRibbon): ?SiteTree
    {
        $holder = $this->page(ServiceHolderPage::class, 'Services', 'services', 0, 2, [
            // The Services block lists the children, so skip the default child grid
            'HideChildren' => true,
            'MetaDescription' => 'Garden design, patios, planting, decking, lighting and maintenance from Fernbrook Landscapes.',
        ]);
        if (!$holder) {
            return SiteTree::get_by_link('services');
        }

        $this->block($holder, ElementServices::class, [
            'Title' => 'All services',
            'ShowTitle' => false,
            'Intro' => '<p>Everything we do, start to finish. Pick one, or let us combine them into a single project with one schedule and one point of contact.</p>',
            'Source' => 'all',
            'Appearance' => 'grid',
            'ColumnsSmall' => 1,
            'ColumnsMedium' => 2,
            'ColumnsLarge' => 3,
        ]);
        $this->block($holder, ElementRibbonCta::class, [
            'Title' => 'Next-season ribbon',
            'RibbonID' => $seasonalRibbon->ID,
        ]);
        $this->publish($holder);

        $services = [
            ['Garden Design', 'Plans drawn around how you use your garden, with 3D visuals before anything is dug.', true,
                'Every project starts with a site visit and a conversation. We survey levels, soil and light, then produce a scaled plan and 3D visuals so you can walk through the garden before work begins.',
                ['Site survey and soil test', 'Concept plan with two layout options', '3D visuals and planting plan', 'Fixed, itemised build quote']],
            ['Patios & Paving', 'Natural stone, porcelain and brick, laid on proper foundations that won\'t sink or crack.', true,
                'A patio is only as good as what\'s underneath it. We dig out, compact and lay a full sub-base, then set every slab on a full mortar bed with correct falls for drainage.',
                ['Patios, paths and driveways', 'Natural stone, porcelain and clay brick', 'Steps, edging and retaining walls', 'Permeable options for drainage rules']],
            ['Planting & Lawns', 'Borders, hedges, trees and new lawns chosen for your soil, your light and your time.', true,
                'We plant for the conditions you actually have, and for how much time you want to spend gardening. Every scheme comes with a simple care sheet for its first year.',
                ['Border design and planting', 'Hedges, trees and screening', 'Turf and seeded lawns', 'First-season plant guarantee']],
            ['Decking & Fencing', 'Hardwood and composite decks, pergolas and fences built square, level and to last.', false,
                'From a simple raised deck to a full garden room frame, our carpenters build with treated or hardwood timber and stainless fixings throughout.',
                ['Hardwood and composite decking', 'Pergolas and shade structures', 'Fencing, screens and gates', 'Built-in seating and planters']],
            ['Outdoor Lighting', 'Low-voltage lighting that makes the garden usable, and beautiful, after dark.', false,
                'Good garden lighting is mostly about what you don\'t see: hidden fittings, warm light and simple controls. We design and install low-voltage systems that are safe and cheap to run.',
                ['Path, step and wall lighting', 'Uplighting for trees and features', 'App and timer controls', 'Fully certified installation']],
            ['Garden Maintenance', 'Year-round care plans: mowing, pruning, weeding and seasonal tidy-ups.', false,
                'Keep a new garden looking the way it did on handover day. Plans are monthly or fortnightly, and the same small team visits each time.',
                ['Lawn care and mowing', 'Pruning and hedge cutting', 'Weeding and border care', 'Spring and autumn tidy-ups']],
        ];

        foreach ($services as $i => [$title, $summary, $featured, $intro, $included]) {
            $page = $this->page(ServicePage::class, $title, null, $holder->ID, $i + 1, [
                'Summary' => $summary,
                'Featured' => $featured,
                'ShowOnHome' => $featured,
                'SortOrder' => $i + 1,
                'MetaDescription' => $summary,
                'ServiceImageID' => $this->imageID('service-' . singleton(SiteTree::class)->generateURLSegment($title)),
            ]);
            if (!$page) {
                continue;
            }
            $items = implode('', array_map(fn ($item) => '<li>' . htmlspecialchars($item) . '</li>', $included));
            $this->block($page, ElementContent::class, [
                'Title' => 'Overview',
                'ShowTitle' => false,
                'BlockWidth' => 'narrow',
                'HTML' => "<p class=\"lead\">{$intro}</p><h2>What's included</h2><ul>{$items}</ul>",
            ]);
            $this->publish($page);
        }

        return $holder;
    }

    private function aboutPage(): ?SiteTree
    {
        $about = $this->page(Page::class, 'About Us', 'about-us', 0, 3, [
            'MetaDescription' => 'Fernbrook Landscapes is a family-run garden design and build company with its own in-house crew.',
        ]);
        if (!$about) {
            return SiteTree::get_by_link('about-us');
        }

        $this->block($about, ElementContent::class, [
            'Title' => 'Our story',
            'ShowTitle' => false,
            'BlockWidth' => 'narrow',
            'HTML' => '<p class="lead">Fernbrook started in 2009 with one van, one mower and a habit of saying yes to the jobs nobody else wanted.</p>'
                . '<p>Fifteen years on we\'re a team of fourteen: designers, builders, carpenters and gardeners who all work for us directly. That\'s still the thing we\'re proudest of. When you hire Fernbrook, you get the same people from the first visit to the final walk-round.</p>'
                . '<h2>How we work</h2><p>We take on a limited number of build projects each season so every garden gets a lead builder on site every day. Between builds, our maintenance team looks after more than two hundred gardens across the area.</p>',
        ]);

        if (class_exists(ElementImage::class)) { // nswdpc/silverstripe-elemental-image
            $this->block($about, ElementImage::class, [
                'Title' => 'In the nursery',
                'ShowTitle' => false,
                'ImageID' => $this->imageID('about'),
                'Height' => 'medium',
            ]);
        }

        $this->block($about, ElementPartners::class, [
            'Title' => 'Who we work with',
            'Intro' => '<p>Local suppliers we\'ve relied on for years, for stone, plants and lighting.</p>',
            'Source' => 'all',
            'Display' => 'cards',
            'ColumnsSmall' => 1,
            'ColumnsMedium' => 3,
            'ColumnsLarge' => 3,
        ]);

        return $this->publish($about);
    }

    private function contactPage(): ?SiteTree
    {
        $contact = $this->page(Page::class, 'Contact Us', 'contact-us', 0, 4, [
            // The site-wide ribbon asks visitors to get in touch; not needed here
            'FooterRibbonMode' => 'none',
            'MetaDescription' => 'Book a free garden design consultation with Fernbrook Landscapes.',
        ]);
        if (!$contact) {
            return SiteTree::get_by_link('contact-us');
        }

        $this->block($contact, ElementContent::class, [
            'Title' => 'Contact details',
            'ShowTitle' => false,
            'BlockWidth' => 'narrow',
            'HTML' => '<p class="lead">Tell us a little about your garden and what you\'d like to change. We reply to every enquiry within one working day.</p>'
                . '<p><strong>Phone:</strong> ' . self::PHONE . '<br><strong>Email:</strong> <a href="mailto:' . self::EMAIL . '">' . self::EMAIL . '</a></p>'
                . '<p><strong>Office hours:</strong><br>Monday to Friday, 8am to 5pm<br>Saturday, 9am to 12pm</p>',
        ]);
        // Top level, not inside a Section: elemental routes form submissions to page-level blocks
        if (class_exists(ElementForm::class)) { // dnadesign/silverstripe-elemental-userforms
            $this->block($contact, ElementForm::class, [
                'Title' => 'Send us a message',
                'BlockWidth' => 'narrow',
                'SubmitButtonText' => 'Send enquiry',
                'OnCompleteMessage' => '<p>Thanks, we\'ve got your message and will be in touch within one working day.</p>',
            ], fn (ElementForm $form) => $this->formFields($form));
        }

        return $this->publish($contact);
    }

    /**
     * antlion/store-location: a "Our yards" holder with two locations, each
     * with weekly hours, holiday hours, departments and staff. Skipped when
     * the module isn't installed.
     */
    private function locationsPage(): ?SiteTree
    {
        if (!class_exists(StoreLocationHolderPage::class)) {
            $this->say('  antlion/store-location not installed, skipping locations');
            return null;
        }

        $holder = $this->page(StoreLocationHolderPage::class, 'Our Yards', 'locations', 0, 5, [
            'MetaDescription' => 'Visit a Fernbrook Landscapes yard: opening hours, directions and the team at each location.',
        ]);
        if (!$holder) {
            return SiteTree::get_by_link('locations');
        }
        $this->publish($holder);

        $departments = [];
        foreach (self::DEPARTMENTS as $title => $email) {
            $departments[$title] = StoreDepartment::get()->filter('Title', $title)->first()
                ?? StoreDepartment::create(['Title' => $title, 'Email' => $email]);
            $departments[$title]->write();
        }

        $yards = [
            [
                'Title' => 'Westbrook Yard',
                'LocationName' => 'Fernbrook Landscapes, Westbrook',
                'Address' => '14 Mill Lane',
                'City' => 'Westbrook',
                'State' => 'OR',
                'Zip' => '97001',
                'Phone' => self::PHONE,
                'Email' => 'westbrook@fernbrook.example',
                'Notes' => 'Free parking in the yard. Design consultations by appointment.',
                // Loads only after consent to the "External" cookie group (store-location map_consent_group)
                'MapEmbedURL' => 'https://www.google.com/maps?q=Westbrook%2C+OR&output=embed',
                // Coordinates set here, so seeding never calls the geocoder (demo addresses are fictional)
                'Latitude' => 45.4871,
                'Longitude' => -122.8037,
                '_departments' => ['Design', 'Construction', 'Maintenance'],
                '_saturday' => ['09:00:00', '13:00:00'],
            ],
            [
                'Title' => 'Old Town Garden Centre',
                'LocationName' => 'Fernbrook Garden Centre',
                'Address' => '2 Station Road',
                'Address2' => 'Unit 4',
                'City' => 'Old Town',
                'State' => 'OR',
                'Zip' => '97002',
                'Phone' => '(555) 014-2291',
                'Email' => 'oldtown@fernbrook.example',
                'Notes' => 'Plants, paving samples and maintenance plan sign-ups.',
                'Latitude' => 45.5231,
                'Longitude' => -122.6765,
                '_departments' => ['Maintenance'],
                '_saturday' => ['09:00:00', '16:00:00'],
            ],
            [
                'Title' => 'Riverside Depot',
                'LocationName' => 'Fernbrook Riverside Depot',
                'Address' => '88 Wharf Street',
                'City' => 'Riverside',
                'State' => 'OR',
                'Zip' => '97003',
                'Phone' => '(555) 014-2292',
                'Email' => 'riverside@fernbrook.example',
                'Notes' => 'Bulk materials: soil, gravel and mulch by the bag or the tonne.',
                'Latitude' => 45.4984,
                'Longitude' => -122.4313,
                '_departments' => ['Construction'],
                '_saturday' => ['08:00:00', '12:00:00'],
            ],
        ];

        $locations = [];
        foreach ($yards as $sort => $yard) {
            $extra = array_filter($yard, fn ($key) => $key[0] !== '_' && $key !== 'Title', ARRAY_FILTER_USE_KEY);
            $page = $this->page(StoreLocationPage::class, $yard['Title'], null, $holder->ID, $sort + 1, $extra);
            if (!$page) {
                continue;
            }
            foreach ($yard['_departments'] as $title) {
                $page->Departments()->add($departments[$title]);
            }
            $this->hours($page, $yard['_saturday']);
            $this->publish($page);
            $locations[$yard['Title']] = $page;
        }

        // Staff: [first, last, title, department, location]
        $staff = [
            ['Alex', 'Moreno', 'Lead Garden Designer', 'Design', 'Westbrook Yard'],
            ['Sam', 'Okoro', 'Construction Manager', 'Construction', 'Westbrook Yard'],
            ['Jo', 'Lindqvist', 'Maintenance Team Lead', 'Maintenance', 'Westbrook Yard'],
            ['Ravi', 'Patel', 'Garden Centre Manager', 'Maintenance', 'Old Town Garden Centre'],
        ];
        foreach ($staff as [$first, $last, $title, $dept, $location]) {
            if (!isset($locations[$location])) {
                continue;
            }
            $member = StaffMember::create([
                'FirstName' => $first,
                'LastName' => $last,
                'Title' => $title,
                'Email' => strtolower($first) . '@fernbrook.example',
            ]);
            $member->write();
            $member->Departments()->add($departments[$dept]);
            $member->Locations()->add($locations[$location]);
        }
        $this->say('  Added ' . count($staff) . ' staff across ' . count($locations) . ' locations');

        return $holder;
    }

    /**
     * Adjusts the schedule store-location created for the page (Mon–Sun
     * 08:00–17:00): Saturday hours, closed Sundays, and two holiday entries.
     */
    private function hours(StoreLocationPage $page, array $saturday): void
    {
        $schedule = $page->DefaultSchedule();
        if (!$schedule->exists()) {
            return;
        }
        foreach ($schedule->Hours() as $hour) {
            match ((int) $hour->DayOfWeek) {
                6 => $hour->update(['OpenTime' => $saturday[0], 'CloseTime' => $saturday[1]]),
                7 => $hour->update(['IsClosed' => true]),
                default => null,
            };
            $hour->write();
        }

        $year = (int) DBDatetime::now()->Format('y');
        StoreHolidayHour::create([
            'StoreScheduleID' => $schedule->ID,
            'Date' => "{$year}-12-25",
            'IsRecurring' => true,
            'IsClosed' => true,
            'Note' => 'Christmas Day',
        ])->write();
        StoreHolidayHour::create([
            'StoreScheduleID' => $schedule->ID,
            'Date' => date('Y-m-d', strtotime('+10 days', DBDatetime::now()->getTimestamp())),
            'IsClosed' => false,
            'OpenTime' => '10:00:00',
            'CloseTime' => '14:00:00',
            'Note' => 'Stocktake (short day)',
        ])->write();
    }

    private function formFields(ElementForm $form): void
    {
        $fields = [
            [EditableFormStep::class, 'First page', []],
            [EditableTextField::class, 'Your name', ['Required' => true]],
            [EditableEmailField::class, 'Email', ['Required' => true]],
            [EditableTextField::class, 'Phone (optional)', []],
            [EditableTextField::class, 'Tell us about your garden', ['Required' => true, 'Rows' => 6]],
        ];
        foreach ($fields as $sort => [$class, $title, $extra]) {
            $class::create([
                'Title' => $title,
                'Sort' => $sort + 1,
                'ParentID' => $form->ID,
                'ParentClass' => ElementForm::class,
                ...$extra,
            ])->write();
        }
    }

    /** --refresh-images: re-download every slot now, so existing pages get the new files too */
    private function refreshUsedImages(): void
    {
        foreach (array_keys(self::IMAGE_QUERIES) as $slot) {
            $this->imageID($slot);
        }
        $this->images->refresh(false);
    }

    /** A demo image's ID for a slot in IMAGE_QUERIES (0 if it couldn't be made) */
    private function imageID(string $slot): int
    {
        [$query, $alt] = self::IMAGE_QUERIES[$slot];

        return (int) $this->images->get($slot, $query, $alt)?->ID;
    }

    /**
     * Pixabay credits at the end of About, for the photos actually used,
     * kept in line with demo/images.json.
     */
    private function photoCredits(?SiteTree $about): void
    {
        $credits = array_intersect_key($this->images->credits(), self::IMAGE_QUERIES);
        if (!$about || !$credits || !$about->ElementalAreaID) {
            return;
        }

        $items = '';
        foreach ($credits as $credit) {
            $items .= sprintf(
                '<li><a href="%s">Photo #%d</a> by <a href="%s">Pixabay user %s</a></li>',
                htmlspecialchars($credit['pageURL']),
                $credit['id'],
                htmlspecialchars($credit['userURL']),
                htmlspecialchars($credit['user'])
            );
        }
        $html = '<p><small>Demo photos from <a href="https://pixabay.com/">Pixabay</a>:</small></p><ul class="text-sm">' . $items . '</ul>';

        // Kept current when photos change (e.g. a new pick in demo/images.json)
        $existing = ElementContent::get()->filter(['ParentID' => $about->ElementalAreaID, 'Title' => 'Photo credits'])->first();
        if ($existing) {
            if ($existing->HTML !== $html) {
                $existing->HTML = $html;
                $existing->write();
                $existing->publishRecursive();
                $this->say('  Updated photo credits on About');
            }
            return;
        }
        $this->block($about, ElementContent::class, [
            'Title' => 'Photo credits',
            'ShowTitle' => false,
            'BlockWidth' => 'narrow',
            'HTML' => $html,
        ]);
        $this->publish($about);
    }

    // ---- Library records --------------------------------------------------------

    private function ribbon(string $title, array $fields): RibbonCta
    {
        $ribbon = RibbonCta::get()->filter('Title', $title)->first();
        if ($ribbon) {
            $this->say("  Ribbon CTA '{$title}' exists, reusing it");
            return $ribbon;
        }

        $ribbon = RibbonCta::create(['Title' => $title, ...$fields]);
        $ribbon->write();
        $this->say("  Created Ribbon CTA '{$title}'");

        return $ribbon;
    }

    /** @param Link[] $links */
    private function ribbonLinks(RibbonCta $ribbon, array $links): void
    {
        if ($ribbon->Links()->exists()) {
            return;
        }
        $this->addLinks($ribbon, $links);
    }

    private function partners(): void
    {
        $partners = [
            'Northstone Quarries' => 'Our main supplier of natural stone paving and walling, quarried and cut within 80 miles of the yard.',
            'Greenroot Nurseries' => 'Family nursery growing most of the trees, hedging and perennials we plant, so stock is acclimatised to local conditions.',
            'Lumen Garden Lighting' => 'Low-voltage fittings and controls, with a ten-year warranty on every installation we design.',
        ];
        $sort = 1;
        foreach ($partners as $title => $summary) {
            $this->libraryRecord(Partner::class, $title, ['Summary' => $summary, 'SortOrder' => $sort++]);
        }
    }

    private function testimonies(): void
    {
        $quotes = [
            'Priya & Tom, Westbrook' => 'They turned a sloping, muddy plot into three terraces we actually use every evening. The crew left the site cleaner each day than they found it.',
            'Margaret L., Old Town' => 'I\'ve had Fernbrook on a maintenance plan for six years. Same two gardeners every visit, and they know every plant by name.',
            'The Okafor family' => 'Clear quote, no surprises, finished two days early. The lighting has completely changed how we use the garden in winter.',
        ];
        $sort = 1;
        foreach ($quotes as $title => $quote) {
            $this->libraryRecord(Testimony::class, $title, ['Testimony' => $quote, 'SortOrder' => $sort++]);
        }
    }

    /**
     * A harmless stand-in for an analytics snippet (Settings > Scripts), so
     * the demo shows consent gating: it only runs after the visitor accepts
     * "Analytics" in the cookie banner.
     */
    private function demoScript(): void
    {
        $config = SiteConfig::current_site_config();
        if ($config->Scripts()->filter('Title', self::DEMO_SCRIPT)->exists()) {
            return;
        }
        $config->Scripts()->add(SiteScript::create([
            'Title' => self::DEMO_SCRIPT,
            'Placement' => 'head',
            'ConsentGroup' => 'Analytics',
            'Code' => "<script>\n  // Stand-in for an analytics snippet: runs only after consent to Analytics\n"
                . "  window.fernbrookAnalytics = true;\n  console.info('Demo analytics loaded (Analytics consent given)');\n</script>",
        ]));
        $this->say("  Added script '" . self::DEMO_SCRIPT . "' (Settings > Scripts, Analytics group)");
    }

    private function libraryRecord(string $class, string $title, array $fields): void
    {
        if ($class::get()->filter('Title', $title)->exists()) {
            return;
        }
        $class::create(['Title' => $title, ...$fields])->write();
        $this->say('  Created ' . singleton($class)->i18n_singular_name() . " '{$title}'");
    }

    // ---- Settings and menus ---------------------------------------------------

    private function settings(RibbonCta $footerRibbon): void
    {
        $config = SiteConfig::current_site_config();
        $changed = false;

        if (!$config->FooterRibbonID || $this->reset) {
            $config->FooterRibbonID = $footerRibbon->ID;
            $changed = true;
        }

        if ($this->reset) {
            $config->update([
                'Title' => 'Fernbrook Landscapes',
                'Tagline' => 'Gardens and outdoor spaces, designed and built to last.',
                // silverwind-settings brand colours (Settings > Theme)
                'ThemePrimary' => '#2f6b3a',
                'ThemeSecondary' => '#1f2a24',
                'ThemeAccent' => '#c8963e',
            ]);
            $changed = true;
        }

        if ($changed) {
            $config->write();
            $this->say('  Updated site settings' . ($this->reset ? ' (name, tagline, brand colours, footer ribbon)' : ' (footer ribbon)'));
        } else {
            $this->say('  Site settings left as they are (use --reset to apply the demo name and colours)');
        }
    }

    private function menus(?SiteTree $services, ?SiteTree $about, ?SiteTree $contact): void
    {
        if (!class_exists(MenuSet::class)) { // dnadesign/silverstripe-setmenu
            return;
        }
        $serviceLinks = $services
            ? array_map(fn (SiteTree $page) => $this->pageLink($page, $page->Title), $services->Children()->toArray())
            : [];

        $sets = [
            'Footer Column 01' => ['Services', $serviceLinks],
            'Footer Column 02' => ['Company', array_filter([
                $about ? $this->pageLink($about, 'About us') : null,
                $contact ? $this->pageLink($contact, 'Contact') : null,
            ])],
        ];

        foreach ($sets as $key => [$name, $links]) {
            $set = MenuSet::get()->filter('Key', $key)->first();
            if (!$set) {
                $this->say("  Menu set '{$key}' not found (run db:build first), skipping");
                continue;
            }
            if ($set->MenuItems()->exists() && !$this->reset) {
                $this->say("  Menu '{$set->Name}' already has links, leaving it");
                continue;
            }
            foreach ($set->MenuItems() as $existing) {
                $this->deleteLink($existing);
            }
            $set->Name = $name; // the footer shows the set's name as the column heading
            $set->write();
            $set->publishSingle(); // MenuSet is versioned
            $this->addLinks($set, $links);
            $this->say("  Filled footer menu '{$name}'");
        }
    }

    // ---- Building blocks ------------------------------------------------------

    /**
     * A draft page, or null if one already exists at that URL (pass a null
     * $urlSegment to let Silverstripe generate it from the title).
     */
    private function page(string $class, string $title, ?string $urlSegment, int $parentID, int $sort, array $extra = []): ?SiteTree
    {
        $segment = $urlSegment ?? singleton(SiteTree::class)->generateURLSegment($title);
        $existing = SiteTree::get()->filter(['URLSegment' => $segment, 'ParentID' => $parentID])->first();
        if ($existing) {
            $this->say("  Skipped '{$title}': a page already exists at /{$segment} (ID {$existing->ID})");
            return null;
        }

        /** @var SiteTree $page */
        $page = $class::create([
            'Title' => $title,
            'URLSegment' => $segment,
            'ParentID' => $parentID,
            'Sort' => $sort,
            ...$extra,
        ]);
        $page->write();
        $this->say("Created page '{$title}' (/" . ltrim($page->RelativeLink(), '/') . ")");

        return $page;
    }

    private function publish(SiteTree $page): SiteTree
    {
        $page->publishRecursive();

        return $page;
    }

    /**
     * Adds a block to the end of a page's main area. $then runs after the
     * block is written, to add items that need its ID.
     */
    private function block(SiteTree $page, string $class, array $fields, ?callable $then = null): BaseElement
    {
        return $this->nestedBlock($this->area($page, 'ElementalArea'), $class, $fields, $then);
    }

    /**
     * The saved ElementalArea behind a has_one (a page's main area, or a
     * Section's "Elements"), created if elemental hasn't made it yet.
     */
    private function area(DataObject $owner, string $relation): ElementalArea
    {
        $area = $owner->getComponent($relation);
        if (!$area->isInDB()) {
            $area = ElementalArea::create(['OwnerClassName' => $owner->ClassName]);
            $area->write();
            $owner->setField("{$relation}ID", $area->ID);
            $owner->write();
        }

        return $area;
    }

    private function nestedBlock(ElementalArea $area, string $class, array $fields, ?callable $then = null): BaseElement
    {
        /** @var BaseElement $block */
        $block = $class::create([
            'ShowTitle' => true,
            ...$fields,
            'ParentID' => $area->ID,
            'Sort' => (int) $area->Elements()->max('Sort') + 1,
        ]);
        $block->write();
        if ($then) {
            $then($block);
        }
        $this->say("    + {$block->getType()}: {$block->Title}");

        return $block;
    }

    /**
     * An unsaved link to a page (a page object, or a URL like "contact-us"),
     * optionally styled as a button (silverwind-blocks LinkStyleExtension).
     */
    private function pageLink(SiteTree|string $page, string $text, ?string $style = null): ?SiteTreeLink
    {
        $page = is_string($page) ? SiteTree::get_by_link($page) : $page;
        if (!$page) {
            return null;
        }

        return SiteTreeLink::create(['PageID' => $page->ID, 'LinkText' => $text, 'ButtonStyle' => $style]);
    }

    private function phoneLink(string $text, ?string $style = null): PhoneLink
    {
        return PhoneLink::create([
            'Phone' => preg_replace('/[^0-9+]/', '', self::PHONE),
            'LinkText' => $text,
            'ButtonStyle' => $style,
        ]);
    }

    /**
     * Attaches links to an owner's has_many "Links" (or a MenuSet's
     * "MenuItems") and publishes them: links are versioned, and owners like
     * RibbonCta and MenuSet aren't published recursively.
     *
     * @param array<Link|null> $links
     */
    private function addLinks(DataObject $owner, array $links): void
    {
        $relation = $owner instanceof MenuSet ? 'MenuItems' : 'Links';
        $sort = 1;
        foreach (array_filter($links) as $link) {
            $link->update([
                'OwnerID' => $owner->ID,
                'OwnerClass' => $owner->ClassName,
                'OwnerRelation' => $relation,
                'Sort' => $sort++,
            ]);
            $link->write();
            $link->publishSingle();
        }
    }

    private function deleteLink(Link $link): void
    {
        $link->doArchive();
    }

    // ---- Reset ----------------------------------------------------------------

    private function archiveDemoPages(): void
    {
        foreach (['home', 'services', 'about-us', 'contact-us', 'locations'] as $segment) {
            $page = SiteTree::get()->filter(['URLSegment' => $segment, 'ParentID' => 0])->first();
            if ($page) {
                $this->archiveTree($page);
                $this->say("  Archived /{$segment} (restore it from Pages > Archive if needed)");
            }
        }
    }

    private function archiveTree(SiteTree $page): void
    {
        foreach (SiteTree::get()->filter('ParentID', $page->ID) as $child) {
            $this->archiveTree($child);
        }
        $page->doArchive();
    }

    private function deleteLibraryRecords(): void
    {
        $titles = [
            RibbonCta::class => ['Thinking about your garden?', 'Our next-season diary is open'],
            Partner::class => ['Northstone Quarries', 'Greenroot Nurseries', 'Lumen Garden Lighting'],
            Testimony::class => ['Priya & Tom, Westbrook', 'Margaret L., Old Town', 'The Okafor family'],
        ];
        foreach (SiteScript::get()->filter('Title', self::DEMO_SCRIPT) as $script) {
            $script->delete();
        }
        if (class_exists(StoreDepartment::class)) {
            $titles[StoreDepartment::class] = array_keys(self::DEPARTMENTS);
            foreach (StaffMember::get()->filter('Email', ['alex@fernbrook.example', 'sam@fernbrook.example', 'jo@fernbrook.example', 'ravi@fernbrook.example']) as $member) {
                $member->delete();
            }
            // Schedules are created per location page and aren't cascade-deleted
            foreach (StoreSchedule::get()->filter('Title', ['Westbrook Yard Schedule', 'Old Town Garden Centre Schedule', 'Riverside Depot Schedule']) as $schedule) {
                foreach ([...$schedule->Hours(), ...$schedule->HolidayHours()] as $row) {
                    $row->delete();
                }
                $schedule->delete();
            }
        }
        foreach ($titles as $class => $list) {
            foreach ($class::get()->filter('Title', $list) as $record) {
                if ($record->hasMethod('Links')) {
                    foreach ($record->Links() as $link) {
                        $this->deleteLink($link);
                    }
                }
                $record->delete();
            }
        }
    }

    private function say(string $line): void
    {
        $this->output->writeln($line);
    }
}
