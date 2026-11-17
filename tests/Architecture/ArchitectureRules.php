<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Selector\SelectorInterface;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Repeats the dependency matrix of deptrac.yaml for src and checks class shape.
 * A row changes here, in deptrac.yaml, and in docs/architecture.md together.
 */
final class ArchitectureRules
{
    private const string TRADING = 'Trading';

    private const string DEMO_ANALYTICS = 'DemoAnalytics';

    private const array NAMESPACES = [self::TRADING, self::DEMO_ANALYTICS];

    /**
     * @return iterable<string, Rule>
     */
    #[TestRule]
    public function domainDependencies(): iterable
    {
        foreach (self::NAMESPACES as $namespace) {
            yield $namespace => PHPat::rule()
                ->classes($this->domain($namespace))
                ->canOnlyDependOn()
                ->classes($this->domain($namespace), $this->moneyLibrary());
        }
    }

    /**
     * @return iterable<string, Rule>
     */
    #[TestRule]
    public function applicationDependencies(): iterable
    {
        foreach (self::NAMESPACES as $namespace) {
            $allowed = [$this->application($namespace), $this->domain($namespace), Selector::inNamespace('Psr\Clock')];

            if ($namespace === self::TRADING) {
                $allowed[] = Selector::inNamespace('Psr\Log');
            }

            yield $namespace => PHPat::rule()
                ->classes($this->application($namespace))
                ->canOnlyDependOn()
                ->classes(...$allowed);
        }
    }

    /**
     * @return iterable<string, Rule>
     */
    #[TestRule]
    public function infrastructureDependencies(): iterable
    {
        foreach (self::NAMESPACES as $namespace) {
            yield $namespace => PHPat::rule()
                ->classes($this->infrastructure($namespace))
                ->canOnlyDependOn()
                ->classes($this->infrastructure($namespace), ...$this->outerLayerDependencies($namespace));
        }
    }

    #[TestRule]
    public function tradingReadModelDependencies(): Rule
    {
        return PHPat::rule()
            ->classes($this->tradingReadModel())
            ->canOnlyDependOn()
            ->classes($this->tradingReadModel(), $this->thirdParty());
    }

    #[TestRule]
    public function tradingQueryAdapterDependencies(): Rule
    {
        return PHPat::rule()
            ->classes($this->tradingQueryAdapter())
            ->canOnlyDependOn()
            ->classes(
                $this->tradingQueryAdapter(),
                $this->tradingReadModel(),
                ...$this->outerLayerDependencies(self::TRADING),
            );
    }

    /**
     * @return iterable<string, Rule>
     */
    #[TestRule]
    public function uiDependencies(): iterable
    {
        foreach (self::NAMESPACES as $namespace) {
            yield $namespace => PHPat::rule()
                ->classes($this->ui($namespace))
                ->canOnlyDependOn()
                ->classes($this->ui($namespace), ...$this->outerLayerDependencies($namespace));
        }
    }

    #[TestRule]
    public function tradingLivewireDependencies(): Rule
    {
        return PHPat::rule()
            ->classes($this->tradingLivewire())
            ->canOnlyDependOn()
            ->classes(
                $this->tradingLivewire(),
                $this->tradingReadModel(),
                ...$this->outerLayerDependencies(self::TRADING),
            );
    }

    /**
     * @return iterable<string, Rule>
     */
    #[TestRule]
    public function serviceProviderDependencies(): iterable
    {
        foreach (self::NAMESPACES as $namespace) {
            yield $namespace => PHPat::rule()
                ->classes($this->serviceProvider($namespace))
                ->canOnlyDependOn()
                ->classes(
                    Selector::AllOf($this->ownNamespace($namespace), Selector::Not($this->tradingReadModel())),
                    $this->thirdParty(),
                    ...$this->allowedLibraries($namespace),
                );
        }
    }

    #[TestRule]
    public function integrationEventsAreReadonly(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('/^TradeManeuver\\\\[^\\\\]+\\\\Application\\\\IntegrationEvent(\\\\|$)/', true))
            ->shouldBeReadonly();
    }

    #[TestRule]
    public function concreteClassesAreFinal(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('TradeManeuver'))
            ->excluding(Selector::isAbstract(), Selector::isEnum(), Selector::isInterface(), Selector::isTrait())
            ->shouldBeFinal();
    }

    #[TestRule]
    public function interfacesHaveTheInterfaceSuffix(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::AllOf(Selector::inNamespace('TradeManeuver'), Selector::isInterface()))
            ->shouldBeNamed('/Interface$/', true);
    }

    /**
     * What Infrastructure, Ui, and the Trading layers that extend them may depend on, apart from themselves.
     *
     * @return list<SelectorInterface>
     */
    private function outerLayerDependencies(string $namespace): array
    {
        return [
            $this->application($namespace),
            $this->domain($namespace),
            ...$this->allowedLibraries($namespace),
            $this->thirdParty(),
        ];
    }

    private function ownNamespace(string $namespace): SelectorInterface
    {
        return Selector::inNamespace('TradeManeuver\\'.$namespace);
    }

    private function domain(string $namespace): SelectorInterface
    {
        return Selector::inNamespace('TradeManeuver\\'.$namespace.'\Domain');
    }

    private function application(string $namespace): SelectorInterface
    {
        return Selector::inNamespace('TradeManeuver\\'.$namespace.'\Application');
    }

    private function infrastructure(string $namespace): SelectorInterface
    {
        return Selector::AllOf(
            Selector::inNamespace('TradeManeuver\\'.$namespace.'\Infrastructure'),
            Selector::NoneOf($this->tradingReadModel(), $this->tradingQueryAdapter(), $this->serviceProvider($namespace)),
        );
    }

    private function ui(string $namespace): SelectorInterface
    {
        return Selector::AllOf(
            Selector::inNamespace('TradeManeuver\\'.$namespace.'\Ui'),
            Selector::Not($this->tradingLivewire()),
        );
    }

    private function serviceProvider(string $namespace): SelectorInterface
    {
        return Selector::classname(
            'TradeManeuver\\'.$namespace.'\Infrastructure\Providers\\'.$namespace.'ServiceProvider',
        );
    }

    /**
     * ADR-0004: read-only Eloquent read models in Infrastructure/{Aggregate}/ReadModel.
     */
    private function tradingReadModel(): SelectorInterface
    {
        return Selector::inNamespace('/^TradeManeuver\\\\Trading\\\\Infrastructure\\\\[^\\\\]+\\\\ReadModel(\\\\|$)/', true);
    }

    /**
     * Query adapters, {Aggregate}Query in Infrastructure/{Aggregate}.
     */
    private function tradingQueryAdapter(): SelectorInterface
    {
        return Selector::classname('/^TradeManeuver\\\\Trading\\\\Infrastructure\\\\[^\\\\]+\\\\[^\\\\]+Query$/', true);
    }

    private function tradingLivewire(): SelectorInterface
    {
        return Selector::inNamespace('TradeManeuver\Trading\Ui\Livewire');
    }

    private function moneyLibrary(): SelectorInterface
    {
        return Selector::AnyOf(Selector::inNamespace('Brick\Money'), Selector::inNamespace('Brick\Math'));
    }

    /**
     * @return list<SelectorInterface>
     */
    private function allowedLibraries(string $namespace): array
    {
        $libraries = [$this->moneyLibrary(), Selector::inNamespace('Psr\Clock')];

        if ($namespace === self::TRADING) {
            $libraries[] = Selector::inNamespace('Psr\Log');
        }

        return $libraries;
    }

    /**
     * Every class outside the project and the allowed libraries; PHP's own classes are ignored in phpstan.neon.dist.
     */
    private function thirdParty(): SelectorInterface
    {
        return Selector::NoneOf(
            Selector::inNamespace('TradeManeuver'),
            Selector::inNamespace('App'),
            Selector::inNamespace('Database'),
            Selector::inNamespace('Brick\Money'),
            Selector::inNamespace('Brick\Math'),
            Selector::inNamespace('Psr\Clock'),
            Selector::inNamespace('Psr\Log'),
        );
    }
}
