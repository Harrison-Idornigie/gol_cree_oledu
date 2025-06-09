"use client";

import { useRouter } from "next/navigation";
import { Tooltip } from "@/components/ui/tooltip";

interface Lesson {
  id: number;
  title: string;
  description: string;
  completed: boolean;
  xp: number;
}

interface UnitModalProps {
  unit: {
    id: number;
    name: string;
    description: string;
    level: number;
    difficulty: "beginner" | "intermediate" | "advanced";
  };
  lessons: Lesson[];
  onClose: () => void;
  isOpen: boolean;
}

export default function UnitModal({
  unit,
  lessons,
  onClose,
  isOpen,
}: UnitModalProps) {
  const router = useRouter();

  if (!isOpen) return null;

  const getDifficultyColor = (
    difficulty: "beginner" | "intermediate" | "advanced"
  ) => {
    switch (difficulty) {
      case "beginner":
        return "var(--duo-green)";
      case "intermediate":
        return "var(--duo-blue)";
      case "advanced":
        return "var(--duo-purple)";
    }
  };

  const isLessonLocked = (lesson: Lesson) => {
    if (lesson.completed) return false;
    // Check if any previous lesson is incomplete
    return lessons.some((l) => l.id < lesson.id && !l.completed);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div className="w-full max-w-2xl mx-4 overflow-hidden bg-white rounded-xl">
        {/* Header */}
        <div
          className="flex items-center justify-between p-6"
          style={{ backgroundColor: getDifficultyColor(unit.difficulty) }}
        >
          <div className="text-white">
            <h2 className="text-2xl font-bold">{unit.name}</h2>
            <p className="text-white/80">{unit.description}</p>
          </div>
          <button onClick={onClose} className="text-white/80 hover:text-white">
            <span className="material-icons-outlined">close</span>
          </button>
        </div>

        {/* Lessons List */}
        <div className="p-6 max-h-[60vh] overflow-y-auto">
          <div className="space-y-4">
            {lessons.map((lesson) => (
              <button
                key={lesson.id}
                onClick={() =>
                  !isLessonLocked(lesson) &&
                  router.push(`/student/path/${unit.id}/lesson/${lesson.id}`)
                }
                className={`w-full p-4 bg-white border rounded-lg transition-all flex items-center justify-between gap-4 ${
                  isLessonLocked(lesson)
                    ? "opacity-50 cursor-not-allowed"
                    : "hover:border-[var(--duo-green)] hover:shadow-md"
                }`}
                disabled={isLessonLocked(lesson)}
              >
                <div className="flex items-center gap-4">
                  <div
                    className={`w-10 h-10 rounded-full flex items-center justify-center ${
                      lesson.completed
                        ? "bg-[var(--duo-green)] text-white"
                        : isLessonLocked(lesson)
                        ? "bg-gray-200 text-gray-400"
                        : "bg-[var(--duo-teal)] text-white"
                    }`}
                  >
                    {isLessonLocked(lesson) ? (
                      <Tooltip content="Complete previous lessons to unlock">
                        <span className="material-icons-outlined">lock</span>
                      </Tooltip>
                    ) : (
                      <span className="material-icons-outlined">
                        {lesson.completed ? "check" : "play_arrow"}
                      </span>
                    )}
                  </div>
                  <div className="text-left">
                    <div className="font-semibold">{lesson.title}</div>
                    <div className="text-sm text-gray-500">
                      {lesson.description}
                    </div>
                  </div>
                </div>
                <div className="flex items-center gap-1 text-[var(--duo-green)]">
                  <span className="text-sm material-icons-outlined">
                    diamond
                  </span>
                  <span className="text-sm font-bold">{lesson.xp} XP</span>
                </div>
              </button>
            ))}
          </div>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-between p-6 bg-gray-50">
          <div className="flex items-center gap-2">
            <span className="text-yellow-400 material-icons-outlined">
              stars
            </span>
            <span className="font-bold">Level {unit.level}</span>
          </div>
          <button
            onClick={() => router.push(`/student/practice/${unit.id}`)}
            className="duo-button bg-[var(--duo-green)] text-white"
          >
            Practice Unit
          </button>
        </div>
      </div>
    </div>
  );
}
