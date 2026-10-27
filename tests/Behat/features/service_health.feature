Feature: Execution service availability
  Scenario: The service responds to a health check
    Given the execution service is running
    When I check the service health
    Then the service reports that it is healthy
