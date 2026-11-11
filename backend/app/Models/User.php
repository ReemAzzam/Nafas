<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'email',
        'password',
        'country_code',
        'phone',
        'date_of_birth',
        'profile_image_url',
        'status',
        'onboarding_status',
        'is_anonymous',

    ];

    public function getFormattedPhoneAttribute(): ?string
    {

        if (! $this->phone) {

            return null;

        }

        // محفوظ أصلاً كـ E.164

        if (str_starts_with($this->phone, '+')) {

            return $this->phone;

        }

        $util = PhoneNumberUtil::getInstance();

        $country = $this->country_code ?: null;

        try {

            $parsed = $util->parse($this->phone, $country);

            return $util->format($parsed, PhoneNumberFormat::E164);

        } catch (NumberParseException $e) {

            if (! $country) {

                return $this->phone;

            }

            $dial = $util->getCountryCodeForRegion($country); // SY → 963

            return $dial ? '+'.$dial.ltrim($this->phone, '0') : $this->phone;

        }

    }

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
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'is_anonymous' => 'boolean',
            'is_blocked' => 'boolean',
        ];
    }

    public function getAgeAttribute(): ?int
    {
        $value = $this->attributes['date_of_birth'] ?? null;

        return $value ? Carbon::parse($value)->age : null;
    }

    public function goals()
    {
        return $this->belongsToMany(Goal::class, 'user_goals')->withTimestamps(); // [cite: 6]
    }

    public function factors()
    {
        return $this->belongsToMany(Factor::class, 'user_factors')->withTimestamps();
    }

    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

    public function wellnessPlans()
    {
        return $this->hasMany(WellnessPlan::class); // [cite: 6]
    }

    public function moodLogs()
    {
        return $this->hasMany(MoodLog::class); // [cite: 6]
    }

    public function feedbacks()
    {
        return $this->hasMany(Feedback::class); // [cite: 6]
    }

    public function supportMethods()
    {
        return $this->belongsToMany(SupportMethod::class, 'user_support_methods')->withTimestamps();
    }

    public function activityCompletions()
    {
        return $this->hasMany(ActivityCompletion::class);
    }

    public function sosContacts()
    {
        return $this->hasMany(SosContact::class);
    }

    public function articleComments()
    {
        return $this->hasMany(ArticleComment::class);
    }

    public function likedArticleComments()
    {
        return $this->belongsToMany(ArticleComment::class, 'article_comment_likes')->withTimestamps();
    }

    public function articleCommentReports()
    {
        return $this->hasMany(ArticleCommentReport::class);
    }

    public function favoriteArticles()
    {
        return $this->belongsToMany(Article::class, 'article_favorites')->withTimestamps();
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class);
    }
}
