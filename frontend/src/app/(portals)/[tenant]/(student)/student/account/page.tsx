"use client";

import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { toast } from "@/hooks/use-toast";
import { Globe, User } from "lucide-react";
import {
  InterfaceLanguage,
  UserSettings,
  getAvailableInterfaceLanguages,
  getUserSettings,
  updateInterfaceLanguage,
} from "@/app/_actions/tenants/student/settings-actions";

export default function AccountPage() {
  const [activeTab, setActiveTab] = useState("general");
  const [isLoading, setIsLoading] = useState(true);
  const [settings, setSettings] = useState<UserSettings | null>(null);
  const [languages, setLanguages] = useState<InterfaceLanguage[]>([]);
  const [selectedLanguage, setSelectedLanguage] = useState<string>("");

  useEffect(() => {
    async function loadData() {
      setIsLoading(true);
      try {
        // Load user settings
        const settingsResult = await getUserSettings();
        if (settingsResult.success && settingsResult.data) {
          setSettings(settingsResult.data);
          setSelectedLanguage(settingsResult.data.interface_language);
        }

        // Load available interface languages
        const languagesResult = await getAvailableInterfaceLanguages();
        if (languagesResult.success && languagesResult.data) {
          setLanguages(languagesResult.data);
        }
      } catch (error) {
        console.error("Error loading account data:", error);
        toast({
          title: "Error",
          description: "Failed to load account settings",
          variant: "destructive",
        });
      } finally {
        setIsLoading(false);
      }
    }

    loadData();
  }, []);

  const handleLanguageChange = async (value: string) => {
    try {
      setSelectedLanguage(value);
      const result = await updateInterfaceLanguage(value);
      
      if (result.success) {
        toast({
          title: "Success",
          description: "Interface language updated successfully",
        });
        
        // Update local state
        if (settings) {
          setSettings({
            ...settings,
            interface_language: value,
          });
        }
      } else {
        throw new Error(result.error || "Failed to update interface language");
      }
    } catch (error) {
      console.error("Error updating interface language:", error);
      toast({
        title: "Error",
        description: "Failed to update interface language",
        variant: "destructive",
      });
      
      // Reset to previous value
      if (settings) {
        setSelectedLanguage(settings.interface_language);
      }
    }
  };

  return (
    <main className="container px-4 py-6 md:px-6 md:py-8">
      <div className="mb-8">
        <h1 className="mb-2 text-3xl font-bold">Account Settings</h1>
        <p className="text-muted-foreground">
          Manage your account preferences and settings
        </p>
      </div>

      <Tabs
        defaultValue="general"
        className="mb-8"
        onValueChange={setActiveTab}
      >
        <TabsList className="grid w-full max-w-md grid-cols-2">
          <TabsTrigger value="general">
            <User className="w-4 h-4 mr-2" />
            General
          </TabsTrigger>
          <TabsTrigger value="language">
            <Globe className="w-4 h-4 mr-2" />
            Language
          </TabsTrigger>
        </TabsList>

        <TabsContent value="general" className="mt-6">
          <Card>
            <CardHeader>
              <CardTitle>General Settings</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-muted-foreground">
                General account settings will be available here.
              </p>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="language" className="mt-6">
          <Card>
            <CardHeader>
              <CardTitle>Language Preferences</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="space-y-2">
                <h3 className="text-lg font-medium">Interface Language</h3>
                <p className="text-muted-foreground">
                  Choose the language for the user interface. This is separate from the languages you are learning.
                </p>
                <div className="max-w-xs">
                  <Select
                    value={selectedLanguage}
                    onValueChange={handleLanguageChange}
                    disabled={isLoading}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select language" />
                    </SelectTrigger>
                    <SelectContent>
                      {languages.map((language) => (
                        <SelectItem key={language.code} value={language.code}>
                          {language.name} ({language.native_name})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <p className="text-xs text-muted-foreground mt-2">
                  Note: This will change the language of all menus and instructions throughout the application.
                </p>
              </div>

              <div className="space-y-2">
                <h3 className="text-lg font-medium">Learning Languages</h3>
                <p className="text-muted-foreground">
                  To manage the languages you are learning, visit the{" "}
                  <a
                    href="/student/languages"
                    className="text-primary hover:underline"
                  >
                    Language Selection
                  </a>{" "}
                  page.
                </p>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </main>
  );
}
