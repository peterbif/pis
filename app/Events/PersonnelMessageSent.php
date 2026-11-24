<?php

namespace App\Events;

use App\Models\PersonnelMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PersonnelMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PersonnelMessage $message)
    {
        $this->message->loadMissing(['sender:id,name,role', 'conversation:id,personnel_id']);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('personnel.'.$this->message->conversation->personnel_id)];
    }

    public function broadcastAs(): string
    {
        return 'personnel.message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender?->name ?: 'UCH colleague',
            'sender_role' => $this->message->sender?->role,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
