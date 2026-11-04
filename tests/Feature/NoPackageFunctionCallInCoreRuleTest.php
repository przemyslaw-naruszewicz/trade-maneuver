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

    public function test_the_rule_reports_package_functions_called_from_the_application_layer(): void
    {
        $this->analyse([$this->fixture('Application/PackageFunctionCalls.php')], [
            [$this->errorFor('now'), 11],
        ]);
    }

    public function test_the_rule_reports_a_package_function_used_as_a_first_class_callable(): void
    {
        $this->analyse([$this->fixture('Domain/PackageFunctionCallable.php')], [
            [$this->errorFor('config'), 11],
        ]);
    }

    public function test_the_rule_reports_a_domain_trait_used_outside_the_core(): void
    {
        $this->analyse([
            $this->fixture('Infrastructure/ClassUsingDomainTrait.php'),
            $this->fixture('Domain/PackageFunctionTrait.php'),
        ], [
            [$this->errorFor('now'), 11],
        ]);
    }

    public function test_the_rule_allows_php_and_project_functions_in_the_core(): void
    {
        $this->analyse([$this->fixture('Domain/AllowedFunctionCalls.php')], []);
    }

    public function test_the_rule_ignores_calls_from_infrastructure_and_ui(): void
    {
        $this->analyse([
            $this->fixture('Infrastructure/PackageFunctionCalls.php'),
            $this->fixture('Ui/PackageFunctionCalls.php'),
        ], []);
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
