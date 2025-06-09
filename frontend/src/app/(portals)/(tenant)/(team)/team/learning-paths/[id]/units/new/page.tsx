import UnitForm from '@/components/admin/forms/UnitForm';

export default function NewUnit({ params }: { params: { id: string } }) {
  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold tracking-tight">Create Unit</h2>
        <p className="text-muted-foreground">
          Add a new unit to the learning path
        </p>
      </div>

      <UnitForm learningPathId={parseInt(params.id)} />
    </div>
  );
}