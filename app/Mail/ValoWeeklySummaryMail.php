<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class ValoWeeklySummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    /**
     * @var array<int, array{name: string, hours: string}>
     */
    public array $belowExpected;

    public int $expectedHours;

    public string $weekLabel;

    public int $memberCount;

    /**
     * @param  array<int, array{name: string, hours: string}>  $belowExpected
     */
    public function __construct(User $user, array $belowExpected, int $expectedHours, string $weekLabel, int $memberCount)
    {
        $this->user = $user;
        $this->belowExpected = $belowExpected;
        $this->expectedHours = $expectedHours;
        $this->weekLabel = $weekLabel;
        $this->memberCount = $memberCount;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->markdown('emails.valo-weekly-summary', [
            'reportUrl' => URL::route('reporting.weekly'),
        ])
            ->subject(__('Weekly hours summary'));
    }
}
