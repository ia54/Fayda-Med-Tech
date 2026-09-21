"use client"

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { useGetDocumentCategoriesQuery } from "@/store/api/apiSlice"
import { Badge } from "@/components/ui/badge"

export default function DocumentCategoriesPage() {
  const { data, isLoading } = useGetDocumentCategoriesQuery({})

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Document Categories</h1>
          <p className="text-muted-foreground">Organize documents with categories and tags for easy searching and filtering (PDF Section 8)</p>
        </div>
        <Button>Add Category</Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Categories</CardTitle>
          <CardDescription>Categorize documents for better organization and retrieval</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <p className="text-muted-foreground">Loading...</p>
          ) : data?.data?.data?.length > 0 ? (
            <div className="flex flex-wrap gap-2">
              {data.data.data.map((cat: any) => (
                <div
                  key={cat.id}
                  className="flex items-center gap-2 px-3 py-2 rounded-lg border bg-muted/30"
                  style={{ borderLeftColor: cat.color || 'var(--primary)', borderLeftWidth: 4 }}
                >
                  <div>
                    <div className="font-medium">{cat.name}</div>
                    <div className="text-xs text-muted-foreground">{cat.description || 'No description'}</div>
                  </div>
                  <Badge variant="outline" className="ml-2">{cat.sort_order || 0}</Badge>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-muted-foreground">No categories defined. Create your first category to organize documents.</p>
          )}
        </CardContent>
      </Card>
    </div>
  )
}