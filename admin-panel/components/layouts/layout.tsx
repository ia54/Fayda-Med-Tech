"use client"

import type { ReactNode } from "react"
import { Sidebar } from "../nav/sidebar"
import { TopNav } from "../nav/top-nav"
interface LayoutProps {
  children: ReactNode
}

export default function Layout({ children }: LayoutProps) {
  return (
    <div className="flex min-h-screen overflow-x-hidden">
      <Sidebar />
      <div className="flex flex-col flex-1 md:ml-72 overflow-x-hidden max-w-full">
        <TopNav />
        <main className="flex-1 p-4 md:p-8 overflow-x-hidden max-w-full">
          {children}
        </main>
      </div>

    </div>

  )
}
