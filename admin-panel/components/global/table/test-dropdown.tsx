"use client";

import { DataTable, ColumnDef } from "./index";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { MoreHorizontal, Edit, Trash, Plus, Download } from "lucide-react";

interface TestUser {
  id: number;
  name: string;
  email: string;
  status: "Active" | "Inactive";
}

export function TestDropdownTable() {
  const data: TestUser[] = [
    { id: 1, name: "John Doe", email: "john@example.com", status: "Active" },
    { id: 2, name: "Jane Smith", email: "jane@example.com", status: "Inactive" },
  ];

  const columns: ColumnDef<TestUser>[] = [
    {
      id: "name",
      accessorKey: "name",
      header: "Name",
    },
    {
      id: "email",
      accessorKey: "email",
      header: "Email",
    },
    {
      id: "status",
      accessorKey: "status",
      header: "Status",
      cell: ({ value }) => (
        <Badge variant={value === "Active" ? "default" : "secondary"}>
          {value}
        </Badge>
      ),
    },
    {
      id: "actions",
      header: "Actions",
      enableHiding: false,
      cell: ({ row }) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" className="h-8 w-8 p-0">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem>
              <Edit className="mr-2 h-4 w-4" />
              Edit
            </DropdownMenuItem>
            <DropdownMenuItem>
              <Trash className="mr-2 h-4 w-4" />
              Delete
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];

  return (
    <DataTable
      name="Test Dropdown Table"
      data={data}
      columns={columns}
      addNewComponent={
        <Button>
          <Plus className="h-4 w-4 mr-2" />
          Add New
        </Button>
      }
      exportConfig={{
        onExport: (format) => console.log("Export:", format),
        formats: ["csv", "excel"],
      }}
      toolbarConfig={{
        showColumnToggle: true,
        showRefresh: true,
      }}
    />
  );
}