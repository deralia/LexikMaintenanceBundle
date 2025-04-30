<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withRules([
        TypedPropertyFromStrictConstructorRector::class,
    ])
    ->withSets([
        // ...
    ])
    ->withPaths([
        __DIR__ . '/Command',
        __DIR__ . '/DependencyInjection',
        __DIR__ . '/Drivers',
        __DIR__ . '/Exception',
        __DIR__ . '/Listener',
    ])
    ->withPhpVersion(PhpVersion::PHP_80)
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0);
