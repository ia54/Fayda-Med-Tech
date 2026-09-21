import { Settings } from "lucide-react";
import Link from "next/link";

const privacyPolicyMenus = [
  {
    label: "Terms Condition Banner",
    icon: Settings,
    href: "/dashboard/admin/application/terms-condition/banner",
  },
  {
    label: "Terms Condition Content",
    icon: Settings,
    href: "/dashboard/admin/application/terms-condition/content",
  },
];

export default function PrivacyPolicySettingsLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div>
      <h2 className="mb-5 font-semibold text-2xl">Terms Condition Settings</h2>

      <div className="grid grid-cols-12 gap-6">
        <aside className="col-span-12 xl:col-span-3 rounded-lg shadow-md bg-card p-4 space-y-2">
          {privacyPolicyMenus.map((item) => {
            const Icon = item.icon;

            return (
              <Link
                key={item.href}
                href={item.href}
                className="flex items-start justify-start gap-2 rounded-md px-3 py-2 text-sm hover:bg-accent/20"
              >
                <Icon className="h-4 w-4" />
                {item.label}
              </Link>
            );
          })}
        </aside>

        <main className="col-span-12 xl:col-span-9 rounded-lg shadow-md bg-card p-6">
          {children}
        </main>
      </div>
    </div>
  );
}
