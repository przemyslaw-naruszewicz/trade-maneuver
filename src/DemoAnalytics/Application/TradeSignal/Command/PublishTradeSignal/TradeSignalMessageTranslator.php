<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Application\TradeSignal\Command\PublishTradeSignal;

use TradeManeuver\DemoAnalytics\Domain\TradeSignal\DemoTradeSignal;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\DemoInstrument;

/**
 * Translates a demo trade signal into Trade Maneuver's published language, `tm.trade-signal.v1`.
 */
final readonly class TradeSignalMessageTranslator
{
    public const string TYPE = 'tm.trade-signal.v1';

    /**
     * @param  string  $source  the identifier agreed with Trade Maneuver
     */
    public function __construct(private string $source) {}

    public function toMessageBody(DemoTradeSignal $tradeSignal): string
    {
        return json_encode([
            'id' => $tradeSignal->id,
            'source' => $this->source,
            'type' => self::TYPE,
            'time' => $tradeSignal->issuedAt->toString(),
            'investmentConfigurationRef' => $tradeSignal->investmentConfigurationRef->toString(),
            'instrument' => ['symbol' => $tradeSignal->instrument->symbol(), 'mic' => DemoInstrument::EXCHANGE],
            'direction' => $tradeSignal->direction->value,
            'confidence' => $tradeSignal->confidence->toString(),
            'horizon' => ['sessions' => $tradeSignal->horizon->sessions()],
            'expectedMove' => ['min' => $tradeSignal->expectedMove->min(), 'max' => $tradeSignal->expectedMove->max()],
            'referencePrice' => [
                'amount' => $tradeSignal->referencePrice->amount(),
                'currency' => $tradeSignal->referencePrice->currencyCode(),
                'asOf' => $tradeSignal->referencePrice->asOf()->toString(),
            ],
            'validUntil' => $tradeSignal->validUntil->toString(),
        ], JSON_THROW_ON_ERROR);
    }
}
