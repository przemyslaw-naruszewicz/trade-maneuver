<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The browser tests set `app.vite_hot_file` to a path that does not exist, so their application serves the production
 * build even while the development Vite server runs and `public/hot` exists.
 */
final class ViteHotFileTest extends TestCase
{
    private const string VITE_DEV_SERVER = 'http://localhost:5173';

    private string $publicPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publicPath = sys_get_temp_dir().'/trade-maneuver-vite-hot-file-test-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->publicPath.'/build');
        File::put($this->publicPath.'/hot', self::VITE_DEV_SERVER);
        File::put($this->publicPath.'/build/manifest.json', (string) json_encode([
            'resources/js/app.js' => ['file' => 'assets/app-production.js', 'src' => 'resources/js/app.js', 'isEntry' => true],
        ]));
        $this->app->usePublicPath($this->publicPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicPath);

        parent::tearDown();
    }

    public function test_a_page_references_the_production_build_when_the_hot_file_setting_names_a_missing_file(): void
    {
        $this->bootWithHotFile($this->publicPath.'/missing-hot');

        $this->blade("@vite('resources/js/app.js')")
            ->assertSee('/build/assets/app-production.js', false)
            ->assertDontSee(self::VITE_DEV_SERVER, false);
    }

    public function test_a_page_references_the_vite_dev_server_without_the_hot_file_setting(): void
    {
        $this->bootWithHotFile(null);

        $this->blade("@vite('resources/js/app.js')")
            ->assertSee(self::VITE_DEV_SERVER.'/@vite/client', false)
            ->assertDontSee('/build/assets/app-production.js', false);
    }

    private function bootWithHotFile(?string $path): void
    {
        config(['app.vite_hot_file' => $path]);
        $provider = $this->app->getProvider(AppServiceProvider::class);
        $this->assertInstanceOf(AppServiceProvider::class, $provider);
        $provider->boot();
    }
}
