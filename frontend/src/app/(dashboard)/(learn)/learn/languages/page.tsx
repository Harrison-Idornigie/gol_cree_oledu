"use client";

import { useEffect, useState } from "react";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Globe, BookOpen, Check, Plus, Info } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "@/hooks/use-toast";
import { Language } from "@/types/learning-path";
import {
  getAllLanguages,
  getSelectedLanguages,
  selectLanguage,
  unselectLanguage,
} from "@/app/_actions/user/language-actions";

export default function LanguagesPage() {
  const [languages, setLanguages] = useState<Language[]>([]);
  const [selectedLanguages, setSelectedLanguages] = useState<Language[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [activeTab, setActiveTab] = useState("all");
  // We'll use this state to store the language details for the dialog
  const [, setSelectedLanguageDetails] = useState<Language | null>(null);

  useEffect(() => {
    async function loadData() {
      setIsLoading(true);
      try {
        // Load all available languages
        const allLanguagesResult = await getAllLanguages();
        if (allLanguagesResult.data) {
          setLanguages(allLanguagesResult.data);
        }

        // Load user's selected languages
        const selectedLanguagesResult = await getSelectedLanguages();
        if (selectedLanguagesResult.data) {
          setSelectedLanguages(selectedLanguagesResult.data);
        }
      } catch (error) {
        console.error("Error loading languages:", error);
        toast({
          title: "Error",
          description: "Failed to load languages",
          variant: "destructive",
        });
      } finally {
        setIsLoading(false);
      }
    }

    loadData();
  }, []);

  const handleSelectLanguage = async (language: Language) => {
    try {
      const result = await selectLanguage(language.id);
      if (result.success) {
        setSelectedLanguages([...selectedLanguages, language]);
        toast({
          title: "Success",
          description: `${language.name} added to your learning languages`,
        });
      } else {
        throw new Error(result.error || "Failed to select language");
      }
    } catch (error) {
      console.error("Error selecting language:", error);
      toast({
        title: "Error",
        description: "Failed to add language",
        variant: "destructive",
      });
    }
  };

  const handleUnselectLanguage = async (language: Language) => {
    try {
      const result = await unselectLanguage(language.id);
      if (result.success) {
        setSelectedLanguages(
          selectedLanguages.filter((lang) => lang.id !== language.id)
        );
        toast({
          title: "Success",
          description: `${language.name} removed from your learning languages`,
        });
      } else {
        throw new Error(result.error || "Failed to remove language");
      }
    } catch (error) {
      console.error("Error removing language:", error);
      toast({
        title: "Error",
        description: "Failed to remove language",
        variant: "destructive",
      });
    }
  };

  const isLanguageSelected = (languageId: number) => {
    return selectedLanguages.some((lang) => lang.id === languageId);
  };

  const filteredLanguages =
    activeTab === "selected"
      ? languages.filter((lang) => isLanguageSelected(lang.id))
      : languages;

  return (
    <main className="container px-4 py-6 md:px-6 md:py-8">
      <div className="mb-8">
        <h1 className="mb-2 text-3xl font-bold">Language Selection</h1>
        <p className="text-muted-foreground">
          Browse available languages and select which ones you want to learn.
        </p>
      </div>

      <Tabs defaultValue="all" className="mb-8" onValueChange={setActiveTab}>
        <TabsList className="grid w-full max-w-md grid-cols-2">
          <TabsTrigger value="all">All Languages</TabsTrigger>
          <TabsTrigger value="selected">My Languages</TabsTrigger>
        </TabsList>

        <TabsContent value="all" className="mt-6">
          <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            {isLoading
              ? // Loading skeletons
                Array(6)
                  .fill(0)
                  .map((_, i) => (
                    <Card key={i} className="overflow-hidden">
                      <CardHeader className="pb-2">
                        <Skeleton className="w-1/2 h-6 mb-2" />
                        <Skeleton className="w-3/4 h-4" />
                      </CardHeader>
                      <CardContent>
                        <Skeleton className="w-full h-20 mb-4" />
                        <div className="flex gap-2">
                          <Skeleton className="w-16 h-5" />
                          <Skeleton className="w-16 h-5" />
                        </div>
                      </CardContent>
                      <CardFooter>
                        <Skeleton className="w-full h-10" />
                      </CardFooter>
                    </Card>
                  ))
              : filteredLanguages.map((language) => (
                  <Card key={language.id} className="overflow-hidden">
                    <CardHeader className="pb-2">
                      <CardTitle className="flex items-center justify-between">
                        {language.name}
                        <Badge variant="outline">
                          {language.code.toUpperCase()}
                        </Badge>
                      </CardTitle>
                      <CardDescription>{language.native_name}</CardDescription>
                    </CardHeader>
                    <CardContent>
                      <div className="flex items-center gap-2 mb-4">
                        <Badge variant="secondary" className="text-xs">
                          {language.learning_paths_count || 0} Learning Paths
                        </Badge>
                        {language.is_popular && (
                          <Badge variant="default" className="text-xs">
                            Popular
                          </Badge>
                        )}
                      </div>
                    </CardContent>
                    <CardFooter className="flex justify-between">
                      <Dialog>
                        <DialogTrigger asChild>
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setSelectedLanguageDetails(language)}
                          >
                            <Info className="w-4 h-4 mr-2" />
                            Details
                          </Button>
                        </DialogTrigger>
                        <DialogContent>
                          <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                              {language.name}
                              <Badge variant="outline">
                                {language.code.toUpperCase()}
                              </Badge>
                            </DialogTitle>
                            <DialogDescription>
                              Native name: {language.native_name}
                            </DialogDescription>
                          </DialogHeader>
                          <div className="space-y-4">
                            <div>
                              <h4 className="mb-1 font-medium">
                                About this language
                              </h4>
                              <p className="text-sm text-muted-foreground">
                                {language.description ||
                                  `${language.name} is spoken by millions of people around the world. Start your journey to learn this beautiful language today.`}
                              </p>
                            </div>
                            <div>
                              <h4 className="mb-1 font-medium">
                                Learning Content
                              </h4>
                              <p className="text-sm text-muted-foreground">
                                {language.learning_paths_count || 0} learning
                                paths available
                              </p>
                            </div>
                          </div>
                        </DialogContent>
                      </Dialog>

                      {isLanguageSelected(language.id) ? (
                        <Button
                          variant="outline"
                          onClick={() => handleUnselectLanguage(language)}
                        >
                          <Check className="w-4 h-4 mr-2" />
                          Selected
                        </Button>
                      ) : (
                        <Button onClick={() => handleSelectLanguage(language)}>
                          <Plus className="w-4 h-4 mr-2" />
                          Add
                        </Button>
                      )}
                    </CardFooter>
                  </Card>
                ))}
          </div>
        </TabsContent>

        <TabsContent value="selected" className="mt-6">
          {isLoading ? (
            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
              {Array(3)
                .fill(0)
                .map((_, i) => (
                  <Card key={i} className="overflow-hidden">
                    <CardHeader className="pb-2">
                      <Skeleton className="w-1/2 h-6 mb-2" />
                      <Skeleton className="w-3/4 h-4" />
                    </CardHeader>
                    <CardContent>
                      <Skeleton className="w-full h-20 mb-4" />
                      <div className="flex gap-2">
                        <Skeleton className="w-16 h-5" />
                        <Skeleton className="w-16 h-5" />
                      </div>
                    </CardContent>
                    <CardFooter>
                      <Skeleton className="w-full h-10" />
                    </CardFooter>
                  </Card>
                ))}
            </div>
          ) : selectedLanguages.length > 0 ? (
            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
              {selectedLanguages.map((language) => (
                <Card key={language.id} className="overflow-hidden">
                  <CardHeader className="pb-2">
                    <CardTitle className="flex items-center justify-between">
                      {language.name}
                      <Badge variant="outline">
                        {language.code.toUpperCase()}
                      </Badge>
                    </CardTitle>
                    <CardDescription>{language.native_name}</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="flex items-center gap-2 mb-4">
                      <Badge variant="secondary" className="text-xs">
                        {language.learning_paths_count || 0} Learning Paths
                      </Badge>
                      {language.is_popular && (
                        <Badge variant="default" className="text-xs">
                          Popular
                        </Badge>
                      )}
                    </div>
                  </CardContent>
                  <CardFooter className="flex justify-between">
                    <Button variant="outline" size="sm" asChild>
                      <a href={`/learn?language=${language.id}`}>
                        <BookOpen className="w-4 h-4 mr-2" />
                        Start Learning
                      </a>
                    </Button>
                    <Button
                      variant="outline"
                      onClick={() => handleUnselectLanguage(language)}
                    >
                      <Check className="w-4 h-4 mr-2" />
                      Selected
                    </Button>
                  </CardFooter>
                </Card>
              ))}
            </div>
          ) : (
            <div className="py-12 text-center">
              <Globe className="w-12 h-12 mx-auto mb-4 text-muted-foreground" />
              <h3 className="mb-2 text-lg font-medium">
                No languages selected
              </h3>
              <p className="mb-6 text-muted-foreground">
                You haven&apos;t selected any languages to learn yet. Browse the
                available languages and add some to your profile.
              </p>
              <Button onClick={() => setActiveTab("all")}>
                Browse Languages
              </Button>
            </div>
          )}
        </TabsContent>
      </Tabs>
    </main>
  );
}
