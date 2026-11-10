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
