Feature: Trade signal evaluation: instrument checks
  After the expiry and direction checks, Trade Maneuver looks up the instrument at the broker. It declines a trade
  signal for an instrument that the broker does not know or that is unavailable with InstrumentUnavailable.

  Background:
    Given the analytics application "demo-analytics" publishes as the RabbitMQ user "demo-analytics"
    And the investment configuration "demo-investment" authorizes the analytics application "demo-analytics"
    And the time is "2026-11-09T10:00:00Z"

  Scenario: A trade signal for an unavailable instrument is declined with InstrumentUnavailable
    Given the broker lists the instrument "DEMO_H" as unavailable, quoted in "EUR"
    When "demo-analytics" delivers the trade signal "purchase" with "instrument.symbol" set to "DEMO_H"
    And Trade Maneuver evaluates the trade signal "purchase"
    Then the trade signal "purchase" ends with one SignalDeclined outcome with the reason "InstrumentUnavailable"

  Scenario: A trade signal for an instrument the broker does not know is declined with InstrumentUnavailable
    Given the broker lists the instrument "DEMO_A" as available, quoted in "EUR"
    When "demo-analytics" delivers the trade signal "purchase" with "instrument.symbol" set to "DEMO_X"
    And Trade Maneuver evaluates the trade signal "purchase"
    Then the trade signal "purchase" ends with one SignalDeclined outcome with the reason "InstrumentUnavailable"
