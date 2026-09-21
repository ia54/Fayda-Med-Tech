"use client";

import React from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { FileText, RefreshCw, Plus, Send, Mail } from "lucide-react";
import { Document, DocumentSigner, SignatureEvent } from "@/store/api/documentsApiSlice";
import { formatDate, getStatusBadgeClass } from "../utils";

interface SignatureWorkflowProps {
  documents: Document[];
  selectedDocument: Document | null;
  selectedDocumentId: number | null;
  setSelectedDocumentId: (id: number) => void;
  refetchSignatureStatus: () => void;
  refetchSignatureHistory: () => void;
  isSignatureStatusFetching: boolean;
  isSignatureHistoryFetching: boolean;
  signatureStatusResponse?: any;
  signatureHistoryResponse?: any;
  signers: any[];
  setSigners: React.Dispatch<React.SetStateAction<any[]>>;
  initialSignerRow: any;
  handleAssignSigners: () => void;
  isAssigningSigners: boolean;
  emailSubject: string;
  setEmailSubject: (val: string) => void;
  emailMessage: string;
  setEmailMessage: (val: string) => void;
  handleSendForSignature: () => void;
  isSendingForSignature: boolean;
}

export function SignatureWorkflow({
  documents,
  selectedDocument,
  selectedDocumentId,
  setSelectedDocumentId,
  refetchSignatureStatus,
  refetchSignatureHistory,
  isSignatureStatusFetching,
  isSignatureHistoryFetching,
  signatureStatusResponse,
  signatureHistoryResponse,
  signers,
  setSigners,
  initialSignerRow,
  handleAssignSigners,
  isAssigningSigners,
  emailSubject,
  setEmailSubject,
  emailMessage,
  setEmailMessage,
  handleSendForSignature,
  isSendingForSignature,
}: SignatureWorkflowProps) {
  return (
    <Card className="bg-card/50 backdrop-blur-sm border-border/50">
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-primary">
          <FileText className="h-5 w-5" /> DocuSign Signature Workflow
        </CardTitle>
        <CardDescription>
          Select a document, assign one or more signers, then send for signature.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
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
          <div className="flex items-end">
            <Button
              variant="outline"
              className="w-full border-primary/20 hover:bg-primary/5"
              onClick={() => {
                refetchSignatureStatus();
                refetchSignatureHistory();
              }}
              disabled={!selectedDocumentId || isSignatureStatusFetching || isSignatureHistoryFetching}
            >
              <RefreshCw
                className={`h-4 w-4 mr-2 ${isSignatureStatusFetching || isSignatureHistoryFetching ? "animate-spin" : ""}`}
              />
              Refresh Status
            </Button>
          </div>
        </div>

        {selectedDocument && (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Card className="bg-muted/30 border-none shadow-none">
              <CardHeader className="pb-2 pt-3">
                <CardTitle className="text-[10px] uppercase tracking-wider text-muted-foreground">Document Status</CardTitle>
              </CardHeader>
              <CardContent className="pb-3">
                <Badge variant="outline" className={`text-xs ${getStatusBadgeClass(selectedDocument.document_status)}`}>
                  {selectedDocument.document_status}
                </Badge>
              </CardContent>
            </Card>
            <Card className="bg-muted/30 border-none shadow-none">
              <CardHeader className="pb-2 pt-3">
                <CardTitle className="text-[10px] uppercase tracking-wider text-muted-foreground">Signature Status</CardTitle>
              </CardHeader>
              <CardContent className="pb-3">
                <Badge variant="outline" className={`text-xs ${getStatusBadgeClass(selectedDocument.signature_status)}`}>
                  {selectedDocument.signature_status}
                </Badge>
              </CardContent>
            </Card>
            <Card className="bg-muted/30 border-none shadow-none">
              <CardHeader className="pb-2 pt-3">
                <CardTitle className="text-[10px] uppercase tracking-wider text-muted-foreground">Envelope ID</CardTitle>
              </CardHeader>
              <CardContent className="pb-3">
                <div className="text-[10px] text-muted-foreground break-all font-mono leading-tight">
                  {signatureStatusResponse?.data.envelope_id || "Not available"}
                </div>
              </CardContent>
            </Card>
          </div>
        )}

        <div className="space-y-4 border-t border-border/50 pt-4">
          <div className="flex items-center justify-between">
            <Label className="text-base font-semibold">Signer Management</Label>
            <Button
              size="sm"
              variant="outline"
              className="h-8 border-primary/20 text-primary hover:bg-primary/5"
              onClick={() =>
                setSigners((prev) => [...prev, { ...initialSignerRow, signing_order: prev.length + 1 }])
              }
            >
              <Plus className="h-3 w-3 mr-1" />
              Add Signer
            </Button>
          </div>

          <div className="space-y-3">
            {signers.map((row, index) => (
              <div key={index} className="grid grid-cols-1 md:grid-cols-12 gap-2 p-3 bg-muted/20 rounded-lg border border-border/30">
                <div className="md:col-span-4 space-y-1">
                  <Label className="text-[10px] text-muted-foreground ml-1">Full Name</Label>
                  <Input
                    placeholder="Signer name"
                    value={row.name}
                    onChange={(e) => {
                      const value = e.target.value;
                      setSigners((prev) => prev.map((item, i) => (i === index ? { ...item, name: value } : item)));
                    }}
                  />
                </div>
                <div className="md:col-span-5 space-y-1">
                  <Label className="text-[10px] text-muted-foreground ml-1">Email Address</Label>
                  <Input
                    type="email"
                    placeholder="Signer email"
                    value={row.email}
                    onChange={(e) => {
                      const value = e.target.value;
                      setSigners((prev) => prev.map((item, i) => (i === index ? { ...item, email: value } : item)));
                    }}
                  />
                </div>
                <div className="md:col-span-2 space-y-1">
                  <Label className="text-[10px] text-muted-foreground ml-1">Order</Label>
                  <Input
                    type="number"
                    min={1}
                    value={row.signing_order}
                    onChange={(e) => {
                      const value = Number(e.target.value) || 1;
                      setSigners((prev) =>
                        prev.map((item, i) => (i === index ? { ...item, signing_order: value } : item))
                      );
                    }}
                  />
                </div>
                <div className="md:col-span-1 flex items-end justify-center">
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-10 w-10 text-rose-500 hover:bg-rose-500/10"
                    onClick={() => setSigners((prev) => (prev.length > 1 ? prev.filter((_, i) => i !== index) : prev))}
                    disabled={signers.length === 1}
                  >
                    -
                  </Button>
                </div>
              </div>
            ))}
          </div>

          <div className="flex justify-end pt-2">
            <Button onClick={handleAssignSigners} disabled={isAssigningSigners || !selectedDocumentId} size="sm">
              {isAssigningSigners ? "Saving..." : "Save Signer Assignment"}
            </Button>
          </div>
        </div>

        <div className="space-y-3 border border-primary/20 bg-primary/5 rounded-xl p-5">
          <Label className="text-sm font-bold flex items-center gap-2">
            <Send className="h-4 w-4 text-primary" /> Send For Signature
          </Label>
          <div className="space-y-3">
            <Input
              placeholder="Email subject (optional)"
              value={emailSubject}
              className="bg-background/50 border-primary/20 focus-visible:ring-primary/30"
              onChange={(e) => setEmailSubject(e.target.value)}
            />
            <Textarea
              placeholder="Email message (optional)"
              value={emailMessage}
              className="bg-background/50 border-primary/20 focus-visible:ring-primary/30 min-h-[100px]"
              onChange={(e) => setEmailMessage(e.target.value)}
              rows={3}
            />
            <div className="flex justify-end">
              <Button onClick={handleSendForSignature} disabled={isSendingForSignature || !selectedDocumentId} className="shadow-lg shadow-primary/20">
                <Mail className="h-4 w-4 mr-2" />
                {isSendingForSignature ? "Sending..." : "Send to DocuSign"}
              </Button>
            </div>
          </div>
        </div>

        <Card className="border-border/30 bg-muted/10">
          <CardHeader className="py-4">
            <CardTitle className="text-sm font-semibold">Signature Event History</CardTitle>
          </CardHeader>
          <CardContent className="px-0 pt-0 overflow-x-auto">
            <Table>
              <TableHeader className="bg-muted/50">
                <TableRow>
                  <TableHead className="text-[10px] uppercase">Signer</TableHead>
                  <TableHead className="text-[10px] uppercase text-center">Status</TableHead>
                  <TableHead className="text-[10px] uppercase">Event</TableHead>
                  <TableHead className="text-[10px] uppercase text-right">Processed At</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {(signatureHistoryResponse?.data.signatures ?? []).length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={4} className="text-center py-6 text-xs text-muted-foreground">No signature events yet.</TableCell>
                  </TableRow>
                ) : (
                  (signatureHistoryResponse?.data.signatures ?? []).map((event: SignatureEvent) => {
                    const signer = (signatureStatusResponse?.data.signers ?? []).find(
                      (item: DocumentSigner) => item.id === event.document_signer_id
                    );

                    return (
                      <TableRow key={event.id}>
                        <TableCell className="text-xs">{signer ? `${signer.name} (${signer.email})` : "System"}</TableCell>
                        <TableCell className="text-center">
                          <Badge variant="outline" className={`text-[10px] ${getStatusBadgeClass(event.status)}`}>
                            {event.status}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-xs italic text-muted-foreground">{event.provider_event || "-"}</TableCell>
                        <TableCell className="text-xs text-right">{formatDate(event.processed_at)}</TableCell>
                      </TableRow>
                    );
                  })
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </CardContent>
    </Card>
  );
}
