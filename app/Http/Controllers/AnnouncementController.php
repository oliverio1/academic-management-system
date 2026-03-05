<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Announcement;

class AnnouncementController extends Controller
{
    public function index() {
        $announcements = Announcement::orderByDesc('created_at')->get();
        return view('admin.announcements.index',compact('announcements'));
    }

    public function create() {
        return view('admin.announcements.create');
    }

    public function store(Request $request) {
        $request->validate([
            'title'    => 'required|string|max:255',
            'body'     => 'required|string',
            'scope'    => 'required|in:public,internal',
            'audience' => 'required|in:all,teachers,students,specific',
            'images.*' => 'nullable|image|max:2048',
        ]);
        Announcement::create([
            'title' => $request->title,
            'body' => $request->body,
            'scope' => $request->scope,
            'audience' => $request->audience,
            'is_active' => true,
            'published_at' => now(),
        ]);
        return redirect()->route('admin.announcements.index')->with('success', 'Aviso publicado correctamente');
    }

    public function edit(Announcement $announcement) {
        return view('admin.announcements.edit',compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement) {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'target' => 'required|in:all,teachers,students,tutors',
            'is_active' => 'boolean',
        ]);
        $announcement->update([
            'title' => $request->title,
            'body' => $request->body,
            'target' => $request->target,
            'is_active' => $request->boolean('is_active'),
        ]);
        return redirect()->route('admin.announcements.index')->with('success', 'Aviso actualizado');
    }

    public function destroy(Announcement $announcement) {
        $announcement->delete();
        return back()->with('success', 'Aviso eliminado');
    }
}