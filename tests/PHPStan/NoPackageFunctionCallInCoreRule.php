<?php

declare(strict_types=1);

namespace Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FunctionCallableNode;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Deptrac cannot resolve an unqualified function name inside a namespace, so it misses calls such as `config()`.
 *
 * @implements Rule<Expr>
 */
final class NoPackageFunctionCallInCoreRule implements Rule
{
    public function __construct(private readonly ReflectionProvider $reflectionProvider) {}

    public function getNodeType(): string
    {
        return Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // Inside a trait, Scope::getFile() returns the file of the class that uses the trait.
        $file = $scope->getTraitReflection()?->getFileName() ?? $scope->getFile();

        if (preg_match('#/src/[^/]+/(Domain|Application)/#', $file) !== 1) {
            return [];
        }

        $name = $this->calledFunctionName($node);

        if ($name === null || ! $this->reflectionProvider->hasFunction($name, $scope)) {
            return [];
        }

        $function = $this->reflectionProvider->getFunction($name, $scope);

        if (! str_contains($function->getFileName() ?? '', '/vendor/')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Domain and Application must not call %s(), a function from an installed package.',
                $function->getName(),
            ))->identifier('tradeManeuver.packageFunctionInCore')->build(),
        ];
    }

    private function calledFunctionName(Expr $node): ?Name
    {
        if ($node instanceof FuncCall) {
            return $node->name instanceof Name ? $node->name : null;
        }

        // @phpstan-ignore phpstanApi.instanceofAssumption (PHPStan passes a first-class callable to rules only as this node.)
        if ($node instanceof FunctionCallableNode) {
            $name = $node->getName();

            return $name instanceof Name ? $name : null;
        }

        return null;
    }
}
