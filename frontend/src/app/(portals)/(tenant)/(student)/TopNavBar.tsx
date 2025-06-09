import React from "react";
import {
  BookOpen,
  Crown,
  FlameIcon as Fire,
  Lightbulb,
  Star,
  User,
  Users,
  BookText,
  GraduationCap,
  LogOut,
} from "lucide-react";
import { ChevronRight, Medal, Mic, Volume2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
  SheetFooter,
  SheetClose,
} from "@/components/ui/sheet";
import { Menu } from "lucide-react";
import LearnSideBar from "./LearnSideBar";
import {
  DropdownMenuTrigger,
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuItem,
  DropdownMenuSeparator,
} from "@/components/ui/dropdown-menu";
import { logout } from "@/app/_actions/auth-actions";
export default function TopNavBar() {
  const userData = {
    name: "Alex Johnson",
    points: 2450,
    streak: 7,
    currentPath: 4,
    completedPaths: [1, 2, 3],
    inProgressPaths: [4],
  };

  const handleLogout = async () => {
    await logout();

    // reload the page
    window.location.href = "/login";
  };

  return (
    <div>
      <header className="sticky top-0 z-10 border-b bg-background">
        <div className="flex items-center px-4">
          <Sheet>
            <SheetTrigger asChild>
              <Button variant="outline">
                <Menu />
              </Button>
            </SheetTrigger>
            <SheetContent side="left" className="w-64">
              <SheetHeader>
                <SheetTitle></SheetTitle>
              </SheetHeader>

              <LearnSideBar />
            </SheetContent>
          </Sheet>

          <div className="container flex items-center justify-between h-16 px-4 md:px-6">
            <div className="flex items-center gap-2 text-xl font-bold">
              <BookOpen className="w-6 h-6 text-primary" />
              <span>CreeQuest</span>
            </div>
            <div className="flex items-center gap-4">
              <div className="flex items-center gap-1 text-amber-500">
                <Star className="w-5 h-5 fill-amber-500 text-amber-500" />
                <span className="font-medium">{userData.points}</span>
              </div>
              <div className="flex items-center gap-1 text-orange-500">
                <Fire className="w-5 h-5 text-orange-500 fill-orange-500" />
                <span className="font-medium">{userData.streak}</span>
              </div>
              <div className="relative">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="rounded-full"
                    >
                      <User className="w-5 h-5" />
                      <span className="sr-only">User profile</span>
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" className="w-56">
                    <DropdownMenuLabel>My Account</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem asChild>
                      <a href="/learn/account">
                        <User className="w-4 h-4 mr-2" />
                        Account Settings
                      </a>
                    </DropdownMenuItem>
                    <DropdownMenuItem>
                      <button
                        onClick={handleLogout}
                        className="flex items-center w-full"
                      >
                        <LogOut className="w-4 h-4 mr-2" />
                        Sign out
                      </button>
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>
          </div>
        </div>
      </header>
    </div>
  );
}
