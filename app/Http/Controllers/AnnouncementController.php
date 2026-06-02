<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::query()
            ->with(['images', 'recipients.user'])
            ->orderByDesc('created_at')
            ->get();

        return view('admin.announcements.index', compact('announcements'));
    }

    public function create()
    {
        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.announcements.create', compact('users'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($request, $data) {
            $announcement = Announcement::create([
                'title' => $data['title'],
                'body' => $data['body'],
                'scope' => $data['scope'],
                'audience' => $data['audience'],
                'is_active' => true,
                'published_at' => now(),
                'created_by' => auth()->id(),
            ]);

            $this->syncRecipients($announcement, $data['audience'], $data['user_ids'] ?? []);
            $this->storeImages($announcement, $request);
        });

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Aviso publicado correctamente');
    }

    public function edit(Announcement $announcement)
    {
        $announcement->load(['images', 'recipients']);

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $selectedUserIds = $announcement->recipients()
            ->pluck('user_id')
            ->all();

        return view('admin.announcements.edit', compact('announcement', 'users', 'selectedUserIds'));
    }

    public function show(Announcement $announcement)
    {
        return redirect()->route('admin.announcements.edit', $announcement);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $data = $this->validatedData($request, $announcement->id);

        DB::transaction(function () use ($request, $announcement, $data) {
            $announcement->update([
                'title' => $data['title'],
                'body' => $data['body'],
                'scope' => $data['scope'],
                'audience' => $data['audience'],
                'is_active' => $request->boolean('is_active'),
            ]);

            $this->syncRecipients($announcement, $data['audience'], $data['user_ids'] ?? []);
            $this->storeImages($announcement, $request);
        });

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Aviso actualizado');
    }

    public function destroy(Announcement $announcement)
    {
        foreach ($announcement->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $announcement->delete();

        return back()->with('success', 'Aviso eliminado');
    }

    protected function validatedData(Request $request, ?int $announcementId = null): array
    {
        if ($request->input('scope') === 'public' && ! $request->filled('audience')) {
            $request->merge([
                'audience' => $request->input('audience_public_fallback', 'all'),
            ]);
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'scope' => 'required|in:public,internal',
            'audience' => 'required|in:all,teachers,students,specific',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|max:2048',
            'is_active' => 'nullable|boolean',
        ]);

        if (($data['scope'] ?? null) === 'internal' && ($data['audience'] ?? null) === 'specific' && empty($data['user_ids'])) {
            throw ValidationException::withMessages([
                'user_ids' => 'Debes seleccionar al menos un usuario para la audiencia específica.',
            ]);
        }

        return $data;
    }

    protected function syncRecipients(Announcement $announcement, string $audience, array $userIds = []): void
    {
        $announcement->recipients()->delete();

        if ($announcement->scope !== 'internal') {
            return;
        }

        $users = match ($audience) {
            'all' => User::query()->pluck('id')->all(),
            'teachers' => User::role('teacher')->pluck('id')->all(),
            'students' => User::role('student')->pluck('id')->all(),
            'specific' => collect($userIds)->map(fn ($id) => (int) $id)->unique()->values()->all(),
            default => [],
        };

        if (empty($users)) {
            return;
        }

        $rows = array_map(function (int $userId) use ($announcement) {
            return [
                'announcement_id' => $announcement->id,
                'user_id' => $userId,
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $users);

        $announcement->recipients()->insert($rows);
    }

    protected function storeImages(Announcement $announcement, Request $request): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $startPosition = (int) $announcement->images()->max('position');

        foreach ($request->file('images', []) as $index => $imageFile) {
            $path = $imageFile->store('announcements', 'public');

            $announcement->images()->create([
                'path' => $path,
                'alt' => $announcement->title,
                'position' => $startPosition + $index + 1,
            ]);
        }
    }
}
