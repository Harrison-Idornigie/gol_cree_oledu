<?php

namespace App\Providers;

use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Word;
use App\Models\Tenants\Sentence;
use App\Models\Tenants\Lesson;
use App\Models\Tenants\Exercise;
use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\Unit;
use App\Models\Tenants\Topic;
use App\Models\Tenants\MediaFile;
use App\Models\Tenants\BulkOperation;
use App\Models\Tenants\Review;
use App\Models\Tenants\Progress;
use App\Models\Tenants\Achievement;
use App\Models\Tenants\UserAnalytics;
use App\Models\Tenants\CurriculumTemplate;
use App\Models\Tenants\ContentTemplate;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use App\Policies\WordPolicy;
use App\Policies\SentencePolicy;
use App\Policies\LessonPolicy;
use App\Policies\ExercisePolicy;
use App\Policies\LanguagePolicy;
use App\Policies\LearningPathPolicy;
use App\Policies\UnitPolicy;
use App\Policies\TopicPolicy;
use App\Policies\MediaFilePolicy;
use App\Policies\BulkOperationPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\ProgressPolicy;
use App\Policies\AchievementPolicy;
use App\Policies\AnalyticsPolicy;
use App\Policies\AIIntegrationPolicy;
use App\Policies\SystemHealthPolicy;
use App\Policies\CurriculumTemplatePolicy;
use App\Policies\ContentTemplatePolicy;
use App\Policies\ContentScaffoldingPolicy;
use App\Policies\TenantAnalyticsPolicy;
use App\Policies\TenantAuthPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Tenant and User Management
        Tenant::class => TenantPolicy::class,
        User::class => UserPolicy::class,

        // Content Management
        Word::class => WordPolicy::class,
        Sentence::class => SentencePolicy::class,
        Lesson::class => LessonPolicy::class,
        Exercise::class => ExercisePolicy::class,
        Language::class => LanguagePolicy::class,
        LearningPath::class => LearningPathPolicy::class,
        Unit::class => UnitPolicy::class,
        Topic::class => TopicPolicy::class,

        // Media and Bulk Operations
        MediaFile::class => MediaFilePolicy::class,
        BulkOperation::class => BulkOperationPolicy::class,

        // Content Review System
        Review::class => ReviewPolicy::class,

        // Progress and Achievements
        Progress::class => ProgressPolicy::class,
        Achievement::class => AchievementPolicy::class,

        // Analytics
        UserAnalytics::class => AnalyticsPolicy::class,

        // Template System
        CurriculumTemplate::class => CurriculumTemplatePolicy::class,
        ContentTemplate::class => ContentTemplatePolicy::class,

        // Tenant Admin Policies (for specific functionality, not models)
        'tenant-analytics' => TenantAnalyticsPolicy::class,
        'tenant-auth' => TenantAuthPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
