Feature: Trade Maneuver availability
  Scenario: The service responds to a health check
    Given Trade Maneuver is running
    When I check the service health
    Then the service reports that it is healthy
