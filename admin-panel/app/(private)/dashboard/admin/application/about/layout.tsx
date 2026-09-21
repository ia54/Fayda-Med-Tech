import { Settings } from "lucide-react";
import Link from "next/link";

const aboutMenus = [
  {
    label: "About Banner",
    icon: Settings,
    href: "/dashboard/admin/application/about/aboutBanner",
  },
  {
    label: "About Us",
    icon: Settings,
    href: "/dashboard/admin/application/about/aboutUs",
  },
  {
    label: "Who We Serve",
    icon: Settings,
    href: "/dashboard/admin/application/about/whoWeServe",
  },
  {
    label: "Our Story",
    icon: Settings,
    href: "/dashboard/admin/application/about/ourStory",
  },
  {
    label: "Our Mission",
    icon: Settings,
    href: "/dashboard/admin/application/about/ourMission",
  },
  {
    label: "Why FaydaMed Different",
    icon: Settings,
    href: "/dashboard/admin/application/about/whyFAYDAMEDDifferent",
  },
  {
    label: "Problem Solving",
    icon: Settings,
    href: "/dashboard/admin/application/about/problemSolving",
  },
];

export default function AboutSettingsLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div>
      {/* Section Name */}
      <h2 className="mb-5 font-semibold text-2xl">About Settings</h2>
      <div className="grid grid-cols-12 gap-6 ">
        <aside className="col-span-12 xl:col-span-3 rounded-lg shadow-md bg-card p-4 space-y-2">
          {aboutMenus.map((item) => {
            const Icon = item.icon;
            return (
              <Link
                key={item.href}
                href={item.href}
                // className="block rounded-md px-3 py-2 text-sm hover:bg-accent/20"
                className="flex items-start justify-start gap-2 rounded-md px-3 py-2 text-sm hover:bg-accent/20"
              >
                <Icon className="h-4 w-4" />
                {item.label}
              </Link>
            );
          })}
        </aside>

        {/* Section Details */}
        <main className="col-span-12 xl:col-span-9 shadow-md rounded-lg  bg-card p-6">
          {children}
        </main>
      </div>
    </div>
  );
}
