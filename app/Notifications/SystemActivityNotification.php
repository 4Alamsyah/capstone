<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SystemActivityNotification extends Notification
{
    public function __construct(
        public string $type,
        public string $title,
        public string $description,
        public ?string $url = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->url,
        ];
    }
}
