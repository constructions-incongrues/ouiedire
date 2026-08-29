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
        /** @var \Composer\Autoload\ClassLoader $loader */
        $loader = require __DIR__.'/../vendor/autoload.php';

        $this->assertNotFalse(
            $loader->findFile('Ouiedire\\Tests\\SmokeTest'),
            "Le mapping PSR-4 autoload-dev ne résout pas le namespace de test."
        );
    }
}
