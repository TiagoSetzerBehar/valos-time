<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\Transport\ValoGmailRelayTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ValoGmailRelayTransport::class)]
class ValoGmailRelayTransportTest extends TestCase
{
    public function test_emails_are_posted_to_the_gmail_relay(): void
    {
        // Arrange
        config([
            'mail.default' => 'valo-gmail',
            'valo.mail_relay_url' => 'https://script.google.com/macros/s/test/exec',
            'valo.mail_relay_secret' => 'relay-secret',
        ]);
        Http::fake(['script.google.com/*' => Http::response(['ok' => true])]);

        // Act
        Mail::html('<p>Hola</p>', function ($message): void {
            $message->to('member@example.com')->subject('Invitación');
        });

        // Assert
        Http::assertSent(fn (Request $request): bool => $request['secret'] === 'relay-secret'
            && $request['to'] === 'member@example.com'
            && $request['subject'] === 'Invitación'
            && str_contains((string) $request['html'], 'Hola'));
    }

    public function test_a_relay_error_is_reported(): void
    {
        // Arrange
        config([
            'mail.default' => 'valo-gmail',
            'valo.mail_relay_url' => 'https://script.google.com/macros/s/test/exec',
            'valo.mail_relay_secret' => 'relay-secret',
        ]);
        Http::fake(['script.google.com/*' => Http::response(['ok' => false, 'error' => 'quota'])]);

        // Assert
        $this->expectException(\Throwable::class);

        // Act
        Mail::html('<p>Hola</p>', function ($message): void {
            $message->to('member@example.com')->subject('Invitación');
        });
    }
}
