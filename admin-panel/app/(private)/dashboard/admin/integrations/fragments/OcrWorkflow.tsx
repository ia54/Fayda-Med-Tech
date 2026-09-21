"use client";

import React, { useEffect, useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Bot, RefreshCw, Mail, FileText, Copy, Download, Share2, ZoomIn, ZoomOut, ChevronLeft, ChevronRight } from "lucide-react";
import { Document } from "@/store/api/documentsApiSlice";
import { formatDate, getStatusBadgeClass } from "../utils";
import { useSelector } from "react-redux";
import { RootState } from "@/store/store";

interface OcrWorkflowProps {
  documents: Document[];
  selectedDocument: Document | null;
  selectedDocumentId: number | null;
  setSelectedDocumentId: (id: number) => void;
  featureType: "TEXT_DETECTION" | "DOCUMENT_TEXT_DETECTION";
  setFeatureType: (val: "TEXT_DETECTION" | "DOCUMENT_TEXT_DETECTION") => void;
  languageHintsText: string;
  setLanguageHintsText: (val: string) => void;
  handleProcessOcr: () => void;
  isProcessingOcr: boolean;
  refetchLatestOcr: () => void;
  isLatestOcrFetching: boolean;
  latestOcrResponse?: any;
  documentDetailsResponse?: any;
  isDocumentDetailsFetching: boolean;
}

export function OcrWorkflow({
  documents,
  selectedDocument,
  selectedDocumentId,
  setSelectedDocumentId,
  featureType,
  setFeatureType,
  languageHintsText,
  setLanguageHintsText,
  handleProcessOcr,
  isProcessingOcr,
  refetchLatestOcr,
  isLatestOcrFetching,
  latestOcrResponse,
  documentDetailsResponse,
  isDocumentDetailsFetching,
}: OcrWorkflowProps) {
  const [pdfUrl, setPdfUrl] = useState<string | null>(null);
  const [isPdfLoading, setIsPdfLoading] = useState(false);
  const token = useSelector((state: RootState) => state.auth.token?.access_token);

  useEffect(() => {
    if (selectedDocument && token) {
      const fetchPdf = async () => {
        setIsPdfLoading(true);
        try {
          const response = await fetch(
            `${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/documents/${selectedDocument.id}/preview`,
            {
              method: "GET",
              headers: {
                Authorization: `Bearer ${token}`,
                Accept: "application/pdf",
              },
              cache: "no-store",
            }
          );
          
          if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Failed to fetch PDF: ${response.status} ${errorText}`);
          }
          
          const blob = await response.blob();
          if (blob.size === 0) throw new Error("Received empty PDF blob");
          
          const url = URL.createObjectURL(blob);
          setPdfUrl(url);
        } catch (error) {
          console.error("Error loading PDF:", error);
          setPdfUrl(null);
        } finally {
          setIsPdfLoading(false);
        }
      };

      fetchPdf();
    } else {
      setPdfUrl(null);
    }

    // Cleanup Blob URL on unmount or document change
    return () => {
      if (pdfUrl) URL.revokeObjectURL(pdfUrl);
    };
  }, [selectedDocument]);

  return (
    <Card className="bg-card/50 backdrop-blur-sm border-border/50">
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-primary">
          <Bot className="h-5 w-5" /> Google Vision OCR
        </CardTitle>
        <CardDescription>
          Process selected document to extract text with Google Vision AI.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-5">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div className="space-y-2 md:col-span-2">
            <Label>Select Document</Label>
            <Select
              value={selectedDocumentId ? String(selectedDocumentId) : ""}
              onValueChange={(value) => setSelectedDocumentId(Number(value))}
            >
              <SelectTrigger className="w-full bg-background/50">
                <SelectValue placeholder="Choose document" />
              </SelectTrigger>
              <SelectContent>
                {documents.map((doc: Document) => (
                  <SelectItem key={doc.id} value={String(doc.id)}>
                    #{doc.id} - {doc.title}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-2">
            <Label>Feature Type</Label>
            <Select value={featureType} onValueChange={(value) => setFeatureType(value as "TEXT_DETECTION" | "DOCUMENT_TEXT_DETECTION")}>
              <SelectTrigger className="w-full bg-background/50">
                <SelectValue placeholder="Feature" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="DOCUMENT_TEXT_DETECTION">DOCUMENT_TEXT_DETECTION</SelectItem>
                <SelectItem value="TEXT_DETECTION">TEXT_DETECTION</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <div className="space-y-2">
          <Label htmlFor="language-hints" className="text-xs text-muted-foreground ml-1">Language Hints (comma separated, optional)</Label>
          <Input
            id="language-hints"
            value={languageHintsText}
            onChange={(e) => setLanguageHintsText(e.target.value)}
            className="bg-background/50"
            placeholder="en, es, fr"
          />
        </div>

        <div className="flex flex-wrap gap-3 pt-2">
          <Button
            onClick={handleProcessOcr}
            disabled={isProcessingOcr || !selectedDocumentId || selectedDocument?.ocr_status === "processing"}
            className="min-w-[140px]"
          >
            {isProcessingOcr ? "Processing..." : "Start OCR Process"}
          </Button>
          <Button
            variant="outline"
            onClick={() => refetchLatestOcr()}
            disabled={!selectedDocumentId || isLatestOcrFetching}
            className="border-primary/20 hover:bg-primary/5"
          >
            <RefreshCw className={`h-4 w-4 mr-2 ${isLatestOcrFetching ? "animate-spin" : ""}`} />
            Refresh OCR Result
          </Button>
        </div>

        <div className="pt-2">
          {/* Main Workspace: Extracted Text */}
          <Card className="border-border/30 bg-card/30 backdrop-blur-md overflow-hidden flex flex-col h-[750px] shadow-2xl">
            <CardHeader className="py-3 bg-muted/20 border-b border-border/50 shrink-0">
              <div className="flex items-center justify-between">
                <div className="flex bg-muted/50 p-1 rounded-lg">
                  <Button variant="secondary" size="sm" className="h-7 px-3 text-[10px] font-bold">Markdown</Button>
                  <Button variant="ghost" size="sm" className="h-7 px-3 text-[10px] font-bold opacity-50">JSON</Button>
                </div>
                <div className="flex items-center gap-1">
                  <Button 
                    variant="ghost" 
                    size="icon" 
                    className="h-7 w-7"
                    onClick={() => {
                      if (latestOcrResponse?.data?.extracted_text) {
                        navigator.clipboard.writeText(latestOcrResponse.data.extracted_text);
                      }
                    }}
                  >
                    <Copy className="h-3.5 w-3.5" />
                  </Button>
                  <Button variant="ghost" size="icon" className="h-7 w-7"><Download className="h-3.5 w-3.5" /></Button>
                  <Button variant="ghost" size="icon" className="h-7 w-7"><Share2 className="h-3.5 w-3.5" /></Button>
                </div>
              </div>
            </CardHeader>
            <CardContent className="flex-1 overflow-y-auto p-6 scrollbar-thin scrollbar-thumb-primary/10">
              {latestOcrResponse?.data ? (
                <div className="space-y-6 animate-in fade-in duration-500">
                  <div className="flex items-center justify-between">
                     <p className="text-[10px] text-muted-foreground font-mono">www.faydatech.com</p>
                     <Badge variant="outline" className={`text-[10px] uppercase tracking-tighter font-bold ${getStatusBadgeClass(latestOcrResponse.data.status)}`}>
                        {latestOcrResponse.data.status}
                      </Badge>
                  </div>
                  
                  <div className="prose prose-sm dark:prose-invert max-w-none">
                    <pre className="bg-transparent p-0 text-slate-800 dark:text-slate-200 whitespace-pre-wrap font-sans text-sm leading-relaxed">
                      {latestOcrResponse.data.extracted_text || "No text content found."}
                    </pre>
                  </div>

                  {latestOcrResponse.data.status === 'processed' && (
                    <div className="flex justify-center pt-4">
                      <Button variant="outline" size="sm" className="rounded-full h-8 px-6 text-[10px] font-bold border-primary/20 bg-primary/5 text-primary hover:bg-primary/10">
                        <Copy className="h-3 w-3 mr-2" /> Copy Full Text
                      </Button>
                    </div>
                  )}
                </div>
              ) : (
                <div className="h-full flex flex-col items-center justify-center py-20 text-center space-y-3 opacity-50">
                  <Bot className="h-12 w-12 text-primary" />
                  <div className="space-y-1">
                    <p className="text-sm font-semibold">Ready for Extraction</p>
                    <p className="text-xs text-muted-foreground max-w-[250px]">Click 'Start OCR Process' to analyze the document with Google Vision AI.</p>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </CardContent>
    </Card>
  );
}

function DetailItem({ label, value }: { label: string; value: any }) {
  return (
    <div className="space-y-1">
      <p className="text-[10px] text-muted-foreground font-semibold uppercase tracking-wider">{label}</p>
      <p className="text-xs font-medium truncate">{value}</p>
    </div>
  );
}
