<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class ValoMemberWeeklyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;

    /**
     * @var array<int, array{project: string, hours: string}>
     */
    public array $rows;

    public string $total;

    public string $weekLabel;

    /**
     * @param  array<int, array{project: string, hours: string}>  $rows
     */
    public function __construct(User $user, array $rows, string $total, string $weekLabel)
    {
        $this->user = $user;
        $this->rows = $rows;
        $this->total = $total;
        $this->weekLabel = $weekLabel;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->markdown('emails.valo-member-weekly-report', [
            'timeUrl' => URL::route('time'),
        ])
            ->subject(__('Your week in VALO\'s Time'));
    }
}
