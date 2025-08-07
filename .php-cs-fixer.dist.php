<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        'single_quote' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const']],
        'nullable_type_declaration' => ['syntax' => 'union'],
        'no_extra_blank_lines' => true,
    ])
    ->setFinder($finder);