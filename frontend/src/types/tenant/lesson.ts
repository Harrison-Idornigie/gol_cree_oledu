import { Section } from './section';
import { Quiz } from './quiz';

export interface Lesson {
  id: number;
  unit_id: number;
  title: string;
  slug: string;
  description: string;
  order: number;
  is_published: boolean;
  estimated_time: number;
  xp_reward: number;
  difficulty_level: string;
  sections: Section[];
   created_at: string;
  updated_at: string;
}

export interface LessonFormData {
  id?: number;
  unit_id: number;
  title: string;
  description: string;
  order: number;
  is_published: boolean;
  estimated_time: number;
  xp_reward: number;
  difficulty_level: string;
}

export const DEFAULT_LESSON_VALUES: LessonFormData = {
  unit_id: 0,
  title: '',
  description: '',
  order: 0,
  is_published: false,
  estimated_time: 10,
  xp_reward: 10,
  difficulty_level: 'beginner',
};
