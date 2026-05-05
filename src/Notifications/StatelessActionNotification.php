<?php

namespace FernandoGuiao\StatelessTenancy\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Class StatelessActionNotification
 *
 * Base notification for stateless actions such as password resets
 * or email verifications sent via the mail channel.
 */
class StatelessActionNotification extends Notification
{
    use Queueable;

    /**
     * @var string
     */
    protected string $subjectText;

    /**
     * @var array<int, string>
     */
    protected array $messageLines;

    /**
     * @var string
     */
    protected string $buttonText;

    /**
     * @var string
     */
    protected string $actionUrl;

    /**
     * Create a new notification instance.
     *
     * @param string $subjectText The subject line of the email
     * @param array<int, string> $messageLines An array of strings where each string is a paragraph line
     * @param string $buttonText The text to display on the action button
     * @param string $actionUrl The full URL that the action button will point to
     */
    public function __construct(string $subjectText, array $messageLines, string $buttonText, string $actionUrl)
    {
        $this->subjectText = $subjectText;
        $this->messageLines = $messageLines;
        $this->buttonText = $buttonText;
        $this->actionUrl = $actionUrl;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable The entity being notified
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable The entity being notified
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail(mixed $notifiable): MailMessage
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
