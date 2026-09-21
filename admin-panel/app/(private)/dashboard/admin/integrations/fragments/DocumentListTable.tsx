"use client";

import React from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Trash2, ExternalLink, CheckCircle2 } from "lucide-react";
import { Document, DocumentStatus, SignatureStatus, OcrStatus } from "@/store/api/documentsApiSlice";
import { formatDate, formatSize, getStatusBadgeClass } from "../utils";

interface DocumentListTableProps {
  documents: Document[];
  isLoading: boolean;
  selectedDocumentId: number | null;
  setSelectedDocumentId: (id: number) => void;
  handleDeleteDocument: (id: number) => void;
  setIsSignModalOpen: (open: boolean) => void;
  documentStatusFilter: DocumentStatus | "all";
  setDocumentStatusFilter: (value: DocumentStatus | "all") => void;
  signatureStatusFilter: SignatureStatus | "all";
  setSignatureStatusFilter: (value: SignatureStatus | "all") => void;
  ocrStatusFilter: OcrStatus | "all";
  setOcrStatusFilter: (value: OcrStatus | "all") => void;
  perPage: number;
  setPerPage: (value: number) => void;
  page: number;
  setPage: (value: (prev: number) => number) => void;
  pagination?: {
    current_page: number;
    last_page: number;
    total: number;
  };
  isDeletingDocument: boolean;
}

export function DocumentListTable({
  documents,
  isLoading,
  selectedDocumentId,
  setSelectedDocumentId,
  handleDeleteDocument,
  setIsSignModalOpen,
  documentStatusFilter,
  setDocumentStatusFilter,
  signatureStatusFilter,
  setSignatureStatusFilter,
  ocrStatusFilter,
  setOcrStatusFilter,
  perPage,
  setPerPage,
  page,
  setPage,
  pagination,
  isDeletingDocument,
}: DocumentListTableProps) {
  return (
    <Card className="bg-card/50 backdrop-blur-sm border-border/50">
      <CardHeader>
        <CardTitle>Document List</CardTitle>
        <CardDescription>Filter by workflow status and select a document for signature/OCR actions.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <Select value={documentStatusFilter} onValueChange={(value) => setDocumentStatusFilter(value as DocumentStatus | "all")}>
            <SelectTrigger className="w-full">
              <SelectValue placeholder="Document Status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All document status</SelectItem>
              <SelectItem value="draft">Draft</SelectItem>
              <SelectItem value="sent_for_signature">Sent for signature</SelectItem>
              <SelectItem value="signed">Signed</SelectItem>
              <SelectItem value="cancelled">Cancelled</SelectItem>
            </SelectContent>
          </Select>

          <Select value={signatureStatusFilter} onValueChange={(value) => setSignatureStatusFilter(value as SignatureStatus | "all")}>
            <SelectTrigger className="w-full">
              <SelectValue placeholder="Signature Status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All signature status</SelectItem>
              <SelectItem value="not_sent">Not sent</SelectItem>
              <SelectItem value="pending">Pending</SelectItem>
              <SelectItem value="completed">Completed</SelectItem>
              <SelectItem value="declined">Declined</SelectItem>
              <SelectItem value="voided">Voided</SelectItem>
            </SelectContent>
          </Select>

          <Select value={ocrStatusFilter} onValueChange={(value) => setOcrStatusFilter(value as OcrStatus | "all")}>
            <SelectTrigger className="w-full">
              <SelectValue placeholder="OCR Status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All OCR status</SelectItem>
              <SelectItem value="not_processed">Not processed</SelectItem>
              <SelectItem value="processing">Processing</SelectItem>
              <SelectItem value="processed">Processed</SelectItem>
              <SelectItem value="failed">Failed</SelectItem>
            </SelectContent>
          </Select>

          <div className="flex gap-2">
            <Select value={String(perPage)} onValueChange={(value) => setPerPage(Number(value))}>
              <SelectTrigger className="w-full">
                <SelectValue placeholder="Per page" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="10">10 / page</SelectItem>
                <SelectItem value="25">25 / page</SelectItem>
                <SelectItem value="50">50 / page</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <div className="rounded-md border border-border/50 overflow-x-auto">
          <Table>
            <TableHeader className="bg-muted/50">
              <TableRow>
                <TableHead className="font-bold">Title</TableHead>
                <TableHead className="hidden md:table-cell">File</TableHead>
                <TableHead className="hidden sm:table-cell">Workflow</TableHead>
                <TableHead>Signature</TableHead>
                <TableHead className="hidden lg:table-cell">OCR</TableHead>
                <TableHead className="hidden xl:table-cell">Created</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center py-10">Loading documents...</TableCell>
                </TableRow>
              ) : documents.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="text-center py-10 text-muted-foreground">No documents found.</TableCell>
                </TableRow>
              ) : (
                documents.map((doc: Document) => (
                  <TableRow key={doc.id} className={selectedDocumentId === doc.id ? "bg-primary/5 border-l-2 border-l-primary" : ""}>
                    <TableCell className="font-semibold text-slate-900 dark:text-white">
                      <div className="flex flex-col">
                        <span>{doc.title}</span>
                        <span className="text-[10px] text-muted-foreground md:hidden">{doc.original_name}</span>
                      </div>
                    </TableCell>
                    <TableCell className="hidden md:table-cell">
                      <div className="text-xs font-mono truncate max-w-[120px]">{doc.original_name}</div>
                      <div className="text-[10px] text-muted-foreground">{formatSize(doc.size)}</div>
                    </TableCell>
                    <TableCell className="hidden sm:table-cell">
                      <Badge variant="outline" className={`text-[10px] uppercase font-bold tracking-tighter ${getStatusBadgeClass(doc.document_status)}`}>
                        {doc.document_status.replace(/_/g, ' ')}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Badge variant="outline" className={`text-[10px] uppercase font-bold tracking-tighter ${getStatusBadgeClass(doc.signature_status)}`}>
                        {doc.signature_status}
                      </Badge>
                    </TableCell>
                    <TableCell className="hidden lg:table-cell">
                      <Badge variant="outline" className={`text-[10px] uppercase font-bold tracking-tighter ${getStatusBadgeClass(doc.ocr_status)}`}>
                        {doc.ocr_status}
                      </Badge>
                    </TableCell>
                    <TableCell className="hidden xl:table-cell text-xs">{formatDate(doc.created_at)}</TableCell>
                    <TableCell>
                      <div className="flex gap-1 justify-end">
                        <Button
                          variant={selectedDocumentId === doc.id ? "default" : "outline"}
                          size="icon"
                          className="h-8 w-8"
                          onClick={() => setSelectedDocumentId(doc.id)}
                          title="Select"
                        >
                          <CheckCircle2 className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="outline"
                          size="icon"
                          className="h-8 w-8 text-blue-500 border-blue-500/30 hover:bg-blue-500/10"
                          asChild
                        >
                          <a href={`${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/storage/${doc.path}`} target="_blank" rel="noopener noreferrer">
                            <ExternalLink className="h-4 w-4" />
                          </a>
                        </Button>
                        <Button
                          variant="outline"
                          size="icon"
                          className="h-8 w-8 text-emerald-500 border-emerald-500/30 hover:bg-emerald-500/10"
                          onClick={() => {
                            setSelectedDocumentId(doc.id);
                            setIsSignModalOpen(true);
                          }}
                        >
                          <Signature className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="outline"
                          size="icon"
                          className="h-8 w-8 text-rose-500 border-rose-500/30 hover:bg-rose-500/10"
                          onClick={() => handleDeleteDocument(doc.id)}
                          disabled={isDeletingDocument}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>

        <div className="flex items-center justify-between">
          <p className="text-sm text-muted-foreground">
            Page {pagination?.current_page ?? 1} of {pagination?.last_page ?? 1}
          </p>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setPage((prev) => Math.max(1, prev - 1))}
              disabled={(pagination?.current_page ?? 1) <= 1}
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => setPage((prev) => Math.min(pagination?.last_page ?? 1, prev + 1))}
              disabled={(pagination?.current_page ?? 1) >= (pagination?.last_page ?? 1)}
            >
              Next
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

// Internal Signature icon for the button
function Signature({ className }: { className?: string }) {
  return (
    <svg 
      className={className}
      viewBox="0 0 24 24" 
      fill="none" 
      stroke="currentColor" 
      strokeWidth="2" 
      strokeLinecap="round" 
      strokeLinejoin="round"
    >
      <path d="m14.5 12.5-5 5" />
      <path d="M2 17.5c5.5 0 9-3.5 14-8.5s8.5-5 13-5" />
      <path d="M22 2v20" />
    </svg>
  );
}
