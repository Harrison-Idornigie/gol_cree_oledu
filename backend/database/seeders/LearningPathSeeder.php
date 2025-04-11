<?php
namespace Database\Seeders;

use App\Models\Language;
use App\Models\LearningPath;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class LearningPathSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Authenticate as the first user for content versioning
        $user = User::first();
        if ($user) {
            Auth::login($user);
        }

        // Get languages
        $english    = Language::where('code', 'en')->first();
        $spanish    = Language::where('code', 'es')->first();
        $plainsCree = Language::where('code', 'crk')->first();

        if (! $english || ! $spanish || ! $plainsCree) {
            $this->command->error('Languages not found. Please run LanguageSeeder first.');
            return;
        }

        // Learning paths are organized in a progression that mimics grade levels 1-12:
        // - Foundation 1-4 (Elementary equivalent)
        // - Developing 1-4 (Middle school equivalent)
        // - Proficient 1-4 (High school equivalent)

        // Create English learning paths
        $this->createLearningPathsForLanguage($english, [
            'foundation-1' => [
                'title'       => 'English Foundations I',
                'description' => 'Begin your English journey with alphabet recognition, basic sounds, and simple vocabulary.',
            ],
            'foundation-2' => [
                'title'       => 'English Foundations II',
                'description' => 'Build on basic English with simple sentences, common phrases, and expanded vocabulary.',
            ],
            'foundation-3' => [
                'title'       => 'English Foundations III',
                'description' => 'Develop reading comprehension with short stories and practice basic conversation skills.',
            ],
            'foundation-4' => [
                'title'       => 'English Foundations IV',
                'description' => 'Complete foundational English with paragraph writing, guided dialogues, and essential grammar.',
            ],
            'developing-1' => [
                'title'       => 'English Development I',
                'description' => 'Expand your English with more complex sentence structures and intermediate vocabulary.',
            ],
            'developing-2' => [
                'title'       => 'English Development II',
                'description' => 'Strengthen your English with paragraph composition, reading comprehension, and conversational fluency.',
            ],
            'developing-3' => [
                'title'       => 'English Development III',
                'description' => 'Enhance your English with essay writing, literary analysis, and presentation skills.',
            ],
            'developing-4' => [
                'title'       => 'English Development IV',
                'description' => 'Master intermediate English with research skills, debate techniques, and cultural contexts.',
            ],
            'proficient-1' => [
                'title'       => 'English Proficiency I',
                'description' => 'Advance your English with complex grammar, academic writing, and professional communication.',
            ],
            'proficient-2' => [
                'title'       => 'English Proficiency II',
                'description' => 'Refine your English with advanced literary analysis, rhetorical techniques, and creative writing.',
            ],
            'proficient-3' => [
                'title'       => 'English Proficiency III',
                'description' => 'Excel in English with critical analysis, research papers, and sophisticated communication strategies.',
            ],
            'proficient-4' => [
                'title'       => 'English Proficiency IV',
                'description' => 'Achieve mastery in English with advanced discourse, cultural nuances, and professional-level communication.',
            ],
        ]);

        // Create Spanish learning paths
        $this->createLearningPathsForLanguage($spanish, [
            'foundation-1' => [
                'title'       => 'Spanish Foundations I',
                'description' => 'Start your Spanish journey with basic pronunciation, greetings, and essential vocabulary.',
            ],
            'foundation-2' => [
                'title'       => 'Spanish Foundations II',
                'description' => 'Build your Spanish skills with simple sentences, common expressions, and basic grammar.',
            ],
            'foundation-3' => [
                'title'       => 'Spanish Foundations III',
                'description' => 'Develop your Spanish with reading comprehension, guided conversations, and expanded vocabulary.',
            ],
            'foundation-4' => [
                'title'       => 'Spanish Foundations IV',
                'description' => 'Complete foundational Spanish with paragraph writing, everyday conversations, and cultural awareness.',
            ],
            'developing-1' => [
                'title'       => 'Spanish Development I',
                'description' => 'Expand your Spanish with intermediate grammar, descriptive writing, and conversational fluency.',
            ],
            'developing-2' => [
                'title'       => 'Spanish Development II',
                'description' => 'Strengthen your Spanish with narrative composition, reading comprehension, and cultural contexts.',
            ],
            'developing-3' => [
                'title'       => 'Spanish Development III',
                'description' => 'Enhance your Spanish with essay writing, literary exploration, and presentation skills.',
            ],
            'developing-4' => [
                'title'       => 'Spanish Development IV',
                'description' => 'Master intermediate Spanish with research projects, debate techniques, and regional variations.',
            ],
            'proficient-1' => [
                'title'       => 'Spanish Proficiency I',
                'description' => 'Advance your Spanish with complex grammar, academic writing, and professional communication.',
            ],
            'proficient-2' => [
                'title'       => 'Spanish Proficiency II',
                'description' => 'Refine your Spanish with advanced literary analysis, rhetorical devices, and creative expression.',
            ],
            'proficient-3' => [
                'title'       => 'Spanish Proficiency III',
                'description' => 'Excel in Spanish with critical analysis, research papers, and sophisticated communication strategies.',
            ],
            'proficient-4' => [
                'title'       => 'Spanish Proficiency IV',
                'description' => 'Achieve mastery in Spanish with advanced discourse, cultural nuances, and professional-level communication.',
            ],
        ]);

        // Create Plains Cree learning paths
        $this->createLearningPathsForLanguage($plainsCree, [
            'foundation-1' => [
                'title'       => 'Plains Cree Foundations I',
                'description' => 'Begin your journey with Plains Cree (nēhiyawēwin) sounds, syllabics, and basic greetings.',
            ],
            'foundation-2' => [
                'title'       => 'Plains Cree Foundations II',
                'description' => 'Build your Plains Cree vocabulary with simple phrases, cultural terms, and basic sentence structure.',
            ],
            'foundation-3' => [
                'title'       => 'Plains Cree Foundations III',
                'description' => 'Develop your Plains Cree with simple conversations, cultural stories, and expanded vocabulary.',
            ],
            'foundation-4' => [
                'title'       => 'Plains Cree Foundations IV',
                'description' => 'Complete foundational Plains Cree with basic writing, everyday conversations, and cultural protocols.',
            ],
            'developing-1' => [
                'title'       => 'Plains Cree Development I',
                'description' => 'Expand your Plains Cree with intermediate grammar, descriptive language, and traditional knowledge.',
            ],
            'developing-2' => [
                'title'       => 'Plains Cree Development II',
                'description' => 'Strengthen your Plains Cree with narrative composition, oral traditions, and cultural practices.',
            ],
            'developing-3' => [
                'title'       => 'Plains Cree Development III',
                'description' => 'Enhance your Plains Cree with traditional storytelling, ceremonial language, and community contexts.',
            ],
            'developing-4' => [
                'title'       => 'Plains Cree Development IV',
                'description' => 'Master intermediate Plains Cree with land-based vocabulary, seasonal teachings, and community engagement.',
            ],
            'proficient-1' => [
                'title'       => 'Plains Cree Proficiency I',
                'description' => 'Advance your Plains Cree with complex grammar, traditional teachings, and ceremonial language.',
            ],
            'proficient-2' => [
                'title'       => 'Plains Cree Proficiency II',
                'description' => 'Refine your Plains Cree with advanced storytelling, medicine teachings, and cultural protocols.',
            ],
            'proficient-3' => [
                'title'       => 'Plains Cree Proficiency III',
                'description' => 'Excel in Plains Cree with elder teachings, spiritual concepts, and traditional ecological knowledge.',
            ],
            'proficient-4' => [
                'title'       => 'Plains Cree Proficiency IV',
                'description' => 'Achieve mastery in Plains Cree with philosophical concepts, ceremonial discourse, and cultural revitalization.',
            ],
        ]);
    }

    /**
     * Create learning paths for a specific language
     */
    private function createLearningPathsForLanguage(Language $language, array $pathsData): void
    {
        foreach ($pathsData as $level => $data) {
            LearningPath::updateOrCreate(
                [
                    'title'       => $data['title'],
                    'language_id' => $language->id,
                ],
                [
                    'description'  => $data['description'],
                    'target_level' => $level,
                    'status'       => 'published',
                ]
            );
        }
    }
}
