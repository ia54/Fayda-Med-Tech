"use client";

import React from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Upload } from "lucide-react";

interface DocumentUploadSectionProps {
  uploadTitle: string;
  setUploadTitle: (value: string) => void;
  setUploadFile: (file: File | null) => void;
  handleUploadDocument: () => void;
  isUploadingDocument: boolean;
}

export function DocumentUploadSection({
  uploadTitle,
  setUploadTitle,
  setUploadFile,
  handleUploadDocument,
  isUploadingDocument,
}: DocumentUploadSectionProps) {
  return (
    <Card className="bg-card/50 backdrop-blur-sm border-border/50">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Upload className="h-5 w-5" /> Upload Document
        </CardTitle>
        <CardDescription>Supports pdf, jpeg, png, jpg, doc, docx up to 20MB.</CardDescription>
      </CardHeader>
      <CardContent className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div className="space-y-2 md:col-span-2">
          <Label htmlFor="doc-title">Title</Label>
          <Input
            id="doc-title"
            value={uploadTitle}
            onChange={(e) => setUploadTitle(e.target.value)}
            placeholder="Lien packet - March 2026"
          />
        </div>
        <div className="space-y-2 md:col-span-1">
          <Label htmlFor="doc-file">File</Label>
          <Input
            id="doc-file"
            type="file"
            onChange={(e) => setUploadFile(e.target.files?.[0] ?? null)}
            accept=".pdf,.jpeg,.jpg,.png,.doc,.docx"
          />
        </div>
        <div className="flex items-end">
          <Button className="w-full" onClick={handleUploadDocument} disabled={isUploadingDocument}>
            {isUploadingDocument ? "Uploading..." : "Upload"}
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
