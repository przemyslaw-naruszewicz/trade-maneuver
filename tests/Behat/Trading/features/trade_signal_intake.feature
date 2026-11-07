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

  Scenario: A trade signal with an invalid structure is rejected
    When "demo-analytics" delivers the trade signal "purchase" with "instrument" set to "DEMO_A"
    Then the trade signal "purchase" ends with one SignalRejected outcome listing a violation of "instrument"

  Scenario: A trade signal addressing an unknown investment configuration is rejected
    When "demo-analytics" delivers the trade signal "purchase" with "investmentConfigurationRef" set to "demo-unknown"
    Then the trade signal "purchase" ends with one SignalRejected outcome listing a violation of "investmentConfigurationRef"

  Scenario: A trade signal from an analytics application not authorized for the investment configuration is rejected
    Given the analytics application "demo-research" publishes as the RabbitMQ user "demo-research"
    When "demo-research" delivers the trade signal "purchase"
    Then the trade signal "purchase" ends with one SignalRejected outcome listing a violation of "source"

  Scenario: A valid trade signal is stored and awaits evaluation
    When "demo-analytics" delivers the trade signal "purchase"
    Then the trade signal "purchase" is stored and awaits evaluation
    And no signal outcome is recorded

  Scenario: A trade signal on the fictional exchange DEMO passes contract validation
    When "demo-analytics" delivers the trade signal "purchase" with "instrument.mic" set to "DEMO"
    Then the trade signal "purchase" is stored and awaits evaluation
    And no signal outcome is recorded

  Scenario: A trade signal whose exchange code does not have the MIC format is rejected
    When "demo-analytics" delivers the trade signal "purchase" with "instrument.mic" set to "demo"
    Then the trade signal "purchase" ends with one SignalRejected outcome listing a violation of "instrument.mic"
