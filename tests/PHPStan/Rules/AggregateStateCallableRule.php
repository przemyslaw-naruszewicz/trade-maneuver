<?php

declare(strict_types=1);

namespace Tests\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\MethodCallableNode;
use PHPStan\Rules\Rule;

/**
 * Reports the first-class callable `$aggregate->state(...)` outside repository implementations and tests.
 *
 * @implements Rule<MethodCallableNode>
 */
final class AggregateStateCallableRule implements Rule
{
    public function __construct(private readonly AggregateStateCallCheck $check = new AggregateStateCallCheck) {}

    public function getNodeType(): string
    {
        return MethodCallableNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        return $this->check->check($node->getVar(), $node->getName(), $scope);
    }
}
