# Language Learning App API

A comprehensive API for a language learning platform built with Laravel.

## Features

- Learning paths with sequential progression
- Units, lessons, and sections organization
- Various exercise types (multiple choice, fill in blanks, matching, etc.)
- Progress tracking system
- Vocabulary management
- Guide book entries for additional resources

## API Endpoints

### Public Routes

#### Learning Paths
- `GET /api/learning-paths` - List all learning paths
- `GET /api/learning-paths/{id}` - Get a specific learning path
- `GET /api/learning-paths/level/{level}` - Get learning paths by level

#### Units
- `GET /api/learning-paths/{pathId}/units` - List units in a learning path
- `GET /api/units/{id}` - Get a specific unit

#### Lessons
- `GET /api/units/{unitId}/lessons` - List lessons in a unit
- `GET /api/lessons/{id}` - Get a specific lesson

#### Sections
- `GET /api/lessons/{lessonId}/sections` - List sections in a lesson
- `GET /api/sections/{id}` - Get a specific section

### Protected Routes (Requires Authentication)

#### Learning Path Management
- `POST /api/learning-paths` - Create a new learning path
- `PUT /api/learning-paths/{id}` - Update a learning path
- `DELETE /api/learning-paths/{id}` - Delete a learning path
- `PATCH /api/learning-paths/{id}/status` - Update learning path status

#### Unit Management
- `POST /api/units` - Create a new unit
- `PUT /api/units/{id}` - Update a unit
- `DELETE /api/units/{id}` - Delete a unit
- `POST /api/learning-paths/{pathId}/units/reorder` - Reorder units

#### Lesson Management
- `POST /api/lessons` - Create a new lesson
- `PUT /api/lessons/{id}` - Update a lesson
- `DELETE /api/lessons/{id}` - Delete a lesson
- `POST /api/units/{unitId}/lessons/reorder` - Reorder lessons

#### Section Management
- `POST /api/sections` - Create a new section
- `PUT /api/sections/{id}` - Update a section
- `DELETE /api/sections/{id}` - Delete a section
- `POST /api/lessons/{lessonId}/sections/reorder` - Reorder sections

#### Progress Tracking
- `GET /api/learning-paths/{id}/progress` - Get learning path progress
- `GET /api/units/{id}/progress` - Get unit progress
- `GET /api/lessons/{id}/progress` - Get lesson progress
- `GET /api/sections/{id}/progress` - Get section progress

## Data Models

### LearningPath
- id
- title
- description
- target_level
- status (draft, published, archived)

### Unit
- id
- learning_path_id
- title
- description
- order

### Lesson
- id
- unit_id
- title
- description
- order

### Section
- id
- lesson_id
- title
- content
- order

### Exercise
- id
- section_id
- type (multiple_choice, fill_blank, matching, writing, speaking)
- content (JSON)
- answers (JSON)
- order

### VocabularyItem
- id
- lesson_id
- word
- translation
- example

### GuideBookEntry
- id
- unit_id
- topic
- content

### UserProgress
- id
- user_id
- trackable_id
- trackable_type
- status
- meta_data (JSON)

## Progress Tracking

The system uses polymorphic relationships to track user progress across different types of content:
- Learning paths
- Units
- Lessons
- Sections
- Exercises

Progress statuses:
- not_started
- in_progress
- completed
- failed

## Exercise Types

1. Multiple Choice
   - Questions with multiple options
   - Single correct answer

2. Fill in the Blanks
   - Text with missing words
   - Case-insensitive answer checking

3. Matching
   - Pairs of related items
   - All pairs must match correctly

4. Writing
   - Free-form text input
   - Requires manual review

5. Speaking
   - Audio recording
   - Requires manual review

## Authorization

The API uses Laravel Sanctum for authentication and implements role-based access control:
- Public access to learning content
- Protected routes for user progress
- Admin routes for content management
