<?php

declare(strict_types=1);

use TwigCsFixer\Config\Config;
use TwigCsFixer\File\Finder;
use TwigCsFixer\Ruleset\Ruleset;
use TwigCsFixer\Standard\Symfony;

return (new Config())
    ->setRuleset((new Ruleset())->addStandard(new Symfony()))
    ->setFinder((new Finder())->in(__DIR__.'/templates'))
    ->setCacheFile(__DIR__.'/var/.twig-cs-fixer.cache');
