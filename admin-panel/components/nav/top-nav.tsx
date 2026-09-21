"use client";

import { useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import { useSelector, useDispatch } from "react-redux";
import { RootState } from "@/store/store";
import { logout } from "@/store/slices/authSlice";
import { Button } from "@/components/ui/button";
import { Bell, Search, Menu } from "lucide-react";
import { ThemeToggle } from "@/components/theme-toggle";
import { Input } from "@/components/ui/input";
import { UserProfile } from "@/components/nav/UserProfile";
import { Breadcrumbs } from "@/components/nav/breadcrumbs";
import { NotificationCenter } from "@/components/nav/NotificationCenter";
import { cn } from "@/lib/utils";
import Image from "next/image";

export function TopNav() {
  const [searchOpen, setSearchOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  const pathname = usePathname();
  const router = useRouter();
  const dispatch = useDispatch();
  const user = useSelector((state: RootState) => state.auth.user);

  // console.log('TopNav rendered with user:', user);

  const handleLogout = () => {
    dispatch(logout());
    router.push("/auth/login");
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    // Implement search functionality
    // console.log('Searching for:', searchQuery);
    setSearchOpen(false);
  };

  return (
    <header className="sticky top-0 z-40 w-full border-b border-border bg-card/80 backdrop-blur-sm shadow-sm overflow-visible">
      <div className="flex h-16 items-center px-4 sm:px-6 lg:px-8 overflow-visible">
        {/* Mobile menu button */}
        <Button
          variant="ghost"
          size="icon"
          className="mr-2 md:hidden"
          onClick={() => {
            // Toggle mobile sidebar
            const sidebar = document.querySelector("[data-sidebar]");
            if (sidebar) {
              sidebar.classList.toggle("translate-x-0");
            } else {
              // Fallback to toggle mobile menu
              const mobileMenuButton = document.querySelector(
                "[data-mobile-menu-button]"
              );
              if (mobileMenuButton) {
                (mobileMenuButton as HTMLButtonElement).click();
              }
            }
          }}
        >
          <Menu className="h-6 w-6" />
          <span className="sr-only">Toggle Menu</span>
        </Button>

        {/* Logo - only visible on mobile */}
        <div className="mr-4 md:hidden">
          <Image
            src="/images/fayda-logo.png"
            alt="FaydaTech"
            width={32}
            height={32}
            className="object-contain"
          />
        </div>

        {/* Breadcrumbs - visible on desktop */}
        <div className="hidden md:flex flex-1">
          <Breadcrumbs />
        </div>

        {/* Search */}
        <div className="flex items-center space-x-2 md:justify-end">
          <div className="w-full md:w-auto md:flex-none">
            {searchOpen ? (
              <form onSubmit={handleSearch} className="relative">
                <Input
                  type="search"
                  placeholder="Search organizations, users..."
                  className="md:w-[300px] lg:w-[400px] pl-10 bg-background/50 backdrop-blur-sm border-border/50"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  autoFocus
                />
                <Search className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-muted-foreground" />
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  className="absolute right-0 top-0 h-full px-3"
                  onClick={() => setSearchOpen(false)}
                >
                  <span className="sr-only">Close search</span>×
                </Button>
              </form>
            ) : (
              <Button
                variant="outline"
                className="relative h-9 w-full justify-start text-sm text-muted-foreground sm:pr-12 md:w-40 lg:w-64 bg-background/50 backdrop-blur-sm border-border/50"
                onClick={() => setSearchOpen(true)}
              >
                <Search className="mr-2 h-4 w-4" />
                <span className="hidden lg:inline-flex">
                  Search organizations, users...
                </span>
                <span className="inline-flex lg:hidden">Search</span>
                <kbd className="pointer-events-none absolute right-1.5 top-2 hidden h-5 select-none items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium opacity-100 sm:flex">
                  <span className="text-xs">⌘</span>K
                </kbd>
              </Button>
            )}
          </div>

          {/* Right section */}
          <div className="flex items-center space-x-2 relative">
            <ThemeToggle />

            <NotificationCenter />

            {/* User Profile Component */}
            {user && (
              <div className="flex items-center relative z-50">
                <UserProfile
                  user={{
                    id: user.id?.toString(),
                    full_name: `${user.first_name} ${user.last_name}`,
                    email: user.email,
                    role: user.role,
                    profile_picture: null, // Add actual profile picture URL if available
                    status: "online", // Set default status or get from user data
                    notifications_count: 0, // Set actual notification count if available
                  }}
                  onLogout={handleLogout}
                />
              </div>
            )}
          </div>
        </div>
      </div>
    </header>
  );
}
