Feature: Trade signal evaluation: expiry and direction
  After intake, Trade Maneuver evaluates each accepted trade signal in its own transaction, through the PostgreSQL job
  queue. Before any check that needs the broker, it declines an expired trade signal with SignalExpired and then a short
  one with UnsupportedDirection.

  Background:
    Given the analytics application "demo-analytics" publishes as the RabbitMQ user "demo-analytics"
    And the investment configuration "demo-investment" authorizes the analytics application "demo-analytics"

  Scenario: A short trade signal is declined with UnsupportedDirection
    Given the time is "2026-11-09T10:00:00Z"
    When "demo-analytics" delivers the trade signal "sale" with "direction" set to "short"
    And Trade Maneuver evaluates the trade signal "sale"
    Then the trade signal "sale" ends with one SignalDeclined outcome with the reason "UnsupportedDirection"

  Scenario: A trade signal whose validity has ended is declined with SignalExpired
    Given the time is "2026-11-09T10:00:00Z"
    When "demo-analytics" delivers the trade signal "purchase" with "validUntil" set to "2026-11-09T09:30:00Z"
    And Trade Maneuver evaluates the trade signal "purchase"
    Then the trade signal "purchase" ends with one SignalDeclined outcome with the reason "SignalExpired"

  Scenario: An expired short trade signal is declined with SignalExpired, the first check
    Given the time is "2026-11-09T10:00:00Z"
    When "demo-analytics" delivers the trade signal "sale" with "validUntil" set to "2026-11-09T09:30:00Z" and "direction" set to "short"
    And Trade Maneuver evaluates the trade signal "sale"
    Then the trade signal "sale" ends with one SignalDeclined outcome with the reason "SignalExpired"

  Scenario: A long trade signal inside its validity has no signal outcome after these checks
    Given the time is "2026-11-09T10:00:00Z"
    When "demo-analytics" delivers the trade signal "purchase" with "validUntil" set to "2026-11-09T10:00:00.000001Z"
    And Trade Maneuver evaluates the trade signal "purchase"
    Then the trade signal "purchase" has no signal outcome and awaits the instrument checks
