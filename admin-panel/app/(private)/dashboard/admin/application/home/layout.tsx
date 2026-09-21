// import Link from "next/link";

// const homeMenus = [
//   {
//     label: "Home Banner",
//     href: "/dashboard/admin/application/home/banner",
//   },
//   {
//     label: "Why Now",
//     href: "/dashboard/admin/application/home/whyNow",
//   },
//   {
//     label: "App Integration",
//     href: "/dashboard/admin/application/home/appIntegration",
//   },
//   {
//     label: "Circle Typewritting",
//     href: "/dashboard/admin/application/home/circleTypewritting",
//   },
// ];

// export default function HomeSettingsLayout({
//   children,
// }: {
//   children: React.ReactNode;
// }) {
//   return (
//     <div>
//       <h2 className="mb-5 font-semibold text-2xl">Home Settings</h2>
//       {/* LEFT MENU */}
//       <div className="grid grid-cols-12 gap-6 ">
//         <aside className="col-span-12 sm:col-span-3 rounded-lg border bg-card p-4 space-y-2">
//           {homeMenus.map((item) => (
//             <Link
//               key={item.href}
//               href={item.href}
//               className="block rounded-md px-3 py-2 text-sm hover:bg-accent/20"
//             >
//               {item.label}
//             </Link>
//           ))}
//         </aside>

//         {/* RIGHT CONTENT */}
//         <main className="col-span-12 sm:col-span-9 rounded-lg border bg-card p-6">
//           {children}
//         </main>
//       </div>
//     </div>
//   );
// }

import { Settings } from "lucide-react";
import Link from "next/link";

const homeMenus = [
  {
    label: "Home Banner",
    icon: Settings,
    href: "/dashboard/admin/application/home/banner",
  },
  {
    label: "Why Now",
    icon: Settings,
    href: "/dashboard/admin/application/home/whyNow",
  },
  {
    label: "Why We Different",
    icon: Settings,
    href: "/dashboard/admin/application/home/whyWeDifferent",
  },
  {
    label: "App Integration",
    icon: Settings,
    href: "/dashboard/admin/application/home/appIntegration",
  },
  {
    label: "Circle Typewritting",
    icon: Settings,
    href: "/dashboard/admin/application/home/circleTypewritting",
  },
];

export default function HomeSettingsLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div>
      <h2 className="mb-5 font-semibold text-2xl">Home Settings</h2>

      <div className="grid grid-cols-12 gap-6">
        {/* Section Name */}
        <aside className="col-span-12 xl:col-span-3 shadow-md rounded-lg  bg-card p-4 space-y-2">
          {homeMenus.map((item) => {
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

        {/* Section Details */}
        <main className="col-span-12 xl:col-span-9 shadow-md rounded-lg  bg-card p-6">
          {children}
        </main>
      </div>
    </div>
  );
}
