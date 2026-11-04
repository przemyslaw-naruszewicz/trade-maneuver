<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Tests\PHPStan\NoPackageFunctionCallInCoreRule;

/**
 * @extends RuleTestCase<NoPackageFunctionCallInCoreRule>
 */
final class NoPackageFunctionCallInCoreRuleTest extends RuleTestCase
{
    public function test_the_rule_reports_package_functions_called_from_the_domain_layer(): void
    {
        $this->analyse([$this->fixture('Domain/PackageFunctionCalls.php')], [
            [$this->errorFor('config'), 13],
            [$this->errorFor('now'), 18],
            [$this->errorFor('Symfony\Component\String\u'), 23],
            [$this->errorFor('Illuminate\Support\php_binary'), 28],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoPackageFunctionCallInCoreRule(self::createReflectionProvider());
    }

    private function fixture(string $path): string
    {
        return dirname(__DIR__).'/PHPStan/Fixtures/src/Trading/'.$path;
    }

    private function errorFor(string $function): string
    {
        return sprintf('Domain and Application must not call %s(), a function from an installed package.', $function);
    }
}
