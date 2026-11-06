<?php

declare(strict_types=1);

namespace Tests\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * Reports `$aggregate->state()` outside repository implementations and tests; PHPStan analyses
 * a nullsafe call as this node too.
 *
 * @implements Rule<MethodCall>
 */
final class AggregateStateCallRule implements Rule
{
    public function __construct(private readonly AggregateStateCallCheck $check = new AggregateStateCallCheck) {}

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        return $this->check->check($node->var, $node->name, $scope);
    }
}
