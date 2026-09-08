<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'role',
        'is_active',
        'verification_token',
        'verification_token_expires_at',
        'avatar_path',
        'show_email',
        'allow_data_research',
        'email_notifications',
        'push_notifications',
        'session_reminders',
        'marketing_emails',
        'preferred_language',
        'preferred_communication_mode',
        'preferred_helper_gender',
        'session_duration_preference',
        'font_size',
        'high_contrast',
        'reduced_motion',
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
            'show_email' => 'boolean',
            'allow_data_research' => 'boolean',
            'email_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'session_reminders' => 'boolean',
            'marketing_emails' => 'boolean',
            'high_contrast' => 'boolean',
            'reduced_motion' => 'boolean',
        ];
    }

    public function helpSeeker()
    {
        return $this->hasOne(HelpSeeker::class, 'user_account_id', 'id');
    }

    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isAdviser(): bool { return $this->role === 'adviser'; }
    public function isHelper(): bool { return $this->role === 'helper'; }
    public function isSeeker(): bool { return $this->role === 'seeker'; }
    public function isModerator(): bool { return $this->role === 'moderator'; }
    public function isProfessional(): bool { return $this->role === 'professional'; }

    public function helper()
    {
        return $this->hasOne(Helper::class, 'user_account_id', 'id');
    }

    public function adviser()
    {
        return $this->hasOne(Adviser::class, 'user_account_id', 'id');
    }

    public function moderator()
    {
        return $this->hasOne(Moderator::class, 'user_account_id', 'id');
    }

    public function psychologyProfessional()
    {
        return $this->hasOne(PsychologyProfessional::class, 'user_account_id', 'id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_account_id', 'id');
    }

    public function unreadNotifications(): HasMany
    {
        return $this->notifications()->where('status', 'unread');
    }

    public function savedResources(): HasMany
    {
        return $this->hasMany(UserSavedResource::class, 'user_id', 'id');
    }

    public function resourceProgress(): HasMany
    {
        return $this->hasMany(UserResourceProgress::class, 'user_id', 'id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNotNull('email_verified_at');
    }

    public function displayName(): string
    {
        if ($this->role === 'helper') {
            return $this->helper?->public_alias ?? 'Peer Helper';
        }
        $alias = $this->helpSeeker?->generated_alias;

        return $alias ?: ($this->name ?: 'Seeker');
    }

    public function initials(): string
    {
        $name = $this->displayName();

        $parts = preg_split('/[\s_]+/', trim($name));

        return strtoupper(mb_substr($parts[0] ?? 'S', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
    }
}
