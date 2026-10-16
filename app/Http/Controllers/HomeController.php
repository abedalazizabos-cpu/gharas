<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\Announcement;
use App\Models\Circle;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\StatsService;
use Illuminate\Support\Facades\Notification;

class HomeController extends Controller
{
    public function index(StatsService $stats)
    {
        return view('public.home', [
            'stats' => $stats->publicSummary(),
            // Only non-personal circle info is exposed publicly.
            'circles' => Circle::active()->where('is_public', true)->orderBy('name')->limit(6)
                ->get(['id', 'name', 'level', 'age_group', 'days', 'time_type', 'prayer', 'time_note', 'start_time', 'end_time', 'description']),
            'announcements' => Announcement::current()->visibleTo(null)->latest('publish_at')->limit(3)->get(),
        ]);
    }

    public function contact(ContactRequest $request)
    {
        $message = ContactMessage::create($request->validated());

        Notification::send(
            User::admins()->active()->get(),
            new SystemNotification('رسالة تواصل جديدة', "من {$message->name}", route('admin.contact.index'), 'envelope'),
        );

        return redirect()->to(route('home').'#contact')->with('success', 'تم استلام رسالتك، وسنتواصل معك قريباً بإذن الله.');
    }
}
