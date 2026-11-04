<?php

declare(strict_types=1);

namespace Tests\Behat;

use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

final class ApplicationContext implements Context
{
    private Application $application;

    private Kernel $kernel;

    private Response $response;

    #[BeforeScenario]
    public function bootApplication(): void
    {
        $this->application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $this->kernel = $this->application->make(Kernel::class);
        $this->kernel->bootstrap();
    }

    #[Given('the execution service is running')]
    public function theExecutionServiceIsRunning(): void
    {
        Assert::assertTrue($this->application->environment('testing'));
    }

    #[When('I check the service health')]
    public function checkServiceHealth(): void
    {
        $request = Request::create('/up');
        $this->response = $this->kernel->handle($request);
        $this->kernel->terminate($request, $this->response);
    }

    #[Then('the service reports that it is healthy')]
    public function theServiceReportsThatItIsHealthy(): void
    {
        Assert::assertSame(Response::HTTP_OK, $this->response->getStatusCode());
    }

    #[AfterScenario]
    public function shutdownApplication(): void
    {
        $this->application->flush();
        Facade::clearResolvedInstances();
        Application::setInstance(null);
    }
}
