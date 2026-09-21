"use client";

import React from "react";
import { Calendar, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { DatePicker } from "antd";
import { cn } from "@/lib/utils";
import {
  format,
  startOfWeek,
  endOfWeek,
  startOfMonth,
  endOfMonth,
  startOfYear,
  endOfYear,
  subMonths,
  subYears,
} from "date-fns";
import dayjs, { Dayjs } from "dayjs";

const { RangePicker } = DatePicker;

export interface DateRange {
  from?: Date;
  to?: Date;
}

export interface DateRangeFilterProps {
  value?: DateRange;
  onChange: (range: DateRange) => void;
  placeholder?: string;
  className?: string;
  size?: "sm" | "md" | "lg";
  disabled?: boolean;
  clearable?: boolean;
  showPresets?: boolean;
}

const presetRanges = [
  {
    label: "Today",
    getValue: () => ({
      from: new Date(),
      to: new Date(),
    }),
  },
  {
    label: "This Week",
    getValue: () => ({
      from: startOfWeek(new Date(), { weekStartsOn: 1 }), // Monday
      to: endOfWeek(new Date(), { weekStartsOn: 1 }),
    }),
  },
  {
    label: "This Month",
    getValue: () => ({
      from: startOfMonth(new Date()),
      to: endOfMonth(new Date()),
    }),
  },
  {
    label: "Last 3 Months",
    getValue: () => ({
      from: startOfMonth(subMonths(new Date(), 2)),
      to: endOfMonth(new Date()),
    }),
  },
  {
    label: "This Year",
    getValue: () => ({
      from: startOfYear(new Date()),
      to: endOfYear(new Date()),
    }),
  },
  {
    label: "Last Year",
    getValue: () => ({
      from: startOfYear(subYears(new Date(), 1)),
      to: endOfYear(subYears(new Date(), 1)),
    }),
  },
];

export const DateRangeFilter: React.FC<DateRangeFilterProps> = ({
  value,
  onChange,
  placeholder = "Select date range...",
  className,
  size = "md",
  disabled = false,
  clearable = true,
  showPresets = true,
}) => {
  // Convert Date to Dayjs for Ant Design
  const dayjsValue: [Dayjs | null, Dayjs | null] | null = React.useMemo(() => {
    if (value?.from && value?.to) {
      return [dayjs(value.from), dayjs(value.to)];
    }
    if (value?.from) {
      return [dayjs(value.from), null];
    }
    if (value?.to) {
      return [null, dayjs(value.to)];
    }
    return null;
  }, [value]);

  const handleChange = (
    dates: [Dayjs | null, Dayjs | null] | null,
    dateStrings: [string, string]
  ) => {
    if (!dates || !dates[0] || !dates[1]) {
      onChange({});
    } else {
      onChange({
        from: dates[0].toDate(),
        to: dates[1].toDate(),
      });
    }
  };

  const handlePresetClick = (preset: (typeof presetRanges)[0]) => {
    const range = preset.getValue();
    onChange(range);
  };

  // Build preset ranges for Ant Design
  const rangePresets = showPresets
    ? presetRanges.map((preset) => {
        const range = preset.getValue();
        return {
          label: preset.label,
          value: [dayjs(range.from), dayjs(range.to)] as [Dayjs, Dayjs],
        };
      })
    : undefined;

  const sizeClass =
    size === "sm"
      ? "h-9"
      : size === "lg"
      ? "h-11"
      : "h-10";

  return (
    <div className={cn("filter-daterange-container", className)}>
      <RangePicker
        value={dayjsValue}
        onChange={handleChange}
        placeholder={[placeholder.split("...")[0] || "Start date", "End date"]}
        disabled={disabled}
        allowClear={clearable}
        presets={rangePresets}
        format="MMM D, YYYY"
        className={cn(
          "w-full",
          sizeClass,
          "rounded-md border border-input bg-background px-3 text-sm shadow-sm transition-colors",
          "hover:border-input focus:outline-none focus:ring-1 focus:ring-ring",
          "disabled:cursor-not-allowed disabled:opacity-50"
        )}
        suffixIcon={<Calendar className="h-4 w-4 text-muted-foreground" />}
        style={{ width: "100%" }}
        popupClassName="z-[9999]"
      />
    </div>
  );
};
