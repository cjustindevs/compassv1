<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Account roles currently supported by COMPASS.
     *
     * @var array<string, string>
     */
    public const ROLE_LABELS = [
        'seeker' => 'Help Seeker',
        'helper' => 'Helper',
        'moderator' => 'Moderator',
        'adviser' => 'Adviser',
        'professional' => 'Psychology Professional',
        'admin' => 'Administrator',
    ];

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
        'dark_mode',
        'font_size',
        'high_contrast',
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
            'show_email' => 'boolean',
            'allow_data_research' => 'boolean',
            'email_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'session_reminders' => 'boolean',
            'marketing_emails' => 'boolean',
            'dark_mode' => 'boolean',
            'high_contrast' => 'boolean',
        ];
    }

    public function helpSeeker()
    {
        return $this->hasOne(HelpSeeker::class, 'user_account_id', 'id');
    }

    public function helper(): HasOne
    {
        return $this->hasOne(Helper::class, 'user_account_id', 'id');
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
        return $query->whereNotNull('email_verified_at');
    }

    public function displayName(): string
    {
        $alias = $this->helpSeeker?->generated_alias;

        return $alias ?: ($this->name ?: 'Seeker');
    }

    public function initials(): string
    {
        $name = $this->displayName();

        $parts = preg_split('/[\s_]+/', trim($name));

        return strtoupper(mb_substr($parts[0] ?? 'S', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }
}
