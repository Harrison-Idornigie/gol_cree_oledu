"use client";

import { useState, useEffect } from "react";
import { ArrowLeft, Sparkles } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import Link from "next/link";
import { LoadingSpinner } from "@/components/ui/loading-spinner";
import VocabularyReview from "@/components/learn/VocabularyReview";
import {
  getVocabularyReviewItems,
  getUnitVocabulary,
  getMistakeVocabularyItems,
} from "@/app/_actions/user/vocabulary-actions";
import { VocabularyItem } from "@/types/vocabulary";
import { getUnit } from "@/app/_actions/user/unit-actions";

export default function UnitVocabularyReviewPage({
  params,
}: {
  params: { pathId: string; unitId: string };
}) {
  const unitId = parseInt(params.unitId);
  const pathId = parseInt(params.pathId);
  const [activeTab, setActiveTab] = useState<string>("unit");
  const [vocabularyItems, setVocabularyItems] = useState<VocabularyItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [unitName, setUnitName] = useState<string>("");

  useEffect(() => {
    async function loadUnitData() {
      try {
        const unit = await getUnit(unitId);
        if (unit) {
          setUnitName(unit.title);
        }
      } catch (error) {
        console.error("Error loading unit data:", error);
      }
    }

    loadUnitData();
  }, [unitId]);

  useEffect(() => {
    async function loadVocabularyItems() {
      try {
        setIsLoading(true);
        setError(null);

        let items: VocabularyItem[] = [];

        if (activeTab === "unit") {
          // Get vocabulary for this specific unit
          items = await getUnitVocabulary(unitId);
        } else if (activeTab === "mistakes") {
          // Get vocabulary items the user has struggled with in this unit
          items = await getMistakeVocabularyItems(10, {
            unitId: unitId,
          });
        } else {
          // Get vocabulary items due for review in this unit
          items = await getVocabularyReviewItems(10, {
            unitId: unitId,
            reviewType: activeTab as "due" | "mistakes" | "all",
          });
        }

        setVocabularyItems(items);
        setIsLoading(false);
      } catch (error) {
        console.error("Error loading vocabulary items:", error);
        setError("Failed to load vocabulary items. Please try again later.");
        setIsLoading(false);
      }
    }

    loadVocabularyItems();
  }, [activeTab, unitId]);

  return (
    <div className="container px-4 py-6 md:px-6 md:py-8">
      <div className="mb-6">
        <Button variant="ghost" size="sm" className="mb-4" asChild>
          <Link href={`/learn/paths/${pathId}/units/${unitId}`}>
            <ArrowLeft className="w-4 h-4 mr-2" />
            Back to Unit
          </Link>
        </Button>
        <h1 className="text-3xl font-bold">Unit Vocabulary</h1>
        <p className="mt-2 text-muted-foreground">
          {unitName
            ? `Practice vocabulary from "${unitName}"`
            : "Practice unit vocabulary with spaced repetition"}
        </p>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
        <TabsList className="grid w-full grid-cols-3 mb-6">
          <TabsTrigger value="unit">All Unit Words</TabsTrigger>
          <TabsTrigger value="due">Due for Review</TabsTrigger>
          <TabsTrigger value="mistakes">Practice Mistakes</TabsTrigger>
        </TabsList>

        <TabsContent value={activeTab} className="mt-0">
          {isLoading ? (
            <div className="flex items-center justify-center h-64">
              <LoadingSpinner />
            </div>
          ) : error ? (
            <Card className="w-full">
              <CardContent className="p-6 text-center">
                <p className="mb-4 text-red-500">{error}</p>
                <Button onClick={() => window.location.reload()}>
                  Try Again
                </Button>
              </CardContent>
            </Card>
          ) : vocabularyItems.length === 0 ? (
            <Card className="w-full">
              <CardContent className="p-6 text-center">
                <div className="flex justify-center mb-4">
                  <div className="flex items-center justify-center w-16 h-16 rounded-full bg-primary/10">
                    <Sparkles className="w-8 h-8 text-primary" />
                  </div>
                </div>
                <h2 className="mb-2 text-xl font-semibold">
                  No vocabulary items to review
                </h2>
                <p className="mb-4 text-muted-foreground">
                  {activeTab === "unit" &&
                    "This unit doesn't have any vocabulary items yet."}
                  {activeTab === "due" &&
                    "You don't have any vocabulary items due for review in this unit."}
                  {activeTab === "mistakes" &&
                    "You haven't made any mistakes with vocabulary in this unit yet."}
                </p>
                <Button asChild>
                  <Link href={`/learn/paths/${pathId}/units/${unitId}`}>
                    Return to Unit
                  </Link>
                </Button>
              </CardContent>
            </Card>
          ) : (
            <VocabularyReview
              vocabularyItems={vocabularyItems}
              unitId={unitId}
            />
          )}
        </TabsContent>
      </Tabs>
    </div>
  );
}
