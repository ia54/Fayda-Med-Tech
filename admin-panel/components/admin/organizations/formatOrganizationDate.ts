/** Preserve missing or invalid source dates instead of implying an epoch date. */
export function formatOrganizationDate(value: string | null | undefined): string {
  if (!value || !value.trim()) return "Not recorded"
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? "Not recorded" : date.toLocaleDateString()
}
