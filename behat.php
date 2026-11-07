<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Tests\Behat\ApplicationContext;
use Tests\Behat\Trading\TradeSignalIntakeContext;

return (new Config)
    ->withProfile(
        (new Profile('default'))
            ->withSuite(
                (new Suite('application'))
                    ->withPaths(__DIR__.'/tests/Behat/features')
                    ->withContexts(ApplicationContext::class)
            )
            ->withSuite(
                (new Suite('trading'))
                    ->withPaths(__DIR__.'/tests/Behat/Trading/features')
                    ->withContexts(TradeSignalIntakeContext::class)
            )
    );
