"use client"

import { useEffect, useState } from "react"
import { sanitizeRichText } from "@/lib/sanitizeRichText.mjs"

export function SafeRichText({ html, className }: { html: unknown; className?: string }) {
  // Server and first browser render are identical; DOMPurify runs only with a DOM.
  const [mounted, setMounted] = useState(false)
  useEffect(() => setMounted(true), [])
  return <div className={className} dangerouslySetInnerHTML={{ __html: mounted ? sanitizeRichText(html) : "" }} />
}
