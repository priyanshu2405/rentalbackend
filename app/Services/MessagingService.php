<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SupportCase;
use App\Models\User;

class MessagingService
{
    public function __construct(private NotificationService $notifications) {}

    public function bookingConversation(Booking $booking): Conversation
    {
        $conversation = Conversation::firstOrCreate(
            ['type' => Conversation::TYPE_BOOKING, 'booking_id' => $booking->id],
            ['item_id' => $booking->item_id]
        );
        $conversation->participants()->syncWithoutDetaching([$booking->renter_id, $booking->owner_id]);

        return $conversation;
    }

    public function directConversation(User $a, User $b, ?int $itemId = null): Conversation
    {
        $existing = Conversation::where('type', Conversation::TYPE_DIRECT)
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId), fn ($q) => $q->whereNull('item_id'))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $a->id))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $b->id))
            ->first();

        if ($existing) {
            return $existing;
        }

        $conversation = Conversation::create(['type' => Conversation::TYPE_DIRECT, 'item_id' => $itemId]);
        $conversation->participants()->attach([$a->id, $b->id]);

        return $conversation;
    }

    public function supportConversation(SupportCase $case): Conversation
    {
        $conversation = Conversation::firstOrCreate(
            ['type' => Conversation::TYPE_SUPPORT, 'support_case_id' => $case->id],
            ['booking_id' => $case->booking_id]
        );
        $conversation->participants()->syncWithoutDetaching([$case->user_id]);

        return $conversation;
    }

    public function post(Conversation $conversation, ?User $sender, ?string $body, ?string $attachmentPath = null): Message
    {
        $message = $conversation->messages()->create([
            'sender_id' => $sender?->id,
            'body' => $body,
            'attachment_path' => $attachmentPath,
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();

        if ($sender) {
            $conversation->participants()->updateExistingPivot($sender->id, ['last_read_at' => $message->created_at]);
        }

        foreach ($conversation->participants as $participant) {
            if ($sender && $participant->id === $sender->id) {
                continue;
            }
            $this->notifications->send(
                $participant,
                'new_message',
                'messages',
                $sender ? "New message from {$sender->name}" : 'New message from Neighbours Rental Support',
                $body ? mb_strimwidth($body, 0, 120, '…') : 'Sent an attachment',
                ['conversation_id' => $conversation->id]
            );
        }

        return $message;
    }
}
