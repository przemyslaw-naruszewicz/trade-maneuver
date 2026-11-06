<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\InvestmentConfiguration;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\Exception\InvestmentConfigurationDoesNotExistException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;

interface InvestmentConfigurationRepositoryInterface
{
    public function nextIdentity(): InvestmentConfigurationId;

    public function add(InvestmentConfiguration $investmentConfiguration): void;

    /**
     * @throws InvestmentConfigurationDoesNotExistException
     */
    public function get(InvestmentConfigurationId $id): InvestmentConfiguration;
}
