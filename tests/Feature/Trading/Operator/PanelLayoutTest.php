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

    private function panelPage(): HTMLDocument
    {
        $html = (string) $this->actingAs($this->operatorModel())->get(route('panel.start'))->assertOk()->getContent();

        return HTMLDocument::createFromString($html, LIBXML_NOERROR);
    }
}
