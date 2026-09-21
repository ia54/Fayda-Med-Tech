import type { Metadata } from 'next'
import { GeistSans } from 'geist/font/sans'
import { GeistMono } from 'geist/font/mono'
import { Analytics } from '@vercel/analytics/next'
import './globals.css'
import { ClientLayoutProvider } from '@/components/providers/client-layout-provider'

export const metadata: Metadata = {
  title: 'Fayda Wellness Pharmacy Admin',
  description: 'Admin dashboard for Fayda Wellness Pharmacy',
}

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  return (
    <html lang="en" suppressHydrationWarning>
      <body className={`font-sans ${GeistSans.variable} ${GeistMono.variable}`}>
        <ClientLayoutProvider>
          {children}
        </ClientLayoutProvider>
      </body>
    </html>
  )
}