<?php

declare(strict_types=1);

namespace Tests\Unit\Endpoint\Web;

use App\Http\Controllers\Web\ValoCronController;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCaseWithDatabase;

#[CoversClass(ValoCronController::class)]
class ValoCronEndpointTest extends TestCaseWithDatabase
{
    public function test_cron_url_runs_with_the_right_token(): void
    {
        // Arrange
        config(['valo.cron_token' => 'secret-token-for-tests']);

        // Act
        $response = $this->get('/valo/cron/secret-token-for-tests');

        // Assert
        $response->assertOk();
        $this->assertSame('ok', $response->getContent());
    }

    public function test_cron_url_is_hidden_with_a_wrong_or_missing_token(): void
    {
        // Arrange
        config(['valo.cron_token' => 'secret-token-for-tests']);

        // Act + Assert
        $this->get('/valo/cron/wrong-token')->assertNotFound();
        config(['valo.cron_token' => '']);
        $this->get('/valo/cron/anything')->assertNotFound();
    }
}
