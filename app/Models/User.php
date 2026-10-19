<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_TEACHER = 'teacher';
    public const ROLE_PARENT = 'parent';
    public const ROLE_VISITOR = 'visitor';

    public const ROLES = [
        self::ROLE_SUPERADMIN => 'المدير الأعلى',
        self::ROLE_ADMIN => 'مدير النظام',
        self::ROLE_TEACHER => 'محفظ',
        self::ROLE_PARENT => 'ولي أمر',
        self::ROLE_VISITOR => 'زائر / داعم',
    ];

    public const STATUSES = [
        'active' => 'نشط',
        'suspended' => 'موقوف',
    ];

    // role and status are deliberately not mass assignable.
    protected $fillable = ['name', 'email', 'phone', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Admin area access: the super admin is an admin with every permission. */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN || $this->role === self::ROLE_SUPERADMIN;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function hasPermission(string $permission): bool
    {
        return \App\Support\Permissions::allows($this, $permission);
    }

    public function isTeacher(): bool
    {
        return $this->role === self::ROLE_TEACHER;
    }

    public function isParent(): bool
    {
        return $this->role === self::ROLE_PARENT;
    }

    /** Read-only account for external delegations and supporters. */
    public function isVisitor(): bool
    {
        return $this->role === self::ROLE_VISITOR;
    }

    /**
     * Normalise a mobile number so "٠٥٥ ٠٠٠ ٠٠٠١", "055-000-0001" and
     * "+966550000001" all match the stored "0550000001".
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }
        $phone = strtr($phone, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '00966')) {
            $digits = '0'.substr($digits, 5);
        } elseif (str_starts_with($digits, '966') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 3);
        }

        return $digits;
    }

    protected function phone(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(set: fn (?string $value) => static::normalizePhone($value));
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function dashboardRoute(): string
    {
        return ($this->isSuperAdmin() ? self::ROLE_ADMIN : $this->role).'.dashboard';
    }

    public function scopeRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    /** Admins and the super admin. */
    /** Name shown to $viewer: the super admin appears only as «إدارة المركز» to everyone else. */
    public function nameFor(?User $viewer): string
    {
        return $this->isSuperAdmin() && ! $viewer?->isSuperAdmin() ? 'إدارة المركز' : $this->name;
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->whereIn('role', [self::ROLE_ADMIN, self::ROLE_SUPERADMIN]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function parentProfile(): HasOne
    {
        return $this->hasOne(ParentProfile::class);
    }

    /** Circles taught by this user (teacher). */
    public function circles(): HasMany
    {
        return $this->hasMany(Circle::class, 'teacher_id');
    }

    /** Warnings and notices the administration issued to this teacher. */
    public function warnings(): HasMany
    {
        return $this->hasMany(TeacherWarning::class, 'teacher_id');
    }

    /** Children linked to this user (parent). */
    public function permissionRules(): HasMany
    {
        return $this->hasMany(PermissionRule::class);
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_student', 'parent_id', 'student_id')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    /** Students currently in this teacher's circles. */
    public function teacherStudents(): Builder
    {
        return Student::query()->whereIn('current_circle_id', $this->circles()->select('id'));
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'recipient_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function teachesStudent(Student $student): bool
    {
        return $student->current_circle_id !== null
            && $this->circles()->whereKey($student->current_circle_id)->exists();
    }

    public function isParentOf(Student $student): bool
    {
        return $this->children()->whereKey($student->id)->exists();
    }
}
