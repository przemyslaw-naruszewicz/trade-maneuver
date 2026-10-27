<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Tests\Behat\ApplicationContext;

return (new Config)
    ->withProfile(
        (new Profile('default'))
            ->withSuite(
                (new Suite('application'))
                    ->withPaths(__DIR__.'/tests/Behat/features')
                    ->withContexts(ApplicationContext::class)
            )
    );
