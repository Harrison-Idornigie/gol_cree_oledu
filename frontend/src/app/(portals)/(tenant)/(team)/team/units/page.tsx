'use client';

import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { useEffect, useState } from "react";
import { getUnits, deleteUnit, toggleUnitStatus } from "@/app/_actions/admin/unit-actions";
import { useRouter } from "next/navigation";
import { AlertDialog } from "@/components/admin/AlertDialog";
import Link from "next/link";
import { toast } from "sonner";

interface Unit {
  id: number;
  title: string;
  description: string;
  order: number;
  estimated_duration: number;
  is_published: boolean;
  learning_path_id: number;
}

export default function UnitsPage() {
  const router = useRouter();
  const [units, setUnits] = useState<Unit[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadUnits();
  }, []);

  const loadUnits = async () => {
    const result = await getUnits();
    if (result.error) {
      setError(result.error);
    } else {
      setUnits(result.data || []);
    }
    setLoading(false);
  };

  const handleToggleStatus = async (id: number) => {
    const result = await toggleUnitStatus(id);
    if (result.error) {
      toast.error("Error", {
        description: result.error,
      });
    } else {
      setUnits(units.map(unit => 
        unit.id === id ? { ...unit, is_published: !unit.is_published } : unit
      ));
      toast.success("Success", {
        description: "Unit status updated successfully",
      });
    }
  };

  const handleDelete = async (id: number) => {
    const result = await deleteUnit(id);
    if (result.error) {
      toast.error("Error", {
        description: result.error,
      });
    } else {
      setUnits(units.filter(unit => unit.id !== id));
      toast.success("Success", {
        description: "Unit deleted successfully",
      });
      router.refresh();
    }
  };

  if (loading) {
    return <div className="flex justify-center items-center h-48">Loading...</div>;
  }

  if (error) {
    return (
      <div className="text-red-500 p-4 rounded-md bg-red-50 mb-4">
        Error: {error}
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">Units</h2>
          <p className="text-muted-foreground">
            Manage all units across learning paths
          </p>
        </div>
      </div>

      <div className="grid gap-4">
        {units.map((unit) => (
          <Card key={unit.id} className="p-6">
            <div className="flex items-start justify-between">
              <div className="space-y-1">
                <div className="flex items-center gap-2">
                  <Link href={`/admin/units/${unit.id}/view`}>
                    <h3 className="font-semibold hover:underline">
                      {unit.title}
                    </h3>
                  </Link>
                  <span
                    className={`inline-block px-2 py-1 text-xs rounded-full ${
                      unit.is_published
                        ? 'bg-green-100 text-green-700'
                        : 'bg-yellow-100 text-yellow-700'
                    }`}
                  >
                    {unit.is_published ? 'Published' : 'Draft'}
                  </span>
                </div>
                <p className="text-sm text-muted-foreground">
                  {unit.description}
                </p>
                <div className="flex gap-2 text-sm text-muted-foreground">
                  <span>Order: {unit.order}</span>
                  <span>•</span>
                  <span>Duration: {unit.estimated_duration} hours</span>
                </div>
              </div>
              <div className="flex gap-2">
                <Button
                  variant={unit.is_published ? "outline" : "default"}
                  onClick={() => handleToggleStatus(unit.id)}
                >
                  {unit.is_published ? "Unpublish" : "Publish"}
                </Button>
                <Link href={`/admin/units/${unit.id}/view`}>
                  <Button variant="outline">View</Button>
                </Link>
                <Link href={`/admin/units/${unit.id}`}>
                  <Button variant="outline">Edit</Button>
                </Link>
                <AlertDialog
                  trigger={
                    <Button variant="outline" className="text-red-600 hover:text-red-700">
                      Delete
                    </Button>
                  }
                  title="Delete Unit"
                  description="Are you sure you want to delete this unit? This action cannot be undone and will remove all associated lessons."
                  confirmText="Delete"
                  cancelText="Cancel"
                  variant="destructive"
                  onConfirm={() => handleDelete(unit.id)}
                />
              </div>
            </div>
          </Card>
        ))}

        {units.length === 0 && (
          <Card className="p-6">
            <div className="text-center text-muted-foreground">
              No units found.
            </div>
          </Card>
        )}
      </div>
    </div>
  );
}