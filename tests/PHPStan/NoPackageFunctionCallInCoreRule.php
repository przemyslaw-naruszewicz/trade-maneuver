<?php

declare(strict_types=1);

namespace Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FuncCall>
 */
final class NoPackageFunctionCallInCoreRule implements Rule
{
    public function __construct(private readonly ReflectionProvider $reflectionProvider) {}

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Name || ! $this->reflectionProvider->hasFunction($node->name, $scope)) {
            return [];
        }

        $function = $this->reflectionProvider->getFunction($node->name, $scope);

        return [
            RuleErrorBuilder::message(sprintf(
                'Domain and Application must not call %s(), a function from an installed package.',
                $function->getName(),
            ))->identifier('tradeManeuver.packageFunctionInCore')->build(),
        ];
    }
}
