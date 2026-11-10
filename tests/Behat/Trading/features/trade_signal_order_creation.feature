Feature: Order creation awaiting approval
  A trade signal that passes evaluation leads to exactly one order awaiting approval. Its limit price is the reference
  price plus the limit price tolerance, rounded down to 0.01 EUR, and its quantity is the largest whole number of units
  whose cost at the limit price does not exceed the order amount. A trade signal whose order amount cannot buy one unit
  is declined with OrderAmountTooSmall.

  Background:
    Given the analytics application "demo-analytics" publishes as the RabbitMQ user "demo-analytics"
    And the time is "2026-11-09T10:00:00Z"
    And the broker lists the instrument "DEMO_A" as available, quoted in "EUR"

  Scenario: A valid long trade signal creates one order awaiting approval
    Given the investment configuration "demo-investment" with the order amount "1000.00" EUR and the limit price tolerance "2.00" % authorizes the analytics application "demo-analytics"
    When "demo-analytics" delivers the trade signal "purchase" with "referencePrice.amount" set to "10.00"
    And Trade Maneuver evaluates the trade signal "purchase"
    Then the trade signal "purchase" leads to one order awaiting approval with the limit price "10.20" EUR and the quantity 98
    And the creation of the order of the trade signal "purchase" is audited with the order amount "1000.00" EUR, the limit price tolerance "2.00" %, and the brokerage account "DEMO-ACCOUNT-1"

  Scenario: A trade signal whose quantity rounds down to zero is declined with OrderAmountTooSmall
    Given the investment configuration "demo-investment" with the order amount "1000.00" EUR and the limit price tolerance "0" % authorizes the analytics application "demo-analytics"
    When "demo-analytics" delivers the trade signal "purchase" with "referencePrice.amount" set to "1000.01"
    And Trade Maneuver evaluates the trade signal "purchase"
    Then the trade signal "purchase" ends with one SignalDeclined outcome with the reason "OrderAmountTooSmall"
    And no order is created for the trade signal "purchase"
