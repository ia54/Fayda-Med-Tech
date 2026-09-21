import { Badge } from "@/components/ui/badge";

export const formatDate = (value: string | null | undefined) => {
  if (!value) return "-";
  return new Date(value).toLocaleString();
};

export const formatSize = (bytes: number) => {
  if (!Number.isFinite(bytes) || bytes <= 0) return "0 B";
  const units = ["B", "KB", "MB", "GB"];
  let size = bytes;
  let unit = 0;

  while (size >= 1024 && unit < units.length - 1) {
    size /= 1024;
    unit += 1;
  }

  return `${size.toFixed(size >= 10 || unit === 0 ? 0 : 1)} ${units[unit]}`;
};

export const getStatusBadgeClass = (status: string) => {
  if (["processed", "completed", "signed"].includes(status)) {
    return "bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30";
  }
  if (["pending", "processing", "sent", "sent_for_signature"].includes(status)) {
    return "bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30";
  }
  if (["failed", "declined", "voided", "cancelled"].includes(status)) {
    return "bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30";
  }
  return "bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-500/30";
};

export const isConfigError = (message?: string) => {
  if (!message) return false;
  const content = message.toLowerCase();
  return content.includes("missing") || content.includes("configuration") || content.includes("api key");
};
