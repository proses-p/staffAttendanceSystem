<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'organization',
        'phone',
        'employee_id',
        'password',
        'is_active',

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function fieldAssignments(): HasMany
    {
        return $this->hasMany(FieldAssignment::class);
    }

    public function fieldExpenses(): HasMany
    {
        return $this->hasMany(FieldExpense::class);
    }

    public function fieldExpenseReviews(): HasMany
    {
        return $this->hasMany(FieldExpenseReviewAudit::class, 'reviewer_id');
    }

    public function leaveRecords(): HasMany
    {
        return $this->hasMany(StaffLeaveRecord::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function companyAccessMessage(): ?string
    {
        if ($this->role === 'super_admin') {
            return null;
        }

        $company = $this->company;

        if (! $company) {
            return 'Your account has no company assignment. Contact a platform administrator to assign it.';
        }

        if ($company->status !== Company::STATUS_ACTIVE) {
            return 'Your company is suspended. Contact platform support.';
        }

        return null;
    }

    public function staffDevices(): HasMany
    {
        return $this->hasMany(StaffDevice::class);
    }
}
