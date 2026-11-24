<?php

namespace App\Http\Controllers;

use App\Events\PersonnelMessageSent;
use App\Models\PersonnelConversation;
use App\Models\PersonnelMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ChatController
{
    private function isAdministrator(User $user): bool
    {
        return in_array($user->role, ['admin', 'superadmin'], true);
    }

    private function messagePayload(PersonnelConversation $conversation, User $viewer): array
    {
        $messages = $conversation->messages()
            ->with('sender:id,name,role')
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->reverse()
            ->values();

        if ($this->isAdministrator($viewer)) {
            $conversation->forceFill(['admin_last_read_at' => now()])->save();
        } else {
            $conversation->forceFill(['personnel_last_read_at' => now()])->save();
        }

        return $messages->map(fn (PersonnelMessage $message) => [
            'id' => $message->id,
            'body' => $message->body,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->name ?: 'UCH colleague',
            'sender_role' => $message->sender?->role,
            'created_at' => $message->created_at?->toIso8601String(),
        ])->all();
    }

    public function index(Request $request)
    {
        /** @var User $viewer */
        $viewer = $request->user();
        if (!$this->isAdministrator($viewer)) {
            $conversation = PersonnelConversation::firstOrCreate(['personnel_id' => $viewer->id]);
            return response()->json([
                'is_admin' => false,
                'personnel' => null,
                'conversations' => [],
                'messages' => $this->messagePayload($conversation, $viewer),
            ]);
        }

        $query = User::query()->where('role', 'personnel')->with(['chatConversation.messages' => fn ($messages) => $messages->latest()->limit(1)]);
        $term = trim((string) $request->query('search', ''));
        if ($term !== '') {
            $query->where(fn ($users) => $users->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%"));
        } else {
            $query->whereHas('chatConversation');
        }
        $people = $query->orderBy('name')->limit(100)->get(['id', 'name', 'email', 'role']);
        $selectedId = (int) $request->query('personnel_id', 0);
        $selected = $selectedId ? $people->firstWhere('id', $selectedId) : null;
        $messages = [];
        if ($selected) {
            $conversation = PersonnelConversation::firstOrCreate(['personnel_id' => $selected->id]);
            $messages = $this->messagePayload($conversation, $viewer);
        }

        return response()->json([
            'is_admin' => true,
            'personnel' => $selected ? $selected->only('id', 'name', 'email') : null,
            'conversations' => $people->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name ?: 'UCH personnel',
                'email' => $person->email,
                'last_message' => $person->chatConversation?->messages->first()?->body,
            ])->all(),
            'messages' => $messages,
        ]);
    }

    public function send(Request $request)
    {
        /** @var User $sender */
        $sender = $request->user();
        $values = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'personnel_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $body = trim($values['body']);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Write a message before sending.']);
        }

        if ($this->isAdministrator($sender)) {
            if (empty($values['personnel_id'])) {
                throw ValidationException::withMessages(['personnel_id' => 'Choose a personnel colleague first.']);
            }
            $person = User::query()->where('role', 'personnel')->findOrFail($values['personnel_id']);
            $conversation = PersonnelConversation::firstOrCreate(['personnel_id' => $person->id]);
        } else {
            $conversation = PersonnelConversation::firstOrCreate(['personnel_id' => $sender->id]);
        }

        $message = $conversation->messages()->create(['sender_id' => $sender->id, 'body' => $body]);
        $message->load('sender:id,name,role');
        try {
            event(new PersonnelMessageSent($message));
        } catch (\Throwable $exception) {
            Log::warning('Personnel chat message was saved but could not be broadcast.', [
                'message_id' => $message->id,
                'exception' => $exception->getMessage(),
            ]);
        }
        return response()->json([
            'message' => [
                'id' => $message->id,
                'body' => $message->body,
                'sender_id' => $message->sender_id,
                'sender_name' => $message->sender?->name ?: 'UCH colleague',
                'sender_role' => $message->sender?->role,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
