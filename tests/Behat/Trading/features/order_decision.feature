Feature: Operator decision on an order awaiting approval
  The operator approves an order awaiting approval, optionally at a new limit price, or refuses it. A new limit price
  recalculates the quantity from the order amount of the order's snapshot: the largest whole number of units whose cost
  does not exceed it. A refusal declines the trade signal with RefusedByOperator; its note stays in the audit trail.

  Background:
    Given the analytics application "demo-analytics" publishes as the RabbitMQ user "demo-analytics"
    And the time is "2026-11-09T10:00:00Z"
    And the broker lists the instrument "DEMO_A" as available, quoted in "EUR"
    And the investment configuration "demo-investment" with the order amount "1000.00" EUR and the limit price tolerance "2.00" % authorizes the analytics application "demo-analytics"
    And "demo-analytics" delivers the trade signal "purchase" with "referencePrice.amount" set to "10.00"
    And Trade Maneuver evaluates the trade signal "purchase"
    And the time is "2026-11-09T11:00:00Z"

  Scenario: Approving without a change keeps the original limit price and quantity
    When the operator approves the order of the trade signal "purchase" without a new limit price
    Then the order of the trade signal "purchase" is approved with the limit price "10.20" EUR and the quantity 98

  Scenario: Approving with a new limit price recalculates the quantity from the order amount
    When the operator approves the order of the trade signal "purchase" with the new limit price "10.50" EUR
    Then the order of the trade signal "purchase" is approved with the limit price "10.50" EUR and the quantity 95
    And the approval of the order of the trade signal "purchase" is audited with the limit price changed from "10.20" to "10.50" EUR and the quantity from 98 to 95
