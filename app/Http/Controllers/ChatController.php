<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Group;
use App\Models\SchoolCycle;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $selectedId = (int) $request->query('conversation');

        $conversationIds = $user->loadMissing('student')->chatParticipations()
            ->pluck('chat_conversations.id');

        $conversations = ChatConversation::query()
            ->with(['participants:id,name', 'group:id,name'])
            ->whereIn('id', $conversationIds)
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->get()
            ->filter(fn (ChatConversation $conversation) => $this->userCanAccessConversation($user, $conversation))
            ->values();

        $selected = $selectedId
            ? $conversations->firstWhere('id', $selectedId)
            : $conversations->first();

        $messages = collect();
        if ($selected) {
            $messages = ChatMessage::query()
                ->with('user:id,name')
                ->where('conversation_id', $selected->id)
                ->orderBy('id')
                ->limit(200)
                ->get();

            $selected->participants()
                ->updateExistingPivot($user->id, ['last_read_at' => now()]);
        }

        return view('chat.index', [
            'conversations' => $conversations,
            'selectedConversation' => $selected,
            'messages' => $messages,
            'availableUsers' => $this->availableUsersFor($user),
            'availableGroups' => $this->availableGroupsFor($user),
        ]);
    }

    public function bootstrap(Request $request)
    {
        $user = $request->user();
        $conversationIds = $user->chatParticipations()->pluck('chat_conversations.id');

        $conversations = ChatConversation::query()
            ->with(['participants:id,name', 'group:id,name'])
            ->whereIn('id', $conversationIds)
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->get()
            ->filter(fn (ChatConversation $conversation) => $this->userCanAccessConversation($user, $conversation))
            ->values();

        $selected = $conversations->first();
        $messages = $selected
            ? ChatMessage::query()
                ->with('user:id,name')
                ->where('conversation_id', $selected->id)
                ->orderBy('id')
                ->limit(200)
                ->get()
            : collect();

        if ($selected) {
            $selected->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
        }

        return response()->json([
            'conversations' => $conversations->map(fn ($conversation) => $this->conversationToArray($conversation, $user)),
            'selected_conversation_id' => $selected?->id,
            'messages' => $messages->map(fn ($message) => $this->messageToArray($message)),
            'available_users' => $this->availableUsersFor($user)->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
            'available_groups' => $this->availableGroupsFor($user)->map(fn ($g) => ['id' => $g->id, 'name' => $g->name]),
            'unread' => $this->countUnreadConversations($user),
        ]);
    }

    public function storeDirectConversation(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $authUser = $request->user();
        $target = User::findOrFail($data['user_id']);

        abort_if($target->id === $authUser->id, 422, 'No puedes crear chat contigo mismo.');
        abort_unless($this->availableUsersFor($authUser)->contains('id', $target->id), 403);

        $existing = ChatConversation::query()
            ->where('type', 'direct')
            ->whereHas('participants', fn ($q) => $q->where('users.id', $authUser->id))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $target->id))
            ->get()
            ->first(function (ChatConversation $conversation) {
                return $conversation->participants()->count() === 2;
            });

        if (! $existing) {
            $existing = ChatConversation::create([
                'type' => 'direct',
                'title' => null,
                'created_by' => $authUser->id,
            ]);
            $existing->participants()->sync([
                $authUser->id => ['last_read_at' => now()],
                $target->id => ['last_read_at' => null],
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'conversation_id' => $existing->id,
            ]);
        }

        return redirect()->route('chat.index', ['conversation' => $existing->id]);
    }

    public function storeGroupConversation(Request $request)
    {
        $data = $request->validate([
            'group_id' => ['required', 'integer', 'exists:groups,id'],
        ]);

        $authUser = $request->user();
        $group = Group::findOrFail($data['group_id']);

        abort_unless($this->availableGroupsFor($authUser)->contains('id', $group->id), 403);

        $conversation = ChatConversation::query()
            ->where('type', 'group')
            ->where('group_id', $group->id)
            ->first();

        if (! $conversation) {
            $conversation = ChatConversation::create([
                'type' => 'group',
                'title' => 'Grupo ' . $group->name,
                'group_id' => $group->id,
                'created_by' => $authUser->id,
            ]);
        }

        $participantIds = $this->groupParticipantUserIds($group);
        if (! in_array($authUser->id, $participantIds, true)) {
            $participantIds[] = $authUser->id;
        }

        $syncData = [];
        foreach ($participantIds as $participantId) {
            $syncData[$participantId] = ['last_read_at' => $participantId === $authUser->id ? now() : null];
        }
        $conversation->participants()->syncWithoutDetaching($syncData);

        if ($request->expectsJson()) {
            return response()->json([
                'conversation_id' => $conversation->id,
            ]);
        }

        return redirect()->route('chat.index', ['conversation' => $conversation->id]);
    }

    public function storeMessage(Request $request, ChatConversation $conversation)
    {
        $authUser = $request->user();
        abort_unless($this->userCanAccessConversation($authUser, $conversation), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'user_id' => $authUser->id,
            'body' => trim($data['body']),
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);
        $conversation->participants()->updateExistingPivot($authUser->id, ['last_read_at' => now()]);

        if ($request->expectsJson()) {
            $message->loadMissing('user:id,name');
            return response()->json([
                'message' => $this->messageToArray($message),
            ]);
        }

        return redirect()->route('chat.index', ['conversation' => $conversation->id]);
    }

    public function fetchMessages(Request $request, ChatConversation $conversation)
    {
        $authUser = $request->user();
        abort_unless($this->userCanAccessConversation($authUser, $conversation), 403);

        $afterId = (int) $request->query('after_id', 0);

        $messages = ChatMessage::query()
            ->with('user:id,name')
            ->where('conversation_id', $conversation->id)
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')
            ->get()
            ->map(fn (ChatMessage $message) => [
                'id' => $message->id,
                'user_id' => $message->user_id,
                'user_name' => $message->user->name ?? 'Usuario',
                'body' => $message->body,
                'time' => optional($message->created_at)->format('d/m/Y H:i'),
            ]);

        $conversation->participants()->updateExistingPivot($authUser->id, ['last_read_at' => now()]);

        return response()->json([
            'messages' => $messages,
        ]);
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'unread' => $this->countUnreadConversations($request->user()),
        ]);
    }

    protected function availableUsersFor(User $authUser): Collection
    {
        if ($authUser->hasRole('admin') || $authUser->hasRole('coordinator')) {
            return User::query()
                ->select('id', 'name')
                ->where('id', '!=', $authUser->id)
                ->orderBy('name')
                ->get();
        }

        if ($authUser->hasRole('teacher')) {
            $administrativeUserIds = $this->administrativeUserIds();
            $studentUserIds = $this->studentUserIdsForTeacher($authUser);
            $allowedIds = $administrativeUserIds->merge($studentUserIds)->unique()->values();

            return User::query()
                ->select('id', 'name')
                ->whereIn('id', $allowedIds)
                ->where('id', '!=', $authUser->id)
                ->orderBy('name')
                ->get();
        }

        if ($authUser->hasRole('student') && $authUser->student) {
            $teacherUserIds = $this->teacherUserIdsForStudent($authUser->student);
            $administrativeUserIds = $this->administrativeUserIds();
            $allowedIds = $teacherUserIds->merge($administrativeUserIds)->unique()->values();

            return User::query()
                ->select('id', 'name')
                ->whereIn('id', $allowedIds)
                ->where('id', '!=', $authUser->id)
                ->orderBy('name')
                ->get();
        }

        if ($authUser->hasRole('prefect')) {
            $administrativeUserIds = User::role(['coordinator', 'admin'])->pluck('id');
            $teacherUserIds = Teacher::query()
                ->whereNotNull('user_id')
                ->pluck('user_id');

            $allowedIds = $administrativeUserIds->merge($teacherUserIds)->unique()->values();

            return User::query()
                ->select('id', 'name')
                ->whereIn('id', $allowedIds)
                ->where('id', '!=', $authUser->id)
                ->orderBy('name')
                ->get();
        }

        if ($authUser->hasRole('guardian') || $authUser->hasRole('tutor')) {
            $administrativeUserIds = User::role(['coordinator', 'admin'])->pluck('id');

            return User::query()
                ->select('id', 'name')
                ->whereIn('id', $administrativeUserIds)
                ->where('id', '!=', $authUser->id)
                ->orderBy('name')
                ->get();
        }

        return collect();
    }

    protected function availableGroupsFor(User $authUser): Collection
    {
        if ($authUser->hasRole('student') || $authUser->hasRole('guardian') || $authUser->hasRole('tutor')) {
            return collect();
        }

        if ($authUser->hasRole('teacher') && $authUser->teacher) {
            $activeCycleId = $this->activeCycleIdForCurrentCampus();
            if (! $activeCycleId) {
                return collect();
            }

            $groupIds = $this->assignmentsForActiveCycle()
                ->where('teacher_id', $authUser->teacher->id)
                ->pluck('group_id')
                ->unique();

            return Group::query()
                ->whereIn('id', $groupIds)
                ->get(['id', 'name']);
        }

        return Group::query()
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function groupParticipantUserIds(Group $group): array
    {
        $activeCycleId = $this->activeCycleIdForCurrentCampus();
        if (! $activeCycleId) {
            return [];
        }

        $studentUserIds = Student::query()
            ->where('group_id', $group->id)
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();

        $teacherUserIds = $this->assignmentsForActiveCycle()
            ->where('group_id', $group->id)
            ->pluck('teacher_id')
            ->pipe(fn ($teacherIds) => Teacher::query()
                ->whereIn('id', $teacherIds)
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->all());

        $staffUserIds = User::role(['coordinator', 'admin'])->pluck('id')->all();

        return array_values(array_unique(array_merge($studentUserIds, $teacherUserIds, $staffUserIds)));
    }

    protected function userCanAccessConversation(User $authUser, ChatConversation $conversation): bool
    {
        if (! $conversation->participants()->where('users.id', $authUser->id)->exists()) {
            return false;
        }

        if ($authUser->hasRole('admin') || $authUser->hasRole('coordinator')) {
            return true;
        }

        if ($conversation->type === 'direct') {
            $otherUserId = (int) $conversation->participants()
                ->where('users.id', '!=', $authUser->id)
                ->value('users.id');

            return $otherUserId > 0
                && $this->availableUsersFor($authUser)->contains('id', $otherUserId);
        }

        if ($conversation->type === 'group') {
            return $conversation->group_id
                && $this->availableGroupsFor($authUser)->contains('id', (int) $conversation->group_id);
        }

        return false;
    }

    protected function administrativeUserIds(): Collection
    {
        return collect(User::role(['coordinator', 'admin'])->pluck('id')->all());
    }

    protected function studentUserIdsForTeacher(User $authUser): Collection
    {
        if (! $authUser->teacher) {
            return collect();
        }

        $assignments = $this->assignmentsForActiveCycle()
            ->with([
                'students:id,user_id',
                'group.students:id,user_id,group_id,is_active',
            ])
            ->where('teacher_id', $authUser->teacher->id)
            ->get(['id', 'group_id']);

        return collect($assignments
            ->flatMap(function (TeachingAssignment $assignment) {
                $sectionStudents = $assignment->students
                    ->pluck('user_id')
                    ->filter();

                if ($sectionStudents->isNotEmpty()) {
                    return $sectionStudents;
                }

                return optional($assignment->group)->students
                    ? $assignment->group->students
                        ->where('is_active', true)
                        ->pluck('user_id')
                        ->filter()
                    : collect();
            })
            ->unique()
            ->values()
            ->all());
    }

    protected function teacherUserIdsForStudent(Student $student): Collection
    {
        $assignments = $this->assignmentsForActiveCycle()
            ->with(['teacher:id,user_id', 'students:id'])
            ->where('group_id', $student->group_id)
            ->get(['id', 'teacher_id', 'group_id']);

        return collect($assignments
            ->filter(function (TeachingAssignment $assignment) use ($student) {
                return $assignment->students->isEmpty()
                    || $assignment->students->contains('id', (int) $student->id);
            })
            ->map(fn (TeachingAssignment $assignment) => optional($assignment->teacher)->user_id)
            ->filter()
            ->unique()
            ->values()
            ->all());
    }

    protected function assignmentsForActiveCycle()
    {
        $activeCycleId = $this->activeCycleIdForCurrentCampus();

        return TeachingAssignment::query()
            ->where('is_active', true)
            ->when(
                $activeCycleId,
                fn ($query) => $query->where(function ($nested) use ($activeCycleId) {
                    $nested->whereHas('schoolCycleGroup', fn ($q) => $q->where('school_cycle_id', $activeCycleId))
                        ->orWhereHas('schedules', fn ($q) => $q->where('school_cycle_id', $activeCycleId));
                }),
                fn ($query) => $query->whereRaw('1 = 0')
            );
    }

    protected function activeCycleIdForCurrentCampus(): ?int
    {
        $activeCampusId = (int) session('active_campus_id', 0);

        return SchoolCycle::query()
            ->where('is_active', true)
            ->when($activeCampusId > 0, fn ($query) => $this->applyCampusFilterToCycleQuery($query, $activeCampusId))
            ->orderByDesc('start_date')
            ->value('id');
    }

    protected function applyCampusFilterToCycleQuery($query, int $activeCampusId): void
    {
        $query->where(function ($nested) use ($activeCampusId) {
            $nested->where('campus_id', $activeCampusId)
                ->orWhereHas('campuses', fn ($campuses) => $campuses->where('campuses.id', $activeCampusId));
        });
    }

    protected function countUnreadConversations(User $user): int
    {
        $rows = DB::table('chat_participants as cp')
            ->select('cp.conversation_id', 'cp.last_read_at')
            ->where('cp.user_id', $user->id)
            ->get();

        $accessibleConversationIds = ChatConversation::query()
            ->with(['participants:id', 'group:id'])
            ->whereIn('id', $rows->pluck('conversation_id'))
            ->get()
            ->filter(fn (ChatConversation $conversation) => $this->userCanAccessConversation($user, $conversation))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $count = 0;
        foreach ($rows as $row) {
            if (! in_array((int) $row->conversation_id, $accessibleConversationIds, true)) {
                continue;
            }

            $hasUnread = DB::table('chat_messages')
                ->where('conversation_id', $row->conversation_id)
                ->where('user_id', '!=', $user->id)
                ->when($row->last_read_at, fn ($q) => $q->where('created_at', '>', $row->last_read_at))
                ->exists();

            if ($hasUnread) {
                $count++;
            }
        }

        return $count;
    }

    protected function conversationToArray(ChatConversation $conversation, User $authUser): array
    {
        $label = $conversation->type === 'group'
            ? ($conversation->title ?: 'Grupo ' . optional($conversation->group)->name)
            : optional($conversation->participants->where('id', '!=', $authUser->id)->first())->name;

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'label' => $label ?: 'Conversación',
            'last_message_at' => optional($conversation->last_message_at)->format('d/m/Y H:i'),
        ];
    }

    protected function messageToArray(ChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'user_name' => $message->user->name ?? 'Usuario',
            'body' => $message->body,
            'time' => optional($message->created_at)->format('d/m/Y H:i'),
        ];
    }
}
