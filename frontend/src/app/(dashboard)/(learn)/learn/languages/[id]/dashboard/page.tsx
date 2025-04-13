import LanguageDashboard from "../../../LanguageDashboard";
import { Metadata } from "next";

interface LanguageDashboardPageProps {
  params: {
    id: string;
  };
}

export const metadata: Metadata = {
  title: "Language Dashboard | Gift of Language",
  description: "Track your progress, recent activities, and get recommendations for your language learning journey.",
};

export default function LanguageDashboardPage({ params }: LanguageDashboardPageProps) {
  const languageId = parseInt(params.id);
  
  return <LanguageDashboard languageId={languageId} />;
}
