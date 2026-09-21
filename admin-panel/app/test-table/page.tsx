"use client";

import { TestDropdownTable } from "@/components/global/table/test-dropdown";
import { TestCustomDropdown } from "@/components/ui/test-custom-dropdown";

export default function TestTablePage() {
  return (
    <div className="container mx-auto py-8 space-y-8">
      <h1 className="text-2xl font-bold mb-6">Dropdown Tests</h1>
      
      <TestCustomDropdown />
      
      <div>
        <h2 className="text-xl font-semibold mb-4">DataTable with Custom Dropdowns</h2>
        <TestDropdownTable />
      </div>
    </div>
  );
}