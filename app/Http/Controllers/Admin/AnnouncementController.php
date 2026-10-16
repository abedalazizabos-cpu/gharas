<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.announcements.index', [
            'announcements' => Announcement::with('author')
                ->when($request->query('audience'), fn ($q, $a) => $q->where('audience', $a))
                ->latest()->paginate(15)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('admin.announcements.form', ['announcement' => new Announcement(['audience' => 'all', 'publish_at' => now()])]);
    }

    public function store(AnnouncementRequest $request)
    {
        $announcement = new Announcement($request->validated());
        $announcement->created_by = $request->user()->id;
        $announcement->save();
        AuditLogger::log('announcement.create', "نشر إعلان «{$announcement->title}»", $announcement);

        if ($request->boolean('notify')) {
            $this->notifyAudience($announcement);
        }

        return redirect()->route('admin.announcements.index')->with('success', 'تم حفظ الإعلان بنجاح.');
    }

    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.form', compact('announcement'));
    }

    public function update(AnnouncementRequest $request, Announcement $announcement)
    {
        $announcement->update($request->validated());
        AuditLogger::logChanges('announcement.update', "تعديل إعلان «{$announcement->title}»", $announcement);

        return redirect()->route('admin.announcements.index')->with('success', 'تم تحديث الإعلان.');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        AuditLogger::log('announcement.delete', "حذف إعلان «{$announcement->title}»", $announcement);

        return back()->with('success', 'تم حذف الإعلان.');
    }

    private function notifyAudience(Announcement $announcement): void
    {
        $roles = match ($announcement->audience) {
            'teachers' => [User::ROLE_TEACHER],
            'parents' => [User::ROLE_PARENT],
            default => [User::ROLE_TEACHER, User::ROLE_PARENT],
        };
        $users = User::whereIn('role', $roles)->active()->get();

        foreach ($users as $user) {
            $user->notify(new SystemNotification(
                'إعلان جديد', $announcement->title,
                route($user->isTeacher() ? 'teacher.announcements' : 'parent.announcements'), 'megaphone'
            ));
        }
    }
}
