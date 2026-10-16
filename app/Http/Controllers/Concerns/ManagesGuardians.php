<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Guardian handling shared by the admin and teacher student pages. */
trait ManagesGuardians
{
    /** Lookup used by the student form to show whether a mobile already belongs to a parent. */
    public function guardianLookup(Request $request)
    {
        $phone = User::normalizePhone($request->query('phone'));
        $user = $phone ? User::where('phone', $phone)->first() : null;
        if (! $user) {
            return response()->json(['found' => false]);
        }

        return response()->json($user->role === User::ROLE_PARENT
            ? ['found' => true, 'parent' => true, 'name' => $user->name, 'children' => $user->children()->pluck('full_name')]
            : ['found' => true, 'parent' => false]);
    }

    /** Edits a linked guardian's name, mobile and (optionally) sets a new password. */
    public function updateGuardian(Request $request, Student $student, User $parent)
    {
        $this->authorize('manage', $student);
        if ($request->filled('password') && ! $request->user()->isAdmin() && ! $request->user()->hasPermission('teacher.guardian.password')) {
            abort(403, 'تغيير كلمة سر ولي الأمر غير مفعّل لحسابك.');
        }
        abort_unless($parent->role === User::ROLE_PARENT && $student->parents()->whereKey($parent->id)->exists(), 404);

        $request->merge(['phone' => User::normalizePhone($request->input('phone'))]);

        // The mobile already belongs to another parent account (e.g. the father was entered twice with a
        // misspelled name): move the student to that account so the father sees all his children together.
        $owner = User::where('phone', $request->input('phone'))->whereKeyNot($parent->id)->first();
        if ($owner && $owner->role === User::ROLE_PARENT) {
            $relationship = $student->parents()->whereKey($parent->id)->first()->pivot->relationship ?? 'father';
            $student->parents()->detach($parent->id);
            $student->parents()->syncWithoutDetaching([$owner->id => ['relationship' => $relationship]]);
            AuditLogger::log('student.parents', "نقل الطالب {$student->full_name} إلى حساب ولي الأمر {$owner->name} ({$owner->phone})", $student,
                ['parent_id' => $parent->id], ['parent_id' => $owner->id]);

            return back()->with('guardian_notice', "الرقم {$owner->phone} مسجل لولي الأمر {$owner->name}، فرُبط الطالب بحسابه، ويظهر الآن مع إخوته: "
                .$owner->children()->orderBy('full_name')->pluck('full_name')->implode('، ').'.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'phone' => ['required', 'regex:/^05\d{8}$/', Rule::unique('users', 'phone')->ignore($parent)],
            'password' => ['nullable', 'string', 'min:6', 'max:64'],
        ], [
            'phone.regex' => 'رقم الجوال يجب أن يبدأ بـ 05 ويتكون من 10 أرقام.',
            'phone.unique' => 'هذا الرقم مسجل لحساب موظف وليس لولي أمر.',
        ], ['name' => 'اسم ولي الأمر', 'phone' => 'جوال ولي الأمر', 'password' => 'كلمة السر']);

        $parent->fill(['name' => $data['name'], 'phone' => $data['phone']]);
        if (! empty($data['password'])) {
            $parent->password = $data['password'];
            $parent->setRememberToken(Str::random(60)); // signs out "remember me" on other devices
        }
        $changed = array_keys($parent->getDirty());
        $parent->save();

        if ($changed) {
            AuditLogger::log('guardian.update', "تعديل بيانات ولي الأمر {$parent->name} (الطالب {$student->full_name})", $parent, [],
                ['changed' => array_values(array_diff($changed, ['remember_token']))]);
        }

        $msg = "تم حفظ بيانات ولي الأمر {$parent->name}.";
        if (! empty($data['password'])) {
            $msg .= " كلمة السر الجديدة: {$data['password']} (يدخل بالجوال {$parent->phone})، فسلّمها له.";
        }

        return back()->with('guardian_notice', $msg);
    }

    /**
     * Links the guardian typed by mobile number, creating a parent account when the number is new.
     *
     * @return array{user: User, created: bool, password: ?string}|null
     */
    private function attachGuardian(Student $student, array $data): ?array
    {
        if (empty($data['guardian_phone'])) {
            return null;
        }
        $parent = User::where('phone', $data['guardian_phone'])->where('role', User::ROLE_PARENT)->first();
        $password = null;
        if (! $parent) {
            $password = (string) random_int(10000000, 99999999);
            $parent = new User(['name' => trim($data['guardian_name']), 'phone' => $data['guardian_phone'], 'password' => $password]);
            $parent->role = User::ROLE_PARENT;
            $parent->status = 'active';
            $parent->save();
            $parent->parentProfile()->create([]);
            AuditLogger::log('user.create', "إنشاء حساب ولي أمر: {$parent->name} (من نموذج الطالب)", $parent, [], $parent->only(['name', 'phone', 'role']));
        }
        $student->parents()->syncWithoutDetaching([$parent->id => ['relationship' => $data['guardian_relationship'] ?? 'father']]);

        return ['user' => $parent, 'created' => $password !== null, 'password' => $password];
    }

    private function guardianMessage(?array $guardian): ?string
    {
        if (! $guardian) {
            return null;
        }
        $u = $guardian['user'];

        return $guardian['created']
            ? "أُنشئ حساب ولي الأمر {$u->name}: يدخل بالجوال {$u->phone} وكلمة المرور المؤقتة {$guardian['password']}، فسلّمها له."
            : "رُبط الطالب بولي الأمر المسجل {$u->name} ({$u->phone}).";
    }
}
