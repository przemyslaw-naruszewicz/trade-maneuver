<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator\Support;

use Dom\Element;
use Dom\HTMLDocument;

/**
 * Finds the root element of a Livewire component in a rendered panel page, by the component name in its snapshot.
 */
trait LivewireComponentRoots
{
    private function componentRoot(HTMLDocument $page, string $name): Element
    {
        foreach ($page->querySelectorAll('[wire\:snapshot]') as $root) {
            $snapshot = json_decode((string) $root->getAttribute('wire:snapshot'), true, 512, JSON_THROW_ON_ERROR);
            if (is_array($snapshot) && ($snapshot['memo']['name'] ?? null) === $name) {
                return $root;
            }
        }
        $this->fail(sprintf('The page renders no Livewire component %s.', $name));
    }
}
