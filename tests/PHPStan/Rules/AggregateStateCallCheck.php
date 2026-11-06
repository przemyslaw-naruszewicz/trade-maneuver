<?php

declare(strict_types=1);

namespace Tests\PHPStan\Rules;

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Enforces ADR-0002: an aggregate's state() is read only by repository implementations and tests.
 * Every state() method declared in a context's Domain namespace counts as the aggregate state contract,
 * so new aggregates need no configuration.
 */
final class AggregateStateCallCheck
{
    private const string DOMAIN_NAMESPACE = '/^TradeManeuver\\\\[^\\\\]+\\\\Domain\\\\/';

    private const string INFRASTRUCTURE_NAMESPACE = '/^TradeManeuver\\\\[^\\\\]+\\\\Infrastructure\\\\/';

    private const string REPOSITORY_INTERFACE_SUFFIX = 'RepositoryInterface';

    private const string TESTS_NAMESPACE = 'Tests\\';

    /**
     * @return list<IdentifierRuleError>
     */
    public function check(Expr $calledOn, Identifier|Expr $methodName, Scope $scope): array
    {
        if (! $methodName instanceof Identifier || $methodName->toLowerString() !== 'state') {
            return [];
        }
        if ($this->isAllowedCaller($scope)) {
            return [];
        }

        $errors = [];
        foreach ($scope->getType($calledOn)->getObjectClassReflections() as $classReflection) {
            if ($this->declaresAggregateState($classReflection)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Call to %s::state() outside a repository implementation or a test. The aggregate state is a persistence contract (ADR-0002).',
                    $classReflection->getName(),
                ))->identifier('tradeManeuver.aggregateStateCall')->build();
            }
        }

        return $errors;
    }

    private function declaresAggregateState(ClassReflection $classReflection): bool
    {
        if (! $classReflection->hasNativeMethod('state')) {
            return false;
        }

        $declaringClass = $classReflection->getNativeMethod('state')->getDeclaringClass()->getName();

        return preg_match(self::DOMAIN_NAMESPACE, $declaringClass) === 1;
    }

    private function isAllowedCaller(Scope $scope): bool
    {
        if (str_starts_with($scope->getNamespace() ?? '', self::TESTS_NAMESPACE)) {
            return true;
        }

        $callerClass = $scope->getClassReflection();

        return $callerClass !== null && $this->isRepositoryImplementation($callerClass);
    }

    private function isRepositoryImplementation(ClassReflection $classReflection): bool
    {
        if (preg_match(self::INFRASTRUCTURE_NAMESPACE, $classReflection->getName()) !== 1) {
            return false;
        }

        foreach ($classReflection->getInterfaces() as $interface) {
            if (str_ends_with($interface->getName(), self::REPOSITORY_INTERFACE_SUFFIX)) {
                return true;
            }
        }

        return false;
    }
}
