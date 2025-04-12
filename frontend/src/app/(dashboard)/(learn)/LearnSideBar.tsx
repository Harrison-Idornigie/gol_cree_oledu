import Link from "next/link";
import React from "react";
import { Button } from "@/components/ui/button";
import { Globe, Home, Settings, BookOpen } from "lucide-react";

export default function LearnSideBar() {
  return (
    <div>
      <div className="p-1">Cree Quest</div>
      <hr className="p-1" />
      <nav className="space-y-2">
        <Button variant="ghost" className="justify-start w-full" asChild>
          <Link href="/learn" className="flex items-center">
            <Home className="w-4 h-4 mr-2" />
            Learning Dashboard
          </Link>
        </Button>
        <Button variant="ghost" className="justify-start w-full" asChild>
          <Link href="/languages" className="flex items-center">
            <Globe className="w-4 h-4 mr-2" />
            Languages
          </Link>
        </Button>
        <Button variant="ghost" className="justify-start w-full" asChild>
          <Link href="/admin" className="flex items-center">
            <Settings className="w-4 h-4 mr-2" />
            Admin Panel
          </Link>
        </Button>
        <Button variant="ghost" className="justify-start w-full" asChild>
          <Link href="/profile" className="flex items-center">
            <BookOpen className="w-4 h-4 mr-2" />
            Profile
          </Link>
        </Button>
      </nav>
    </div>
  );
}
