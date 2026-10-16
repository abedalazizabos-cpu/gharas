<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingsRequest;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\BackupService;

class SettingController extends Controller
{
    public function edit()
    {
        $settings = collect(Setting::DEFAULTS)->mapWithKeys(fn ($v, $k) => [$k => Setting::get($k)]);

        return view('admin.settings.edit', compact('settings'));
    }

    public function update(SettingsRequest $request)
    {
        $data = $request->validated();
        $data['allow_capacity_override'] = $request->boolean('allow_capacity_override') ? '1' : '0';
        foreach (['reminders_enabled', 'logo_background', 'logo_on_pdf', 'logo_favicon', 'reminders_on_login'] as $flag) {
            $data[$flag] = $request->boolean($flag) ? '1' : '0';
        }
        $data['reminders_days'] = collect($data['reminders_days'])->map(fn ($d) => (int) $d)->unique()->sort()->implode(',');
        $data['reminders_roles'] = collect($data['reminders_roles'] ?? [])->implode(',');
        $data['center_logo'] = $this->storeLogo($request);
        $old = [];
        $new = [];

        foreach (array_keys(Setting::DEFAULTS) as $key) {
            $value = isset($data[$key]) ? (string) $data[$key] : null;
            if (Setting::get($key) !== $value) {
                $old[$key] = Setting::get($key);
                $new[$key] = $value;
            }
            Setting::put($key, $value);
        }

        if ($new) {
            AuditLogger::log('settings.update', 'تعديل إعدادات النظام', null, $old, $new);
        }

        return back()->with('success', 'تم حفظ الإعدادات بنجاح.');
    }

    /** Logos live in public/uploads so they work without `storage:link` (simpler on Windows). */
    private function storeLogo(SettingsRequest $request): ?string
    {
        $current = Setting::get('center_logo');
        $remove = $request->boolean('remove_logo') || $request->hasFile('logo');
        if ($remove && $current && is_file(public_path($current))) {
            @unlink(public_path($current));
        }
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $name = 'logo-'.now()->format('YmdHis').'.'.$file->extension();
            $file->move(public_path('uploads'), $name);

            return 'uploads/'.$name;
        }

        return $remove ? null : $current;
    }

    public function backup(BackupService $backup)
    {
        $path = $backup->create();
        AuditLogger::log('system.backup', 'إنشاء نسخة احتياطية لقاعدة البيانات');

        return response()->download($path)->deleteFileAfterSend(false);
    }
}
