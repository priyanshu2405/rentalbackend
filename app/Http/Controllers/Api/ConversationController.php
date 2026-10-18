<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Item;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Messages tab + booking chat + support chat.
 * Real-time: poll GET /conversations/{id}/messages?after_id=… every few seconds,
 * or add Laravel Reverb/Pusher broadcasting later.
 */
class ConversationController extends Controller
{
    public function __construct(private MessagingService $messaging) {}

    /** ?filter=all|rentals|support|unread  &search=… */
    public function index(Request $request)
    {
        $data = $request->validate([
            'filter' => ['nullable', 'in:all,rentals,support,unread'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $user = $request->user();

        $conversations = Conversation::query()
            ->whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
            ->with(['participants', 'latestMessage.sender', 'item.photos', 'booking', 'supportCase'])
            ->when(($data['filter'] ?? null) === 'rentals', fn ($q) => $q->whereIn('type', [Conversation::TYPE_BOOKING, Conversation::TYPE_DIRECT]))
            ->when(($data['filter'] ?? null) === 'support', fn ($q) => $q->where('type', Conversation::TYPE_SUPPORT))
            ->when($data['search'] ?? null, function ($q, $s) use ($user) {
                $term = "%{$s}%";
                $q->where(fn ($w) => $w
                    ->whereHas('participants', fn ($p) => $p->where('users.id', '!=', $user->id)->where('name', 'like', $term))
                    ->orWhereHas('item', fn ($i) => $i->where('title', 'like', $term))
                    ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', $term)));
            })
            ->orderByDesc('last_message_at')
            ->get();

        if (($data['filter'] ?? null) === 'unread') {
            $conversations = $conversations->filter(fn ($c) => $c->unreadCountFor($user) > 0)->values();
        }

        return ConversationResource::collection($conversations)->additional([
            'meta' => ['unread_total' => $conversations->sum(fn ($c) => $c->unreadCountFor($user))],
        ]);
    }

    /** "Message owner" / "Contact Maya": start (or reopen) a chat with a user, optionally about an item. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required_without:item_id', 'nullable', 'integer', 'exists:users,id'],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        $item = isset($data['item_id']) ? Item::find($data['item_id']) : null;
        $other = User::findOrFail($data['user_id'] ?? $item->user_id);

        if ($other->id === $request->user()->id) {
            throw ValidationException::withMessages(['user_id' => "You can't message yourself."]);
        }

        $conversation = $this->messaging->directConversation($request->user(), $other, $item?->id);
        if (! empty($data['body'])) {
            $this->messaging->post($conversation, $request->user(), $data['body']);
        }

        return response()->json(['data' => new ConversationResource($conversation->load(['participants', 'latestMessage.sender', 'item.photos', 'booking', 'supportCase']))], 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->canBeAccessedBy($request->user()), 403);

        return response()->json(['data' => new ConversationResource($conversation->load(['participants', 'latestMessage.sender', 'item.photos', 'booking', 'supportCase']))]);
    }

    /** ?before_id= for older pages, ?after_id= for polling new ones. Returned oldest → newest. */
    public function messages(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->canBeAccessedBy($request->user()), 403);

        $messages = $conversation->messages()->with('sender')
            ->when($request->integer('before_id'), fn ($q, $id) => $q->where('id', '<', $id))
            ->when($request->integer('after_id'), fn ($q, $id) => $q->where('id', '>', $id))
            ->orderByDesc('id')
            ->limit(min($request->integer('limit', 50), 100))
            ->get()
            ->reverse()
            ->values();

        $this->markRead($request->user(), $conversation);

        return MessageResource::collection($messages);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($conversation->canBeAccessedBy($user), 403);

        $request->validate([
            'body' => ['required_without:attachment', 'nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:10240'],
        ]);

        // Admins answering a support chat join it on first reply.
        if ($conversation->type === Conversation::TYPE_SUPPORT && $user->is_admin) {
            $conversation->participants()->syncWithoutDetaching([$user->id]);
            $conversation->load('participants');
        }

        $path = $request->file('attachment')?->store("messages/{$conversation->id}", 'public');
        $message = $this->messaging->post($conversation->load('participants'), $user, $request->input('body'), $path);

        return response()->json(['data' => new MessageResource($message->load('sender'))], 201);
    }

    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->canBeAccessedBy($request->user()), 403);
        $this->markRead($request->user(), $conversation);

        return response()->json(['message' => 'ok']);
    }

    private function markRead(User $user, Conversation $conversation): void
    {
        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
        $user->appNotifications()->whereNull('read_at')->where('type', 'new_message')
            ->where('data->conversation_id', $conversation->id)->update(['read_at' => now()]);
    }
}
