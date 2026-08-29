<?php

namespace Ouiedire\Tests;

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    public function testLePiloteDeCouvertureEstCharge()
    {
        $this->assertTrue(
            extension_loaded('pcov'),
            "L'extension pcov est absente : la couverture ne serait pas mesurée."
        );
    }

    public function testLAutoloaderDeDevResoutLeNamespaceDeTest()
    {
        $prefixes = require __DIR__ . '/../vendor/composer/autoload_psr4.php';

        $this->assertArrayHasKey(
            'Ouiedire\\Tests\\',
            $prefixes,
            "Le mapping PSR-4 autoload-dev n'est pas généré."
        );
        $this->assertTrue(
            class_exists(self::class),
            'Le namespace de test ne se résout pas via le mapping PSR-4.'
        );
    }
}
