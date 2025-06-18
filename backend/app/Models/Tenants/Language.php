<?php
namespace App\Models\Tenants;

use App\Traits\Tenant\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Language extends Model
{
    use HasFactory, HasAuditLog, BelongsToTenant;

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'is_active',
        'tenant_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected array $auditLogEvents = [
        'created' => 'Created new language: :code (:name)',
        'updated' => 'Updated language: :code',
        'deleted' => 'Deleted language: :code',
    ];

    protected array $auditLogProperties = [
        'code',
        'name',
        'native_name',
        'is_active',
    ];

    /**
     * Get the words in this language.
     */
    public function words(): HasMany
    {
        return $this->hasMany(Word::class);
    }

    /**
     * Get the sentences in this language.
     */
    public function sentences(): HasMany
    {
        return $this->hasMany(Sentence::class);
    }

    /**
     * Get language pairs where this is the source language.
     */
    public function sourceLanguagePairs(): HasMany
    {
        return $this->hasMany(LanguagePair::class, 'source_language_id');
    }

    /**
     * Get language pairs where this is the target language.
     */
    public function targetLanguagePairs(): HasMany
    {
        return $this->hasMany(LanguagePair::class, 'target_language_id');
    }

    /**
     * Get languages that this language can be translated from.
     */
    public function sourceLanguages(): BelongsToMany
    {
        return $this->belongsToMany(
            Language::class,
            'language_pairs',
            'target_language_id',
            'source_language_id'
        )->withPivot('is_active')->withTimestamps();
    }

    /**
     * Get languages that this language can be translated to.
     */
    public function targetLanguages(): BelongsToMany
    {
        return $this->belongsToMany(
            Language::class,
            'language_pairs',
            'source_language_id',
            'target_language_id'
        )->withPivot('is_active')->withTimestamps();
    }

    /**
     * Get learning paths in this language.
     */
    public function learningPaths(): HasMany
    {
        return $this->hasMany(LearningPath::class);
    }

    /**
     * Get preview data for the language.
     */
    public function getPreviewData(): array
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'name'        => $this->name,
            'native_name' => $this->native_name,
            'is_active'   => $this->is_active,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }

    /**
     * Get a list of valid ISO 639-1 language codes.
     */
    public static function getValidIsoCodes(): array
    {
        return [
            'ab', 'aa', 'af', 'ak', 'sq', 'am', 'ar', 'an', 'hy', 'as', 'av', 'ae', 'ay', 'az', 'bm', 'ba', 'eu', 'be', 'bn',
            'bh', 'bi', 'bs', 'br', 'bg', 'my', 'ca', 'ch', 'ce', 'ny', 'zh', 'cv', 'kw', 'co', 'cr', 'hr', 'cs', 'da', 'dv',
            'nl', 'dz', 'en', 'eo', 'et', 'ee', 'fo', 'fj', 'fi', 'fr', 'ff', 'gl', 'ka', 'de', 'el', 'gn', 'gu', 'ht', 'ha',
            'he', 'hz', 'hi', 'ho', 'hu', 'ia', 'id', 'ie', 'ga', 'ig', 'ik', 'io', 'is', 'it', 'iu', 'ja', 'jv', 'kl', 'kn',
            'kr', 'ks', 'kk', 'km', 'ki', 'rw', 'ky', 'kv', 'kg', 'ko', 'ku', 'kj', 'la', 'lb', 'lg', 'li', 'ln', 'lo', 'lt',
            'lu', 'lv', 'gv', 'mk', 'mg', 'ms', 'ml', 'mt', 'mi', 'mr', 'mh', 'mn', 'na', 'nv', 'nd', 'ne', 'ng', 'nb', 'nn',
            'no', 'ii', 'nr', 'oc', 'oj', 'cu', 'om', 'or', 'os', 'pa', 'pi', 'fa', 'pl', 'ps', 'pt', 'qu', 'rm', 'rn', 'ro',
            'ru', 'sa', 'sc', 'sd', 'se', 'sm', 'sg', 'sr', 'gd', 'sn', 'si', 'sk', 'sl', 'so', 'st', 'es', 'su', 'sw', 'ss',
            'sv', 'ta', 'te', 'tg', 'th', 'ti', 'bo', 'tk', 'tl', 'tn', 'to', 'tr', 'ts', 'tt', 'tw', 'ty', 'ug', 'uk', 'ur',
            'uz', 've', 'vi', 'vo', 'wa', 'cy', 'wo', 'fy', 'xh', 'yi', 'yo', 'za', 'zu',
        ];
    }
}