import LearningPathForm from '@/components/admin/forms/LearningPathForm';

export default function NewLearningPath() {
  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold tracking-tight">Create Learning Path</h2>
        <p className="text-muted-foreground">
          Create a new learning path for your students
        </p>
      </div>

      <LearningPathForm />
    </div>
  );
}