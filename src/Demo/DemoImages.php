<?php

namespace Antlion\SilverwindSite\Demo;

use GuzzleHttp\Client;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Environment;
use Throwable;

/**
 * Photos for the demo site (SeedDemoSiteTask), from Pixabay.
 *
 * Each image has a "slot" (e.g. "service-patios") with a search. The first
 * run searches Pixabay, picks a real photo (not AI-generated, at least
 * 1600px wide, not used by another slot) and records its ID in
 * demo/images.json in this module, so later seeds and teammates get the same photos
 * (and stable screenshots). Images are downloaded into assets/Demo and
 * reused on later runs, so a normal re-seed makes no API calls at all.
 *
 * Without PIXABAY_API_KEY, or when Pixabay can't be reached, a generated
 * gradient placeholder in the brand colours is used instead, so seeding
 * never fails (CI, offline).
 *
 * Pixabay terms: images are downloaded rather than hotlinked, as the API
 * docs ask; API results aren't kept beyond the photo ID and credit.
 */
class DemoImages
{
    private const string API = 'https://pixabay.com/api/';

    /** Where chosen photo IDs are recorded; relative to this module unless absolute */
    private const string MANIFEST = 'demo/images.json';

    private const string FOLDER = 'Demo';

    private const int MIN_WIDTH = 1600;

    private array $manifest = [];

    private bool $dirty = false;

    private ?Client $client = null;

    /** Re-download existing demo images (same photo IDs), keeping their records */
    private bool $refresh = false;

    /** @var callable(string): void */
    private $log;

    /**
     * @param string[] $palette Hex colours for placeholders, dark to light
     */
    public function __construct(callable $log, private array $palette = ['#1f3d2b', '#2f6b3a', '#c8963e'])
    {
        $this->log = $log;
        $path = $this->manifestPath();
        if (is_file($path)) {
            $this->manifest = json_decode((string) file_get_contents($path), true) ?: [];
        }
    }

    public function hasApiKey(): bool
    {
        return (bool) Environment::getEnv('PIXABAY_API_KEY');
    }

    /**
     * The image for a slot: an existing demo image, else a Pixabay photo,
     * else a placeholder.
     */
    public function get(string $slot, string $query, string $alt): ?Image
    {
        $name = $slot . '.jpg';
        $folder = Folder::find_or_make(self::FOLDER);

        $existing = Image::get()->filter(['ParentID' => $folder->ID, 'Name' => $name])->first();
        if ($existing && !$this->refresh) {
            return $existing;
        }

        [$data, $source] = $this->fromPixabay($slot, $query) ?? [$this->placeholder($slot), 'placeholder'];
        if (!$data) {
            return $existing;
        }

        // On refresh, the same record gets the new file, so pages using it keep working
        $image = $existing ?: Image::create();
        $image->setFromString($data, self::FOLDER . '/' . $name);
        $image->Title = $alt;
        $image->ParentID = $folder->ID;
        $image->write();
        $image->publishSingle();

        ($this->log)("    image {$slot}: " . ($source === 'pixabay' ? 'Pixabay #' . $this->manifest[$slot]['id'] : 'placeholder'));

        return $image;
    }

    /**
     * Re-downloads every demo image used from now on (same photo IDs from
     * the manifest), replacing each existing image's file in place.
     */
    public function refresh(bool $on = true): void
    {
        $this->refresh = $on;
    }

    /**
     * Credits for the photos that came from Pixabay, for slots in use.
     *
     * @return array<string, array{id:int, pageURL:string, userURL:string, user:string}>
     */
    public function credits(): array
    {
        return array_filter($this->manifest, fn ($entry) => !empty($entry['id']));
    }

    /** Writes demo/images.json if new photos were chosen, and fixes file ownership */
    public function save(): void
    {
        $this->matchAssetsOwner();
        if (!$this->dirty) {
            return;
        }
        ksort($this->manifest);
        file_put_contents(
            $this->manifestPath(),
            json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );
        $this->dirty = false;
    }

    /** @return array{0: string, 1: string}|null [jpeg bytes, 'pixabay'] */
    private function fromPixabay(string $slot, string $query): ?array
    {
        if (!$this->hasApiKey()) {
            return null;
        }

        try {
            $hit = isset($this->manifest[$slot]['id'])
                ? $this->lookup((int) $this->manifest[$slot]['id'])
                : $this->search($query);
            if (!$hit) {
                ($this->log)("    image {$slot}: no suitable Pixabay photo for \"{$query}\", using a placeholder");
                return null;
            }

            // largeImageURL is a temporary link (1280px): fetch it now, keep the file
            $data = (string) $this->client()->get($hit['largeImageURL'])->getBody();
            if (!@imagecreatefromstring($data)) {
                return null;
            }

            $entry = [
                'id' => (int) $hit['id'],
                'query' => $query,
                'pageURL' => $hit['pageURL'],
                'userURL' => $hit['userURL'] ?? ('https://pixabay.com/users/' . $hit['user_id'] . '/'),
                'user' => (string) $hit['user'],
            ];
            if (($this->manifest[$slot] ?? null) !== $entry) {
                $this->manifest[$slot] = $entry;
                $this->dirty = true;
            }

            return [$data, 'pixabay'];
        } catch (Throwable $e) {
            ($this->log)("    image {$slot}: Pixabay unavailable ({$e->getMessage()}), using a placeholder");
            return null;
        }
    }

    private function search(string $query): ?array
    {
        $taken = array_column($this->manifest, 'id');
        foreach ($this->request(['q' => $query, 'per_page' => 50, 'order' => 'popular']) as $hit) {
            if (!empty($hit['isAiGenerated']) || ($hit['imageWidth'] ?? 0) < self::MIN_WIDTH || in_array((int) $hit['id'], $taken, true)) {
                continue;
            }

            return $hit;
        }

        return null;
    }

    private function lookup(int $id): ?array
    {
        return $this->request(['id' => $id])[0] ?? null;
    }

    private function request(array $params): array
    {
        $response = $this->client()->get(self::API, ['query' => [
            'key' => Environment::getEnv('PIXABAY_API_KEY'),
            'image_type' => 'photo',
            'orientation' => 'horizontal',
            'safesearch' => 'true',
            ...$params,
        ]]);

        return json_decode((string) $response->getBody(), true)['hits'] ?? [];
    }

    private function client(): Client
    {
        return $this->client ??= new Client(['timeout' => 30, 'connect_timeout' => 10]);
    }

    /**
     * A 1600x1000 diagonal gradient in the brand colours with soft circles,
     * varied per slot so neighbouring cards don't look identical.
     */
    private function placeholder(string $slot): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $width = 1600;
        $height = 1000;
        $seed = crc32($slot);
        $rgb = fn (string $hex) => sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        [$from, $to] = [$rgb($this->palette[$seed % 2]), $rgb($this->palette[($seed % 2) + 1] ?? $this->palette[0])];

        $image = imagecreatetruecolor($width, $height);
        for ($x = 0; $x < $width; $x += 4) {
            $t = $x / $width;
            $colour = imagecolorallocate(
                $image,
                (int) ($from[0] + ($to[0] - $from[0]) * $t),
                (int) ($from[1] + ($to[1] - $from[1]) * $t),
                (int) ($from[2] + ($to[2] - $from[2]) * $t)
            );
            imagefilledrectangle($image, $x, 0, $x + 3, $height, $colour);
        }
        $light = imagecolorallocatealpha($image, 255, 255, 255, 110);
        mt_srand($seed);
        for ($i = 0; $i < 6; $i++) {
            $size = mt_rand(200, 700);
            imagefilledellipse($image, mt_rand(0, $width), mt_rand(0, $height), $size, $size, $light);
        }
        mt_srand();

        ob_start();
        imagejpeg($image, null, 82);

        return (string) ob_get_clean();
    }

    /**
     * When the task runs as root (`docker exec` without -u), files it creates
     * are root-owned and the web server can't write resized copies next to
     * them, so the first page using one fails. Give the demo folder, and the
     * manifest, the owner of the assets directory.
     */
    private function matchAssetsOwner(): void
    {
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0 || !defined('ASSETS_PATH')) {
            return;
        }
        $uid = fileowner(ASSETS_PATH);
        $gid = filegroup(ASSETS_PATH);
        $paths = [$this->manifestPath(), ASSETS_PATH . '/' . self::FOLDER];
        foreach (glob(ASSETS_PATH . '/' . self::FOLDER . '/*') ?: [] as $file) {
            $paths[] = $file;
        }
        foreach ($paths as $path) {
            if (file_exists($path)) {
                @chown($path, $uid);
                @chgrp($path, $gid);
            }
        }
    }

    private function manifestPath(): string
    {
        return dirname(__DIR__, 2) . '/' . self::MANIFEST;
    }
}
