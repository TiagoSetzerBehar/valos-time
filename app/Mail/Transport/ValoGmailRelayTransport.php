<?php

declare(strict_types=1);

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

/**
 * VALO's Time: sends emails through a Google Apps Script web app that runs in the VALO Gmail account.
 * The mail is sent by Gmail itself, so it is authenticated and lands in the inbox, and it travels over
 * HTTPS, which the free Render plan does not block (unlike the SMTP ports).
 */
class ValoGmailRelayTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $url,
        private readonly string $secret,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if ($this->url === '' || $this->secret === '') {
            throw new RuntimeException('The Gmail relay is not configured (VALO_MAIL_RELAY_URL / VALO_MAIL_RELAY_SECRET).');
        }

        $original = $message->getOriginalMessage();
        if (! $original instanceof Message) {
            throw new RuntimeException('Only MIME messages can be sent through the Gmail relay.');
        }
        $email = MessageConverter::toEmail($original);
        $addresses = fn (array $list): string => implode(',', array_map(fn (Address $address): string => $address->getAddress(), $list));
        $replyTo = $email->getReplyTo();

        $response = Http::timeout(30)
            ->acceptJson()
            ->post($this->url, [
                'secret' => $this->secret,
                'to' => $addresses($email->getTo()),
                'cc' => $addresses($email->getCc()),
                'bcc' => $addresses($email->getBcc()),
                'reply_to' => $replyTo !== [] ? $replyTo[0]->getAddress() : '',
                'name' => $email->getFrom() !== [] ? $email->getFrom()[0]->getName() : '',
                'subject' => (string) $email->getSubject(),
                'html' => (string) $email->getHtmlBody(),
                'text' => (string) $email->getTextBody(),
            ]);

        if (! $response->successful() || $response->json('ok') !== true) {
            throw new RuntimeException('The Gmail relay did not send the email: '.mb_substr($response->body(), 0, 300));
        }
    }

    public function __toString(): string
    {
        return 'valo-gmail';
    }
}
