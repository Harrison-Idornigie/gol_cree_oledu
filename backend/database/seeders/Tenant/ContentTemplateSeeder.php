<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\ContentTemplate;
use App\Models\Tenants\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class ContentTemplateSeeder extends Seeder
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

        // Create different types of content templates
        $this->createExerciseTemplates();
        $this->createLessonTemplates();
        $this->createTopicTemplates();
        $this->createUnitTemplates();

        $this->command->info('Content templates created successfully!');
    }

    /**
     * Create exercise templates for different exercise types
     */
    private function createExerciseTemplates(): void
    {
        $exerciseTemplates = [
            // Multiple Choice Templates
            [
                'template_type' => ContentTemplate::TYPE_EXERCISE,
                'name' => 'Basic Multiple Choice',
                'description' => 'Standard multiple choice exercise with 4 options for guidebook and grammar practice.',
                'difficulty_level' => 1,
                'skill_focus' => ContentTemplate::SKILL_VOCABULARY,
                'exercise_types' => ['multiple_choice'],
                'guidebook_requirements' => [
                    'min_words' => 5,
                    'max_words' => 10,
                    'difficulty_level' => 'beginner'
                ],
                'template_data' => [
                    'exercise_type' => 'multiple_choice',
                    'content_structure' => [
                        'question_format' => 'text_with_audio',
                        'options_count' => 4,
                        'correct_answers' => 1,
                        'randomize_options' => true,
                        'show_images' => true,
                        'audio_support' => true
                    ],
                    'scoring' => [
                        'points_correct' => 10,
                        'points_incorrect' => 0,
                        'allow_retry' => true
                    ]
                ]
            ],
            [
                'template_type' => ContentTemplate::TYPE_EXERCISE,
                'name' => 'Advanced Multiple Choice',
                'description' => 'Complex multiple choice exercise with distractors for advanced learners.',
                'difficulty_level' => 4,
                'skill_focus' => ContentTemplate::SKILL_GRAMMAR,
                'exercise_types' => ['multiple_choice'],
                'guidebook_requirements' => [
                    'min_words' => 10,
                    'max_words' => 20,
                    'difficulty_level' => 'advanced'
                ],
                'template_data' => [
                    'exercise_type' => 'multiple_choice',
                    'content_structure' => [
                        'question_format' => 'context_based',
                        'options_count' => 4,
                        'correct_answers' => 1,
                        'randomize_options' => true,
                        'show_context' => true,
                        'distractor_quality' => 'high'
                    ]
                ]
            ],

            // Fill-in-the-Blank Templates
            [
                'template_type' => ContentTemplate::TYPE_EXERCISE,
                'name' => 'Basic Fill-in-the-Blank',
                'description' => 'Simple fill-in-the-blank exercise for guidebook practice.',
                'difficulty_level' => 2,
                'skill_focus' => ContentTemplate::SKILL_VOCABULARY,
                'exercise_types' => ['fill_in_blank'],
                'guidebook_requirements' => [
                    'min_words' => 3,
                    'max_words' => 8,
                    'difficulty_level' => 'beginner'
                ],
                'template_data' => [
                    'exercise_type' => 'fill_in_blank',
                    'content_structure' => [
                        'blank_type' => 'single_word',
                        'hints_provided' => true,
                        'case_sensitive' => false,
                        'accept_synonyms' => true,
                        'audio_support' => true
                    ]
                ]
            ],
            [
                'template_type' => ContentTemplate::TYPE_EXERCISE,
                'name' => 'Grammar Fill-in-the-Blank',
                'description' => 'Grammar-focused fill-in-the-blank exercise with conjugations and forms.',
                'difficulty_level' => 3,
                'skill_focus' => ContentTemplate::SKILL_GRAMMAR,
                'exercise_types' => ['fill_in_blank'],
                'guidebook_requirements' => [
                    'min_words' => 5,
                    'max_words' => 12,
                    'difficulty_level' => 'intermediate'
                ],
                'template_data' => [
                    'exercise_type' => 'fill_in_blank',
                    'content_structure' => [
                        'blank_type' => 'grammatical_form',
                        'hints_provided' => false,
                        'case_sensitive' => true,
                        'accept_synonyms' => false,
                        'grammar_focus' => true
                    ]
                ]
            ],

            // Matching Templates
            [
                'template_type' => ContentTemplate::TYPE_EXERCISE,
                'name' => 'Word-Image Matching',
                'description' => 'Match words with corresponding images for guidebook building.',
                'difficulty_level' => 1,
                'skill_focus' => ContentTemplate::SKILL_VOCABULARY,
                'exercise_types' => ['matching'],
                'guidebook_requirements' => [
                    'min_words' => 4,
                    'max_words' => 8,
                    'difficulty_level' => 'beginner'
                ],
                'template_data' => [
                    'exercise_type' => 'matching',
                    'content_structure' => [
                        'match_type' => 'word_to_image',
                        'pairs_count' => 6,
                        'randomize_order' => true,
                        'show_all_options' => true,
                        'visual_feedback' => true
                    ]
                ]
            ],
            [
                'template_type' => ContentTemplate::TYPE_EXERCISE,
                'name' => 'Translation Matching',
                'description' => 'Match words or phrases with their translations.',
                'difficulty_level' => 2,
                'skill_focus' => ContentTemplate::SKILL_VOCABULARY,
                'exercise_types' => ['matching'],
                'guidebook_requirements' => [
                    'min_words' => 6,
                    'max_words' => 12,
                    'difficulty_level' => 'intermediate'
                ],
                'template_data' => [
                    'exercise_type' => 'matching',
                    'content_structure' => [
                        'match_type' => 'translation',
                        'pairs_count' => 8,
                        'randomize_order' => true,
                        'show_all_options' => false,
                        'audio_support' => true
                    ]
                ]
            ],

            // Listening Templates
            [
                'template_type' => ContentTemplate::TYPE_EXERCISE,
                'name' => 'Basic Listening Comprehension',
                'description' => 'Simple listening exercise with audio clips and comprehension questions.',
                'difficulty_level' => 2,
                'skill_focus' => ContentTemplate::SKILL_LISTENING,
                'exercise_types' => ['listening'],
                'guidebook_requirements' => [
                    'min_words' => 8,
                    'max_words' => 15,
                    'difficulty_level' => 'beginner'
                ],
                'template_data' => [
                    'exercise_type' => 'listening',
                    'content_structure' => [
                        'audio_length' => 'short',
                        'playback_controls' => true,
                        'transcript_available' => true,
                        'question_types' => ['multiple_choice', 'true_false'],
                        'native_speaker' => true
                    ]
                ]
            ],

            // Conversation Templates
            [
                'template_type' => ContentTemplate::TYPE_EXERCISE,
                'name' => 'Guided Conversation',
                'description' => 'Structured conversation practice with prompts and feedback.',
                'difficulty_level' => 3,
                'skill_focus' => ContentTemplate::SKILL_SPEAKING,
                'exercise_types' => ['conversation'],
                'guidebook_requirements' => [
                    'min_words' => 10,
                    'max_words' => 20,
                    'difficulty_level' => 'intermediate'
                ],
                'template_data' => [
                    'exercise_type' => 'conversation',
                    'content_structure' => [
                        'conversation_type' => 'guided',
                        'turns_count' => 6,
                        'prompts_provided' => true,
                        'recording_required' => true,
                        'peer_interaction' => false
                    ]
                ]
            ]
        ];

        foreach ($exerciseTemplates as $template) {
            ContentTemplate::updateOrCreate(
                [
                    'template_type' => $template['template_type'],
                    'name' => $template['name'],
                ],
                array_merge($template, [
                    'created_by' => auth()->id(),
                    'usage_count' => 0,
                ])
            );
        }
    }

    /**
     * Create lesson templates for different lesson structures
     */
    private function createLessonTemplates(): void
    {
        $lessonTemplates = [
            [
                'template_type' => ContentTemplate::TYPE_LESSON,
                'name' => 'Guidebook Introduction Lesson',
                'description' => 'Standard lesson template for introducing new guidebook with multiple exercise types.',
                'difficulty_level' => 1,
                'skill_focus' => ContentTemplate::SKILL_VOCABULARY,
                'exercise_types' => ['multiple_choice', 'matching', 'fill_in_blank'],
                'guidebook_requirements' => [
                    'min_words' => 8,
                    'max_words' => 12,
                    'difficulty_level' => 'beginner'
                ],
                'template_data' => [
                    'title' => 'New Guidebook',
                    'lesson_structure' => [
                        'introduction' => [
                            'duration_minutes' => 5,
                            'content_type' => 'guidebook_presentation',
                            'includes_audio' => true,
                            'includes_images' => true
                        ],
                        'practice' => [
                            'duration_minutes' => 15,
                            'content_type' => 'guided_practice'
                        ],
                        'assessment' => [
                            'duration_minutes' => 10,
                            'content_type' => 'formative_assessment'
                        ]
                    ],
                    'exercise_patterns' => [
                        ['type' => 'multiple_choice', 'count' => 3],
                        ['type' => 'matching', 'count' => 2],
                        ['type' => 'fill_in_blank', 'count' => 2]
                    ]
                ]
            ],
            [
                'template_type' => ContentTemplate::TYPE_LESSON,
                'name' => 'Grammar Focus Lesson',
                'description' => 'Lesson template focused on grammar instruction with scaffolded practice.',
                'difficulty_level' => 3,
                'skill_focus' => ContentTemplate::SKILL_GRAMMAR,
                'exercise_types' => ['fill_in_blank', 'multiple_choice', 'conversation'],
                'guidebook_requirements' => [
                    'min_words' => 10,
                    'max_words' => 15,
                    'difficulty_level' => 'intermediate'
                ],
                'template_data' => [
                    'title' => 'Grammar Focus',
                    'lesson_structure' => [
                        'explanation' => [
                            'duration_minutes' => 8,
                            'content_type' => 'grammar_explanation',
                            'includes_examples' => true
                        ],
                        'guided_practice' => [
                            'duration_minutes' => 12,
                            'content_type' => 'scaffolded_practice'
                        ],
                        'independent_practice' => [
                            'duration_minutes' => 15,
                            'content_type' => 'independent_practice'
                        ]
                    ],
                    'exercise_patterns' => [
                        ['type' => 'fill_in_blank', 'count' => 4],
                        ['type' => 'multiple_choice', 'count' => 3],
                        ['type' => 'conversation', 'count' => 1]
                    ]
                ]
            ],
            [
                'template_type' => ContentTemplate::TYPE_LESSON,
                'name' => 'Cultural Context Lesson',
                'description' => 'Lesson template integrating cultural content with language learning.',
                'difficulty_level' => 2,
                'skill_focus' => ContentTemplate::SKILL_CONVERSATION,
                'exercise_types' => ['conversation', 'listening', 'multiple_choice'],
                'guidebook_requirements' => [
                    'min_words' => 12,
                    'max_words' => 18,
                    'difficulty_level' => 'intermediate'
                ],
                'template_data' => [
                    'title' => 'Cultural Context',
                    'lesson_structure' => [
                        'cultural_introduction' => [
                            'duration_minutes' => 10,
                            'content_type' => 'cultural_presentation',
                            'includes_media' => true
                        ],
                        'language_practice' => [
                            'duration_minutes' => 20,
                            'content_type' => 'contextual_practice'
                        ]
                    ],
                    'exercise_patterns' => [
                        ['type' => 'listening', 'count' => 2],
                        ['type' => 'conversation', 'count' => 2],
                        ['type' => 'multiple_choice', 'count' => 2]
                    ],
                    'cultural_components' => true
                ]
            ]
        ];

        foreach ($lessonTemplates as $template) {
            ContentTemplate::updateOrCreate(
                [
                    'template_type' => $template['template_type'],
                    'name' => $template['name'],
                ],
                array_merge($template, [
                    'created_by' => auth()->id(),
                    'usage_count' => 0,
                ])
            );
        }
    }

    /**
     * Create topic templates for organizing lessons
     */
    private function createTopicTemplates(): void
    {
        $topicTemplates = [
            [
                'template_type' => ContentTemplate::TYPE_TOPIC,
                'name' => 'Guidebook Topic',
                'description' => 'Topic template for organizing guidebook-focused lessons around themes.',
                'difficulty_level' => 1,
                'skill_focus' => ContentTemplate::SKILL_VOCABULARY,
                'exercise_types' => ['multiple_choice', 'matching', 'fill_in_blank', 'listening'],
                'guidebook_requirements' => [
                    'min_words' => 20,
                    'max_words' => 30,
                    'difficulty_level' => 'beginner'
                ],
                'template_data' => [
                    'title' => 'Thematic Guidebook',
                    'topic_structure' => [
                        'introduction_lesson' => [
                            'type' => 'guidebook_introduction',
                            'estimated_duration' => 30
                        ],
                        'practice_lessons' => [
                            'count' => 3,
                            'type' => 'guidebook_practice',
                            'estimated_duration' => 25
                        ],
                        'assessment_lesson' => [
                            'type' => 'guidebook_assessment',
                            'estimated_duration' => 20
                        ]
                    ],
                    'lesson_patterns' => [
                        ['type' => 'guidebook_introduction', 'count' => 1],
                        ['type' => 'guidebook_practice', 'count' => 3],
                        ['type' => 'guidebook_assessment', 'count' => 1]
                    ]
                ]
            ],
            [
                'template_type' => ContentTemplate::TYPE_TOPIC,
                'name' => 'Grammar Topic',
                'description' => 'Topic template for systematic grammar instruction and practice.',
                'difficulty_level' => 3,
                'skill_focus' => ContentTemplate::SKILL_GRAMMAR,
                'exercise_types' => ['fill_in_blank', 'multiple_choice', 'conversation'],
                'guidebook_requirements' => [
                    'min_words' => 15,
                    'max_words' => 25,
                    'difficulty_level' => 'intermediate'
                ],
                'template_data' => [
                    'title' => 'Grammar Focus',
                    'topic_structure' => [
                        'explanation_lesson' => [
                            'type' => 'grammar_explanation',
                            'estimated_duration' => 35
                        ],
                        'practice_lessons' => [
                            'count' => 4,
                            'type' => 'grammar_practice',
                            'estimated_duration' => 30
                        ]
                    ],
                    'lesson_patterns' => [
                        ['type' => 'grammar_explanation', 'count' => 1],
                        ['type' => 'grammar_practice', 'count' => 4]
                    ]
                ]
            ]
        ];

        foreach ($topicTemplates as $template) {
            ContentTemplate::updateOrCreate(
                [
                    'template_type' => $template['template_type'],
                    'name' => $template['name'],
                ],
                array_merge($template, [
                    'created_by' => auth()->id(),
                    'usage_count' => 0,
                ])
            );
        }
    }

    /**
     * Create unit templates for organizing topics
     */
    private function createUnitTemplates(): void
    {
        $unitTemplates = [
            [
                'template_type' => ContentTemplate::TYPE_UNIT,
                'name' => 'Beginner Unit',
                'description' => 'Unit template for beginner-level content with foundational skills.',
                'difficulty_level' => 1,
                'skill_focus' => ContentTemplate::SKILL_VOCABULARY,
                'exercise_types' => ['multiple_choice', 'matching', 'fill_in_blank', 'listening'],
                'guidebook_requirements' => [
                    'min_words' => 40,
                    'max_words' => 60,
                    'difficulty_level' => 'beginner'
                ],
                'template_data' => [
                    'title' => 'Foundation Unit',
                    'unit_structure' => [
                        'introduction_topic' => [
                            'type' => 'guidebook_introduction',
                            'estimated_duration' => 120,
                            'lesson_count' => 4
                        ],
                        'practice_topics' => [
                            'count' => 2,
                            'type' => 'mixed_practice',
                            'estimated_duration' => 100,
                            'lesson_count' => 3
                        ],
                        'assessment_topic' => [
                            'type' => 'unit_assessment',
                            'estimated_duration' => 60,
                            'lesson_count' => 2
                        ]
                    ],
                    'topic_patterns' => [
                        ['type' => 'guidebook_introduction', 'count' => 1],
                        ['type' => 'mixed_practice', 'count' => 2],
                        ['type' => 'unit_assessment', 'count' => 1]
                    ]
                ]
            ],
            [
                'template_type' => ContentTemplate::TYPE_UNIT,
                'name' => 'Intermediate Unit',
                'description' => 'Unit template for intermediate-level content with integrated skills.',
                'difficulty_level' => 3,
                'skill_focus' => ContentTemplate::SKILL_CONVERSATION,
                'exercise_types' => ['conversation', 'listening', 'fill_in_blank', 'multiple_choice'],
                'guidebook_requirements' => [
                    'min_words' => 60,
                    'max_words' => 80,
                    'difficulty_level' => 'intermediate'
                ],
                'template_data' => [
                    'title' => 'Integrated Skills Unit',
                    'unit_structure' => [
                        'skills_topics' => [
                            'count' => 3,
                            'type' => 'integrated_skills',
                            'estimated_duration' => 150,
                            'lesson_count' => 5
                        ],
                        'project_topic' => [
                            'type' => 'culminating_project',
                            'estimated_duration' => 90,
                            'lesson_count' => 3
                        ]
                    ],
                    'topic_patterns' => [
                        ['type' => 'integrated_skills', 'count' => 3],
                        ['type' => 'culminating_project', 'count' => 1]
                    ]
                ]
            ],
            [
                'template_type' => ContentTemplate::TYPE_UNIT,
                'name' => 'Cultural Immersion Unit',
                'description' => 'Unit template focusing on cultural content and authentic materials.',
                'difficulty_level' => 2,
                'skill_focus' => ContentTemplate::SKILL_READING,
                'exercise_types' => ['listening', 'conversation', 'reading', 'multiple_choice'],
                'guidebook_requirements' => [
                    'min_words' => 50,
                    'max_words' => 70,
                    'difficulty_level' => 'intermediate'
                ],
                'template_data' => [
                    'title' => 'Cultural Context Unit',
                    'unit_structure' => [
                        'cultural_topics' => [
                            'count' => 4,
                            'type' => 'cultural_exploration',
                            'estimated_duration' => 120,
                            'lesson_count' => 4
                        ]
                    ],
                    'topic_patterns' => [
                        ['type' => 'cultural_exploration', 'count' => 4]
                    ],
                    'cultural_components' => true,
                    'authentic_materials' => true
                ]
            ]
        ];

        foreach ($unitTemplates as $template) {
            ContentTemplate::updateOrCreate(
                [
                    'template_type' => $template['template_type'],
                    'name' => $template['name'],
                ],
                array_merge($template, [
                    'created_by' => auth()->id(),
                    'usage_count' => 0,
                ])
            );
        }
    }
}
