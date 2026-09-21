import { useState, useMemo } from "react";
import type { Blog } from "@/store/api/blogsApiSlice";
import type { DateRange } from "@/components/ui/filters/DateRangeFilter";
import dayjs from "dayjs";

export function useBlogFilters(blogs: Blog[]) {
  const [searchTerm, setSearchTerm] = useState("");
  const [dateRange, setDateRange] = useState<DateRange | undefined>(undefined);

  const handleDateRangeChange = (range: DateRange | undefined) => {
    setDateRange(range);
  };

  const filteredBlogs = useMemo(() => {
    return blogs.filter((blog) => {
      // Search filter
      const matchesSearch =
        searchTerm === "" ||
        blog.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
        blog.author.toLowerCase().includes(searchTerm.toLowerCase()) ||
        blog.category.toLowerCase().includes(searchTerm.toLowerCase()) ||
        blog.tags.some((tag) => tag.toLowerCase().includes(searchTerm.toLowerCase()));

      // Date range filter
      const matchesDateRange = !dateRange?.from || !dateRange?.to || (() => {
        const createdDate = dayjs(blog.created_at);
        const fromDate = dayjs(dateRange.from);
        const toDate = dayjs(dateRange.to);
        return createdDate.isAfter(fromDate) && createdDate.isBefore(toDate.add(1, "day"));
      })();

      return matchesSearch && matchesDateRange;
    });
  }, [blogs, searchTerm, dateRange]);

  return {
    searchTerm,
    setSearchTerm,
    dateRange,
    handleDateRangeChange,
    filteredBlogs,
  };
}
