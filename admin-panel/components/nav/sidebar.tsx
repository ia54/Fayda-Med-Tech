"use client";

import { useState, useEffect } from "react";
import { usePathname, useRouter } from "next/navigation";
import { useSelector, useDispatch } from "react-redux";
import { RootState } from "@/store/store";
import { logout } from "@/store/slices/authSlice";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import {
  Home,
  Building2,
  Users,
  Shield,
  FileText,
  Layout,
  Settings,
  CreditCard,
  Activity,
  Flag,
  Menu,
  Briefcase,
  Scale,
  Server,
  Ticket,
  Zap,
  AlertTriangle,
  Bot,
  Upload,
  BarChart3,
  FileCheck,
  Send,
  FolderOpen,
  DollarSign,
  MessageSquare,
  Eye,
  User,
  Download,
  Bell,
  ChevronDown,
  Settings2,
  DollarSignIcon,
  Globe,
  Lock,
  BookOpen,
} from "lucide-react";
import { cn } from "@/lib/utils";
import Image from "next/image";
import Link from "next/link";
import {
  ROLES,
  RoleType,
  ROLE_DISPLAY_NAMES,
  MENU_CONFIG,
  MenuItemConfig,
} from "@/lib/roleConstants";
import { filterMenuByRole } from "@/lib/permissions";

/**
 * Icon lookup map — maps string icon names from MENU_CONFIG to actual Lucide components
 */
const ICON_MAP: Record<string, React.ComponentType<{ className?: string }>> = {
  Home,
  Building2,
  Users,
  Shield,
  FileText,
  Layout,
  Settings,
  CreditCard,
  Activity,
  Flag,
  Briefcase,
  Scale,
  Zap,
  AlertTriangle,
  Upload,
  BarChart3,
  FileCheck,
  Send,
  FolderOpen,
  DollarSign,
  MessageSquare,
  Eye,
  User,
  Download,
  Bell,
  Settings2,
  Globe,
  Lock,
  BookOpen,
};

/**
 * Get icon component from string name
 */
function getIcon(iconName: string): React.ComponentType<{ className?: string }> {
  return ICON_MAP[iconName] || FileText;
}

export function Sidebar() {
  const pathname = usePathname();
  const router = useRouter();
  const dispatch = useDispatch();
  const user = useSelector((state: RootState) => state.auth.user);

  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [openMenus, setOpenMenus] = useState<Record<string, boolean>>({});

  useEffect(() => {
    setIsMobileMenuOpen(false);
  }, [pathname]);

  // Auto-open dropdown if route matches a child item
  useEffect(() => {
    const userRole = (user?.role as RoleType) || ROLES.ADMIN;
    const filteredItems = filterMenuByRole(MENU_CONFIG, userRole);

    filteredItems.forEach((item) => {
      if (item.children) {
        const hasActiveChild = item.children.some(
          (child) => child.href && pathname.startsWith(child.href)
        );
        if (hasActiveChild) {
          setOpenMenus((prev) => ({ ...prev, [item.title]: true }));
        }
      }
    });
  }, [pathname, user?.role]);

  const handleLogout = () => {
    dispatch(logout());
    router.push("/auth/login");
  };

  // Use centralized MENU_CONFIG filtered by current user's role
  const userRole = (user?.role as RoleType) || ROLES.ADMIN;
  const filteredMenuItems = filterMenuByRole(MENU_CONFIG, userRole);

  // Group items by section for visual organization
  const sections: { name: string; items: MenuItemConfig[] }[] = [];
  const sectionMap = new Map<string, MenuItemConfig[]>();

  filteredMenuItems.forEach((item) => {
    const section = item.section || "Other";
    if (!sectionMap.has(section)) {
      sectionMap.set(section, []);
    }
    sectionMap.get(section)!.push(item);
  });

  sectionMap.forEach((items, name) => {
    sections.push({ name, items });
  });

  const toggleMenu = (title: string) => {
    setOpenMenus((prev) => ({
      ...prev,
      [title]: !prev[title],
    }));
  };

  const renderMenuItem = (item: MenuItemConfig) => {
    const Icon = getIcon(item.icon);
    const hasChildren = item.children?.length;
    const isOpen = openMenus[item.title];
    const isActive = item.href && pathname === item.href;

    // Dropdown menu item
    if (hasChildren) {
      return (
        <li key={item.title}>
          <button
            onClick={() => toggleMenu(item.title)}
            className={cn(
              "flex w-full items-center justify-between rounded-md p-2 text-sm font-semibold transition",
              isOpen ? "bg-accent/20 text-accent" : "hover:bg-accent/20"
            )}
          >
            <div className="flex items-center gap-3">
              <Icon className="h-5 w-5" />
              {item.title}
            </div>
            <ChevronDown
              className={cn(
                "h-4 w-4 transition-transform",
                isOpen && "rotate-180"
              )}
            />
          </button>

          {isOpen && (
            <div className="ml-7 mt-1 space-y-1">
              {item.children?.map((child) => {
                const ChildIcon = getIcon(child.icon);
                const isChildActive = pathname === child.href;

                return (
                  <Link
                    key={child.href}
                    href={child.href!}
                    onClick={() => setIsMobileMenuOpen(false)}
                    className={cn(
                      "flex items-center gap-3 rounded-md p-2 text-sm transition",
                      isChildActive
                        ? "bg-accent/20 text-accent"
                        : "text-muted-foreground hover:bg-accent/10"
                    )}
                  >
                    <ChildIcon className="h-4 w-4" />
                    {child.title}
                  </Link>
                );
              })}
            </div>
          )}
        </li>
      );
    }

    // Normal menu item
    return (
      <li key={item.title + (item.href || "")}>
        <Link
          href={item.href!}
          onClick={() => setIsMobileMenuOpen(false)}
          className={cn(
            "flex items-center gap-3 rounded-md p-2 text-sm font-semibold transition",
            isActive ? "bg-accent/20 text-accent" : "hover:bg-accent/20"
          )}
        >
          <Icon className="h-5 w-5" />
          {item.title}
        </Link>
      </li>
    );
  };

  const SidebarContent = () => (
    <div className="flex h-full flex-col bg-card/80 backdrop-blur-sm border-r border-border">
      {/* Logo */}
      <div className="flex h-16 items-center gap-3 px-6 border-b">
        <Image src="/images/fayda-logo.png" alt="Logo" width={40} height={40} />
        <div>
          <h1 className="font-bold">FaydaTech</h1>
          <p className="text-xs text-muted-foreground">
            {ROLE_DISPLAY_NAMES[user?.role as RoleType] || "User"}
          </p>
        </div>
      </div>

      {/* Menu — grouped by section */}
      <nav className="flex-1 px-4 py-4 overflow-y-auto">
        {sections.map((section, sectionIndex) => (
          <div key={section.name} className={sectionIndex > 0 ? "mt-4" : ""}>
            {/* Section header — only show for non-Main sections with multiple items */}
            {section.name !== "Main" && sections.length > 1 && (
              <p className="px-2 mb-2 text-xs font-semibold text-muted-foreground/70 uppercase tracking-wider">
                {section.name}
              </p>
            )}
            <ul className="space-y-1">
              {section.items.map(renderMenuItem)}
            </ul>
          </div>
        ))}
      </nav>
    </div>
  );

  return (
    <>
      {/* Desktop */}
      <div className="hidden md:fixed md:inset-y-0 md:flex md:w-72">
        <SidebarContent />
      </div>

      {/* Mobile */}
      <Sheet open={isMobileMenuOpen} onOpenChange={setIsMobileMenuOpen}>
        <SheetTrigger asChild>
          <Button variant="ghost" className="fixed top-4 left-4 z-50 md:hidden">
            <Menu className="h-5 w-5" />
          </Button>
        </SheetTrigger>
        <SheetContent side="left" className="w-72 p-0">
          <SidebarContent />
        </SheetContent>
      </Sheet>
    </>
  );
}
