Feature: Trade signal intake
  Analytics applications deliver tm.trade-signal.v1 messages to Trade Maneuver. Each delivery is accepted durably
  once. A message that violates the contract ends with the signal outcome SignalRejected, which lists the violations.

  Background:
    Given the analytics application "demo-analytics" publishes as the RabbitMQ user "demo-analytics"
    And the investment configuration "demo-investment" authorizes the analytics application "demo-analytics"

  Scenario: A trade signal without a required field is rejected
    When "demo-analytics" delivers the trade signal "purchase" without "instrument.symbol"
    Then the trade signal "purchase" ends with one SignalRejected outcome listing a violation of "instrument.symbol"

  Scenario: A trade signal with an invalid value is rejected
    When "demo-analytics" delivers the trade signal "purchase" with "confidence" set to "1.5"
    Then the trade signal "purchase" ends with one SignalRejected outcome listing a violation of "confidence"
