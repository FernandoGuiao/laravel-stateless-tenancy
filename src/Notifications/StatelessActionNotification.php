<?php

namespace FernandoGuiao\StatelessTenancy\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StatelessActionNotification extends Notification
{
    use Queueable;

    protected string $subjectText;
    protected array $messageLines;
    protected string $buttonText;
    protected string $actionUrl;

    public function __construct(string $subjectText, array $messageLines, string $buttonText, string $actionUrl)
    {
        $this->subjectText = $subjectText;
        $this->messageLines = $messageLines;
        $this->buttonText = $buttonText;
        $this->actionUrl = $actionUrl;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $mailMessage = (new MailMessage)
            ->subject($this->subjectText);

        foreach ($this->messageLines as $line) {
            $mailMessage->line($line);
        }

        $mailMessage->action($this->buttonText, $this->actionUrl);

        return $mailMessage;
    }
}
