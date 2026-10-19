/** Flatten the returned report without losing zero values, unknowns or nested records. */
export function reportRows(value: unknown, prefix = ""): [string, string][] {
  if (value === null || value === undefined) return [[prefix, "Not recorded"]]
  if (typeof value === "number") return [[prefix, value.toLocaleString("en-US", { useGrouping: false, maximumFractionDigits: 2 })]]
  if (typeof value !== "object") return [[prefix, String(value)]]
  const entries = Object.entries(value)
  if (!entries.length) return [[prefix || "Results", "No records"]]
  return entries.flatMap(([key, child]) => reportRows(child, [prefix, Array.isArray(value) ? `Record ${Number(key) + 1}` : key.replace(/_/g, " ")].filter(Boolean).join(" / ")))
}

export function reportCsv(value: unknown): string {
  const cell = (text: string) => {
    // Quoting alone does not prevent spreadsheet formula execution.
    const safe = /^[\s\u0000-\u001f]*[=+@-]/.test(text) || /^[\t\r\n]/.test(text) ? `'${text}` : text
    return `"${safe.replace(/"/g, '""')}"`
  }
  return "\ufeff" + [["Field", "Value"], ...reportRows(value)].map(row => row.map(cell).join(",")).join("\r\n") + "\r\n"
}
