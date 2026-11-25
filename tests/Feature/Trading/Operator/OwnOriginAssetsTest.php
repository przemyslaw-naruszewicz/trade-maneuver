<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\TestCase;

/**
 * Renders the pages against a copy of a production build manifest in tests/Fixtures/vite-build.
 */
final class OwnOriginAssetsTest extends TestCase
{
    use OperatorAccounts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEnrolledOperator();
    }

    public function test_the_login_page_references_fonts_styles_and_scripts_only_on_the_application_origin(): void
    {
        $this->assertReferencesOnlyTheApplicationOrigin((string) $this->get(route('login'))->assertOk()->getContent());
    }

    public function test_the_panel_layout_references_fonts_styles_and_scripts_only_on_the_application_origin(): void
    {
        $html = (string) $this->actingAs($this->operatorModel())->get(route('panel.start'))->assertOk()->getContent();

        $this->assertReferencesOnlyTheApplicationOrigin($html);
    }

    private function assertReferencesOnlyTheApplicationOrigin(string $html): void
    {
        $page = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $references = $this->references($page);

        foreach ($references as $reference) {
            $this->assertTrue($this->isOnTheApplicationOrigin($reference), sprintf('"%s" is not on the application origin.', $reference));
        }

        $this->assertNotNull($page->querySelector('link[rel="stylesheet"][href*="/build/assets/app-"]'), 'The page has no style sheet.');
        $this->assertNotNull($page->querySelector('script[type="module"][src*="/build/assets/app-"]'), 'The page has no script.');
        $this->assertNotNull($page->querySelector('link[rel="preload"][as="font"][href*="/build/assets/ibm-plex-sans-"]'), 'The page preloads no font.');
        $this->assertStringContainsString('url("/build/assets/jost-', $html, 'The page declares no font face.');
    }

    /**
     * Every URL the page loads: element sources and links, and url() values in inline styles.
     *
     * @return list<string>
     */
    private function references(HTMLDocument $page): array
    {
        return [...$this->elementReferences($page), ...$this->styleReferences($page)];
    }

    /**
     * @return list<string>
     */
    private function elementReferences(HTMLDocument $page): array
    {
        $references = [];
        foreach (['[src]' => 'src', 'link[href]' => 'href', '[srcset]' => 'srcset'] as $selector => $attribute) {
            foreach ($page->querySelectorAll($selector) as $element) {
                $references[] = (string) $element->getAttribute($attribute);
            }
        }

        return $references;
    }

    /**
     * @return list<string>
     */
    private function styleReferences(HTMLDocument $page): array
    {
        $styles = '';
        foreach ($page->querySelectorAll('style') as $element) {
            $styles .= $element->textContent;
        }
        foreach ($page->querySelectorAll('[style]') as $element) {
            $styles .= $element->getAttribute('style');
        }
        preg_match_all('/url\(\s*[\'"]?([^\'")]+)/', $styles, $matches);

        return $matches[1];
    }

    private function isOnTheApplicationOrigin(string $reference): bool
    {
        if (str_starts_with($reference, 'data:')) {
            return true;
        }

        $hasScheme = preg_match('/^[a-z][a-z0-9+.-]*:/i', $reference) === 1;
        if (! $hasScheme) {
            return ! str_starts_with($reference, '//');
        }

        return str_starts_with($reference, rtrim((string) config('app.url'), '/').'/');
    }
}
