"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { getUnit } from "@/app/_actions/tenants/student/unit-actions";
import { LoadingSpinner } from "@/components/ui/loading-spinner";

export default function UnitVocabularyRedirect({ 
  params 
}: { 
  params: { unitId: string } 
}) {
  const router = useRouter();
  const unitId = parseInt(params.unitId);

  useEffect(() => {
    async function redirectToNestedPath() {
      try {
        // Get the unit to find its learning path ID
        const unit = await getUnit(unitId);
        
        if (unit && unit.learning_path_id) {
          // Redirect to the nested path
          router.push(`/student/paths/${unit.learning_path_id}/units/${unitId}/vocabulary`);
        } else {
          // If we can't find the learning path, redirect to the learn page
          router.push("/student");
        }
      } catch (error) {
        console.error("Error redirecting:", error);
        router.push("/student");
      }
    }

    redirectToNestedPath();
  }, [unitId, router]);

  return (
    <div className="flex items-center justify-center h-screen">
      <LoadingSpinner />
    </div>
  );
}
