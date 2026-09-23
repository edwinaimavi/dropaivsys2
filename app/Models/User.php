<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'dni',
        'name',
        'lastname',
        'email',
        'password',
        'phone',
        'address',
        'photo',
        'status',
        'created_by',
        'updated_by',
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
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withTimestamps();
    }

    public function belongsToCompany(int $companyId): bool
    {
        if ($companyId <= 0) {
            return false;
        }

        if ($this->relationLoaded('companies')) {
            return $this->companies->contains('id', $companyId);
        }

        return $this->companies()->whereKey($companyId)->exists();
    }

    public function createdWorkAgendaItems(): HasMany
    {
        return $this->hasMany(WorkAgendaItem::class, 'created_by_user_id');
    }

    public function responsibleWorkAgendaItems(): HasMany
    {
        return $this->hasMany(WorkAgendaItem::class, 'responsible_user_id');
    }

    public function workAgendaAssignments(): HasMany
    {
        return $this->hasMany(WorkAgendaItemAssignment::class);
    }

    public function createdAssistanceNotes(): HasMany
    {
        return $this->hasMany(AssistanceNote::class, 'created_by_user_id');
    }

    public function responsibleAssistanceNotes(): HasMany
    {
        return $this->hasMany(AssistanceNote::class, 'responsible_user_id');
    }

    public function sharedAssistanceNotes(): BelongsToMany
    {
        return $this->belongsToMany(
            AssistanceNote::class,
            'assistance_note_shares',
            'user_id',
            'assistance_note_id'
        )->withPivot(['company_id', 'shared_by_user_id', 'created_at']);
    }

    public function preference()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(self::class, 'updated_by');
    }

    public function roleHistories(): HasMany
    {
        return $this->hasMany(UserRoleHistory::class);
    }

    public function latestRoleHistory(): HasOne
    {
        return $this->hasOne(UserRoleHistory::class)->latestOfMany('performed_at');
    }

    public function themeMode(): string
    {
        return $this->preference?->theme_mode ?? 'light';
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
