<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config)
    ->setRules([
        '@PSR12' => true,
        'braces_position' => ['anonymous_classes_opening_brace' => 'next_line_unless_newline_at_signature_end'],
        'new_with_parentheses' => ['anonymous_class' => false, 'named_class' => false],
        'single_line_empty_body' => true,
        'single_trait_insert_per_statement' => false,
    ])
    ->setCacheFile(__DIR__.'/storage/framework/cache/php-cs-fixer.cache')
    ->setFinder(
        Finder::create()
            ->in(__DIR__)
            ->exclude(['vendor', 'node_modules', 'storage', 'bootstrap/cache', '.agents', '.codex', '.aws', '.claude'])
            ->ignoreDotFiles(false)
            ->notPath('resources/views')
    );
