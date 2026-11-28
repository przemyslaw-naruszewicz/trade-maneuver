<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator\Support;

use Dom\Element;
use Dom\HTMLDocument;

/**
 * Renders panel pages for the logged-in operator and reads their text, for tests that use OperatorAccounts.
 */
trait RenderedPanelPages
{
    private function panelPageAt(string $routeName): HTMLDocument
    {
        $html = (string) $this->actingAs($this->operatorModel())->get(route($routeName))->assertOk()->getContent();

        return HTMLDocument::createFromString($html, LIBXML_NOERROR);
    }

    private function text(Element|HTMLDocument $element, string $selector): string
    {
        $found = $element->querySelector($selector);
        $this->assertNotNull($found, sprintf('No element matches %s.', $selector));

        return $this->collapsed((string) $found->textContent);
    }

    private function collapsed(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
