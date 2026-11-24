<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\TestCase;

final class PanelLayoutTest extends TestCase
{
    use OperatorAccounts;
    use RefreshDatabase;

    private const string SIMULATED_BROKER_LABEL = 'Simulated broker: no real orders';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEnrolledOperator();
    }

    public function test_the_login_pages_and_the_panel_layout_show_the_simulated_broker_label(): void
    {
        $this->get(route('login'))->assertOk()->assertSeeText(self::SIMULATED_BROKER_LABEL);

        $this->submitPassword();
        $this->get(route('login.totp'))->assertOk()->assertSeeText(self::SIMULATED_BROKER_LABEL);

        $this->actingAs($this->operatorModel())
            ->get(route('panel.start'))
            ->assertOk()
            ->assertSeeText(self::SIMULATED_BROKER_LABEL);
    }

    public function test_the_panel_layout_has_a_theme_switch_and_renders_the_light_theme_by_default(): void
    {
        $page = $this->panelPage();

        $this->assertSame('light', $page->documentElement?->getAttribute('data-theme'));
        $themeSwitch = $page->querySelector('aside button[data-theme-toggle]');
        $this->assertNotNull($themeSwitch);
        $this->assertSame('button', $themeSwitch->getAttribute('type'));
        $this->assertSame('Switch theme', trim((string) $themeSwitch->textContent));
    }

    public function test_the_panel_navigation_contains_the_start_entry_and_the_logout_action(): void
    {
        $page = $this->panelPage();

        $entries = $page->querySelectorAll('aside nav[aria-label="Panel"] a');
        $this->assertGreaterThan(0, $entries->length);
        $startEntry = $entries->item(0);
        $this->assertNotNull($startEntry);
        $this->assertSame('Start', trim((string) $startEntry->textContent));
        $this->assertSame(route('panel.start'), $startEntry->getAttribute('href'));
        $this->assertSame('page', $startEntry->getAttribute('aria-current'));

        $logoutForm = $page->querySelector('aside form[method="post"][action="'.route('logout').'"]');
        $this->assertNotNull($logoutForm);
        $this->assertNotNull($logoutForm->querySelector('input[type="hidden"][name="_token"]'));
        $logoutButton = $logoutForm->querySelector('button[type="submit"]');
        $this->assertNotNull($logoutButton);
        $this->assertSame('Log out', trim((string) $logoutButton->textContent));
    }

    private function panelPage(): HTMLDocument
    {
        $html = (string) $this->actingAs($this->operatorModel())->get(route('panel.start'))->assertOk()->getContent();

        return HTMLDocument::createFromString($html, LIBXML_NOERROR);
    }
}
