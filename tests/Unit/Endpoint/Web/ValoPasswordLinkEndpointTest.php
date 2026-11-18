<?php

declare(strict_types=1);

namespace Tests\Unit\Endpoint\Web;

use App\Enums\Role;
use App\Http\Controllers\Web\ValoPasswordLinkController;
use App\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ValoPasswordLinkController::class)]
#[CoversClass(RedirectIfAuthenticated::class)]
class ValoPasswordLinkEndpointTest extends EndpointTestAbstract
{
    public function test_a_logged_in_member_who_forgot_the_password_gets_the_reset_email(): void
    {
        // Arrange
        Notification::fake();
        $data = $this->createUserWithRole(Role::Employee);

        // Act
        $response = $this->actingAs($data->user)->postJson(route('valo.password.link'));
        $again = $this->actingAs($data->user)->postJson(route('valo.password.link'));

        // Assert
        $response->assertOk()->assertJsonPath('sent', true)->assertJsonPath('email', $data->user->email);
        $again->assertStatus(429);
        Notification::assertSentToTimes($data->user, ResetPassword::class, 1);
    }

    public function test_guests_can_not_ask_for_the_link_of_someone_else(): void
    {
        // Act
        $response = $this->postJson(route('valo.password.link'));

        // Assert
        $response->assertUnauthorized();
    }

    public function test_the_link_of_the_email_opens_even_if_the_member_is_still_logged_in(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Employee);
        $token = Password::broker()->createToken($data->user);

        // Act
        $response = $this->actingAs($data->user)->get(route('password.reset', ['token' => $token, 'email' => $data->user->email]));

        // Assert
        $response->assertOk();
        $this->assertGuest();
    }

    public function test_other_guest_pages_still_send_logged_in_members_home(): void
    {
        // Arrange
        $data = $this->createUserWithRole(Role::Employee);

        // Act
        $response = $this->actingAs($data->user)->get(route('login'));

        // Assert
        $response->assertRedirect();
    }
}
