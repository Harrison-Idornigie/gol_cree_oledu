"use client";

import { useState, useEffect } from "react";
import { useSearchParams } from "next/navigation";
import { ArrowLeft, Sparkles } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import Link from "next/link";
import { LoadingSpinner } from "@/components/ui/loading-spinner";
import VocabularyReview from "@/components/tenants/students/VocabularyReview";
import {
  getVocabularyReviewItems,
  getUnitVocabulary,
  getMistakeVocabularyItems,
} from "@/app/_actions/user/vocabulary-actions";
import { VocabularyItem } from "@/types/vocabulary";

export default function VocabularyReviewPage() {
  const searchParams = useSearchParams();
  const languageId = searchParams.get("language");
  const unitId = searchParams.get("unit");
  const reviewType = searchParams.get("type") || "due";

  const [activeTab, setActiveTab] = useState<string>(reviewType);
  const [vocabularyItems, setVocabularyItems] = useState<VocabularyItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    async function loadVocabularyItems() {
      try {
        setIsLoading(true);
        setError(null);

        let items: VocabularyItem[] = [];

        if (activeTab === "unit" && unitId) {
          // Get vocabulary for a specific unit
          items = await getUnitVocabulary(parseInt(unitId));
        } else if (activeTab === "mistakes") {
          // Get vocabulary items the user has struggled with
          items = await getMistakeVocabularyItems(10, {
            unitId: unitId ? parseInt(unitId) : undefined,
            languageId: languageId ? parseInt(languageId) : undefined,
          });
        } else {
          // Get vocabulary items due for review
          items = await getVocabularyReviewItems(10, {
            unitId: unitId ? parseInt(unitId) : undefined,
            languageId: languageId ? parseInt(languageId) : undefined,
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
  }, [activeTab, unitId, languageId]);

  return (
    <div className="container px-4 py-6 md:px-6 md:py-8">
      <div className="mb-6">
        <Button variant="ghost" size="sm" className="mb-4" asChild>
          <Link
            href={
              languageId ? `/learn/languages/${languageId}/dashboard` : "/learn"
            }
          >
            <ArrowLeft className="w-4 h-4 mr-2" />
            Back to Dashboard
          </Link>
        </Button>
        <h1 className="text-3xl font-bold">Vocabulary Review</h1>
        <p className="mt-2 text-muted-foreground">
          Practice your vocabulary with spaced repetition to improve retention.
        </p>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
        <TabsList className="grid w-full grid-cols-3 mb-6">
          <TabsTrigger value="due">Due for Review</TabsTrigger>
          <TabsTrigger value="mistakes">Practice Mistakes</TabsTrigger>
          {unitId && <TabsTrigger value="unit">Unit Vocabulary</TabsTrigger>}
          {!unitId && <TabsTrigger value="all">All Vocabulary</TabsTrigger>}
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
                  {activeTab === "due" &&
                    "You don't have any vocabulary items due for review."}
                  {activeTab === "mistakes" &&
                    "You haven't made any mistakes yet. Keep practicing!"}
                  {activeTab === "unit" &&
                    "This unit doesn't have any vocabulary items."}
                  {activeTab === "all" &&
                    "You haven't learned any vocabulary items yet."}
                </p>
                <Button asChild>
                  <Link href="/learn">Explore Learning Paths</Link>
                </Button>
              </CardContent>
            </Card>
          ) : (
            <VocabularyReview
              vocabularyItems={vocabularyItems}
              unitId={unitId ? parseInt(unitId) : undefined}
              languageId={languageId ? parseInt(languageId) : undefined}
            />
          )}
        </TabsContent>
      </Tabs>
    </div>
  );
}
