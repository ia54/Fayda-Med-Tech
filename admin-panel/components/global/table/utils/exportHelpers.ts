/**
 * Export Helper Functions for DataTable
 *
 * These utilities help with exporting table data to various formats.
 * Usage: Import these functions in your exportConfig.onExport handler
 */

/**
 * Convert data to CSV format and trigger download
 */
export function exportToCSV<T extends Record<string, any>>(
  data: T[],
  filename: string = "export.csv"
): void {
  if (!data || data.length === 0) {
    console.warn("No data to export");
    return;
  }

  // Get headers from first object
  const headers = Object.keys(data[0]);

  // Create CSV content
  const csvContent = [
    // Header row
    headers.map(escapeCSVValue).join(","),
    // Data rows
    ...data.map((row) =>
      headers.map((header) => escapeCSVValue(row[header])).join(",")
    ),
  ].join("\n");

  // Create blob and download
  downloadFile(csvContent, filename, "text/csv;charset=utf-8;");
}

/**
 * Escape special characters in CSV values
 */
function escapeCSVValue(value: any): string {
  if (value === null || value === undefined) {
    return "";
  }

  const stringValue = String(value);

  // If value contains comma, quote, or newline, wrap in quotes and escape quotes
  if (
    stringValue.includes(",") ||
    stringValue.includes('"') ||
    stringValue.includes("\n")
  ) {
    return `"${stringValue.replace(/"/g, '""')}"`;
  }

  return stringValue;
}

/**
 * Convert data to JSON format and trigger download
 */
export function exportToJSON<T>(
  data: T[],
  filename: string = "export.json"
): void {
  if (!data || data.length === 0) {
    console.warn("No data to export");
    return;
  }

  const jsonContent = JSON.stringify(data, null, 2);
  downloadFile(jsonContent, filename, "application/json");
}

/**
 * Convert data to Excel-compatible format (CSV with Excel headers)
 */
export function exportToExcel<T extends Record<string, any>>(
  data: T[],
  filename: string = "export.xlsx"
): void {
  if (!data || data.length === 0) {
    console.warn("No data to export");
    return;
  }

  // For now, use CSV format which Excel can open
  // In production, consider using a library like xlsx or exceljs
  const headers = Object.keys(data[0]);

  const csvContent = [
    // Add BOM for Excel UTF-8 support
    "\uFEFF",
    // Header row
    headers.map(escapeCSVValue).join(","),
    // Data rows
    ...data.map((row) =>
      headers.map((header) => escapeCSVValue(row[header])).join(",")
    ),
  ].join("\n");

  downloadFile(
    csvContent,
    filename.replace(".xlsx", ".csv"),
    "text/csv;charset=utf-8;"
  );
}

/**
 * Helper function to trigger file download
 */
function downloadFile(
  content: string,
  filename: string,
  mimeType: string
): void {
  const blob = new Blob([content], { type: mimeType });
  const url = URL.createObjectURL(blob);

  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  link.style.display = "none";

  document.body.appendChild(link);
  link.click();

  // Cleanup
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

/**
 * Format data for export by transforming column values
 */
export function formatDataForExport<T extends Record<string, any>>(
  data: T[],
  columnFormatters?: Record<keyof T, (value: any) => any>
): Record<string, any>[] {
  if (!columnFormatters) {
    return data;
  }

  return data.map((row) => {
    const formattedRow: Record<string, any> = {};

    Object.keys(row).forEach((key) => {
      const formatter = columnFormatters[key as keyof T];
      formattedRow[key] = formatter ? formatter(row[key]) : row[key];
    });

    return formattedRow;
  });
}

/**
 * Example usage with DataTable component
 *
 * ```tsx
 * import { exportToCSV, exportToExcel, formatDataForExport } from "@/components/global/table/utils/exportHelpers";
 *
 * <DataTable
 *   data={users}
 *   columns={columns}
 *   exportConfig={{
 *     onExport: async (format) => {
 *       // Transform data for export
 *       const exportData = formatDataForExport(users, {
 *         created_at: (date) => new Date(date).toLocaleDateString(),
 *         status: (status) => status.toUpperCase(),
 *       });
 *
 *       // Rename columns for export
 *       const formattedData = exportData.map(user => ({
 *         "Full Name": `${user.first_name} ${user.last_name}`,
 *         "Email Address": user.email,
 *         "Role": user.role,
 *         "Status": user.status,
 *         "Created Date": user.created_at,
 *       }));
 *
 *       // Export based on format
 *       switch (format) {
 *         case "csv":
 *           exportToCSV(formattedData, "users.csv");
 *           break;
 *         case "excel":
 *           exportToExcel(formattedData, "users.xlsx");
 *           break;
 *         case "json":
 *           exportToJSON(formattedData, "users.json");
 *           break;
 *       }
 *     },
 *     formats: ["csv", "excel", "json"],
 *   }}
 * />
 * ```
 */

/**
 * Export only selected rows
 */
export function exportSelectedRows<T extends Record<string, any>>(
  allData: T[],
  selectedRowKeys: string[],
  getRowKey: (row: T) => string,
  format: "csv" | "excel" | "json",
  filename: string = "export"
): void {
  const selectedData = allData.filter((row) =>
    selectedRowKeys.includes(getRowKey(row))
  );

  if (selectedData.length === 0) {
    console.warn("No rows selected for export");
    return;
  }

  switch (format) {
    case "csv":
      exportToCSV(selectedData, `${filename}.csv`);
      break;
    case "excel":
      exportToExcel(selectedData, `${filename}.xlsx`);
      break;
    case "json":
      exportToJSON(selectedData, `${filename}.json`);
      break;
  }
}

/**
 * Export with custom column selection
 */
export function exportWithColumns<T extends Record<string, any>>(
  data: T[],
  columns: string[],
  format: "csv" | "excel" | "json",
  filename: string = "export"
): void {
  const filteredData = data.map((row) => {
    const filtered: Record<string, any> = {};
    columns.forEach((col) => {
      if (col in row) {
        filtered[col] = row[col];
      }
    });
    return filtered;
  });

  switch (format) {
    case "csv":
      exportToCSV(filteredData, `${filename}.csv`);
      break;
    case "excel":
      exportToExcel(filteredData, `${filename}.xlsx`);
      break;
    case "json":
      exportToJSON(filteredData, `${filename}.json`);
      break;
  }
}

/**
 * Get export filename with timestamp
 */
export function getExportFilename(
  baseName: string,
  format: "csv" | "excel" | "json"
): string {
  const timestamp = new Date().toISOString().split("T")[0];
  const extension = format === "excel" ? "csv" : format;
  return `${baseName}_${timestamp}.${extension}`;
}
