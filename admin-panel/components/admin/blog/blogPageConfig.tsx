import { FileText, Eye, Edit2, Clock, Plus } from "lucide-react";

// Stats Cards Configuration
export function getStatsCards(stats: {
  totalBlogs: number;
  publishedBlogs: number;
  draftBlogs: number;
  scheduledBlogs: number;
}) {
  return [
    {
      id: "total",
      title: "Total Posts",
      value: stats.totalBlogs.toLocaleString(),
      icon: <FileText className="h-4 w-4" />,
      description: "All blog posts",
    },
    {
      id: "published",
      title: "Published",
      value: stats.publishedBlogs.toLocaleString(),
      icon: <Eye className="h-4 w-4" />,
      description: "Live on website",
    },
    {
      id: "drafts",
      title: "Drafts",
      value: stats.draftBlogs.toLocaleString(),
      icon: <Edit2 className="h-4 w-4" />,
      description: "Needs review",
    },
    {
      id: "scheduled",
      title: "Scheduled",
      value: stats.scheduledBlogs.toLocaleString(),
      icon: <Clock className="h-4 w-4" />,
      description: "Pending publish",
    },
  ];
}

// Page Header Configuration
export function getPageHeader(onAddBlog: () => void, onExport?: () => void) {
  return {
    title: "Blog Management",
    description: "Create and manage blog posts for your website",
    actions: [
      {
        label: "New Blog Post",
        icon: <Plus className="h-4 w-4" />,
        onClick: onAddBlog,
      },
    ],
    exportConfig: onExport
      ? {
          onExport,
          formats: ["csv", "excel", "json"] as (
            | "csv"
            | "excel"
            | "json"
            | "pdf"
          )[],
        }
      : undefined,
  };
}

// Empty State Configuration
export function getEmptyState(onAdd: () => void) {
  return {
    title: "No blog posts found",
    description: "Get started by creating your first blog post",
    action: {
      label: "Create Blog Post",
      onClick: onAdd,
    },
  };
}

// Loading State Configuration
export function getLoadingState(isLoading: boolean) {
  return {
    isLoading,
    rowCount: 5,
    message: isLoading ? "Loading blog posts..." : undefined,
  };
}
