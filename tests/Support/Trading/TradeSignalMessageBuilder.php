<?php

declare(strict_types=1);

namespace Tests\Support\Trading;

/**
 * Builds `tm.trade-signal.v1` message bodies on the fictional instrument DEMO_A at the fictional exchange DEMO.
 * Fields are addressed by path, such as `instrument.mic`.
 */
final class TradeSignalMessageBuilder
{
    /** @var array<string, mixed> */
    private array $message;

    private function __construct(string $id, string $source)
    {
        $this->message = [
            'id' => $id,
            'source' => $source,
            'type' => 'tm.trade-signal.v1',
            'time' => '2026-11-07T09:00:00Z',
            'investmentConfigurationRef' => 'demo-investment',
            'instrument' => ['symbol' => 'DEMO_A', 'mic' => 'DEMO'],
            'direction' => 'long',
            'confidence' => '0.85',
            'horizon' => ['sessions' => 5],
            'expectedMove' => ['min' => '1.5', 'max' => '4.0'],
            'referencePrice' => ['amount' => '12.50', 'currency' => 'EUR', 'asOf' => '2026-11-07T08:59:00Z'],
            'validUntil' => '2026-11-10T16:00:00Z',
            'extensions' => ['alertLevel' => 'amber'],
        ];
    }

    public static function aTradeSignal(string $id, string $source = 'demo-analytics'): self
    {
        return new self($id, $source);
    }

    /**
     * A stable UUID for a business alias, so that scenarios and tests can name trade signals.
     */
    public static function idFor(string $alias): string
    {
        $hash = md5($alias);

        return sprintf('%s-%s-7%s-8%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 13, 3), substr($hash, 17, 3), substr($hash, 20, 12));
    }

    public function with(string $path, mixed $value): self
    {
        $keys = explode('.', $path);
        $last = array_pop($keys);
        $node = &$this->message;
        foreach ($keys as $key) {
            if (! isset($node[$key]) || ! is_array($node[$key])) {
                $node[$key] = [];
            }
            $node = &$node[$key];
        }
        $node[$last] = $value;
        unset($node);

        return $this;
    }

    public function without(string $path): self
    {
        $keys = explode('.', $path);
        $last = array_pop($keys);
        $node = &$this->message;
        foreach ($keys as $key) {
            if (! isset($node[$key]) || ! is_array($node[$key])) {
                return $this;
            }
            $node = &$node[$key];
        }
        unset($node[$last]);
        unset($node);

        return $this;
    }

    public function body(): string
    {
        return json_encode($this->message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
