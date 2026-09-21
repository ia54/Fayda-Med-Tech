"use client";

import { useEffect, useMemo, useState } from "react";
import { ProtectedRoute } from "@/components/protected-route";
import { ROLES } from "@/lib/roleConstants";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { useToast } from "@/hooks/use-toast";
import { SignatureModal } from "@/components/SignatureModal";
import { 
  AlertCircle, 
  RefreshCw, 
  Activity, 
  Key, 
  Zap, 
  Bot, 
  FileText, 
  Settings, 
  CheckCircle,
  Mail,
  Trash2,
  Edit,
  Shield,
  HelpCircle
} from "lucide-react";
import {
  createDocumentUploadFormData,
  Document,
  OcrStatus,
  SignatureStatus,
  useAssignSignersMutation,
  useGetDocumentByIdQuery,
  useGetDocumentsQuery,
  useGetDocumentSignatureStatusQuery,
  useGetLatestDocumentOcrQuery,
  useGetSignatureHistoryByDocumentQuery,
  useProcessDocumentOcrMutation,
  useSendForSignatureMutation,
  useUploadDocumentMutation,
  useDeleteDocumentMutation,
  DocumentStatus,
} from "@/store/api/documentsApiSlice";
import {
  useGetApiCredentialsQuery,
  useDeleteApiCredentialMutation,
  ApiCredential,
} from "@/store/api/apiCredentialsApiSlice";

// Fragments
import { IntegrationsLoader } from "./fragments/IntegrationsLoader";
import { IntegrationStats } from "./fragments/IntegrationStats";
import { DocumentUploadSection } from "./fragments/DocumentUploadSection";
import { DocumentListTable } from "./fragments/DocumentListTable";
import { SignatureWorkflow } from "./fragments/SignatureWorkflow";
import { OcrWorkflow } from "./fragments/OcrWorkflow";
import { IntegrationFormModal } from "./fragments/IntegrationFormModal";

// Utils
import { isConfigError, formatDate } from "./utils";

// Types
type SignerFormRow = {
  name: string;
  email: string;
  signing_order: number;
};

const initialSignerRow: SignerFormRow = {
  name: "",
  email: "",
  signing_order: 1,
};

const PROVIDER_CONFIG: Record<string, { icon: any, type: string, description: string }> = {
  signature: { icon: FileText, type: "E-Signature", description: "Electronic signature for liens and AOB documents" },
  docusign: { icon: FileText, type: "E-Signature", description: "DocuSign integration for e-signatures" },
  ocr: { icon: Bot, type: "OCR", description: "Optical character recognition for forms" },
  openai: { icon: Zap, type: "AI Assistant", description: "AI-powered claim validation and appeal generation" },
  hellosign: { icon: FileText, type: "E-Signature", description: "Alternative e-signature provider" },
  other: { icon: Shield, type: "Generic", description: "Third-party service integration" },
};

import { useAuth } from "@/hooks/useAuth";
import { cn } from "@/lib/utils";

export default function AdminIntegrationsPage() {
  const { user } = useAuth();
  const isFirmAdmin = user?.role === ROLES.FIRM_ADMIN;
  const { toast } = useToast();

  // State
  const [activeTab, setActiveTab] = useState("overview");
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [documentStatusFilter, setDocumentStatusFilter] = useState<DocumentStatus | "all">("all");
  const [signatureStatusFilter, setSignatureStatusFilter] = useState<SignatureStatus | "all">("all");
  const [ocrStatusFilter, setOcrStatusFilter] = useState<OcrStatus | "all">("all");

  const [selectedDocumentId, setSelectedDocumentId] = useState<number | null>(null);
  const [isSignModalOpen, setIsSignModalOpen] = useState(false);

  const [uploadTitle, setUploadTitle] = useState("");
  const [uploadFile, setUploadFile] = useState<File | null>(null);

  const [signers, setSigners] = useState<SignerFormRow[]>([{ ...initialSignerRow }]);
  const [emailSubject, setEmailSubject] = useState("");
  const [emailMessage, setEmailMessage] = useState("");
  const [currentModalSigner, setCurrentModalSigner] = useState<{ name: string, email: string } | null>(null);
  const [isDocuSignFlow, setIsDocuSignFlow] = useState(false);

  const [featureType, setFeatureType] = useState<"TEXT_DETECTION" | "DOCUMENT_TEXT_DETECTION">("DOCUMENT_TEXT_DETECTION");
  const [languageHintsText, setLanguageHintsText] = useState("");

  const [isIntegrationModalOpen, setIsIntegrationModalOpen] = useState(false);
  const [editingCredential, setEditingCredential] = useState<ApiCredential | null>(null);

  // Queries
  const {
    data: documentsResponse,
    isLoading: isDocumentsLoading,
    isFetching: isDocumentsFetching,
    error: documentsError,
    refetch: refetchDocuments,
  } = useGetDocumentsQuery({
    page,
    per_page: perPage,
    ...(documentStatusFilter !== "all" ? { document_status: documentStatusFilter } : {}),
    ...(signatureStatusFilter !== "all" ? { signature_status: signatureStatusFilter } : {}),
    ...(ocrStatusFilter !== "all" ? { ocr_status: ocrStatusFilter } : {}),
  });

  const {
    data: credentialsResponse,
    isLoading: isCredentialsLoading,
    refetch: refetchCredentials,
  } = useGetApiCredentialsQuery();

  const documents: Document[] = documentsResponse?.data.documents ?? [];
  const pagination = documentsResponse?.data.pagination;
  const credentials = credentialsResponse?.data ?? [];

  // Selection Logic
  useEffect(() => {
    if (!selectedDocumentId && documents.length > 0) {
      setSelectedDocumentId(documents[0].id);
    }
  }, [documents, selectedDocumentId]);

  useEffect(() => {
    setPage(1);
  }, [documentStatusFilter, signatureStatusFilter, ocrStatusFilter, perPage]);

  const selectedDocument = useMemo(() => {
    if (!selectedDocumentId) return null;
    return documents.find((item: Document) => item.id === selectedDocumentId) ?? null;
  }, [documents, selectedDocumentId]);

  const { data: documentDetailsResponse, isFetching: isDocumentDetailsFetching } = useGetDocumentByIdQuery(selectedDocumentId as number, {
    skip: !selectedDocumentId,
  });

  const { data: signatureStatusResponse, refetch: refetchSignatureStatus, isFetching: isSignatureStatusFetching } =
    useGetDocumentSignatureStatusQuery(selectedDocumentId as number, {
      skip: !selectedDocumentId,
    });

  const { data: signatureHistoryResponse, refetch: refetchSignatureHistory, isFetching: isSignatureHistoryFetching } =
    useGetSignatureHistoryByDocumentQuery(selectedDocumentId as number, {
      skip: !selectedDocumentId,
    });

  const {
    data: latestOcrResponse,
    error: latestOcrError,
    isFetching: isLatestOcrFetching,
    refetch: refetchLatestOcr,
  } = useGetLatestDocumentOcrQuery(selectedDocumentId as number, {
    skip: !selectedDocumentId,
  });

  // Mutations
  const [uploadDocument, { isLoading: isUploadingDocument }] = useUploadDocumentMutation();
  const [assignSigners, { isLoading: isAssigningSigners }] = useAssignSignersMutation();
  const [sendForSignature, { isLoading: isSendingForSignature }] = useSendForSignatureMutation();
  const [processDocumentOcr, { isLoading: isProcessingOcr }] = useProcessDocumentOcrMutation();
  const [deleteDocument, { isLoading: isDeletingDocument }] = useDeleteDocumentMutation();
  const [deleteCredential] = useDeleteApiCredentialMutation();

  // Handlers
  const handleUploadDocument = async () => {
    if (!uploadTitle.trim() || !uploadFile) {
      toast({
        title: "Upload required fields",
        description: "Please provide both title and file before uploading.",
        variant: "destructive",
      });
      return;
    }

    try {
      await uploadDocument(createDocumentUploadFormData(uploadTitle.trim(), uploadFile)).unwrap();
      setUploadTitle("");
      setUploadFile(null);
      toast({
        title: "Uploaded",
        description: "Document uploaded successfully.",
      });
      await refetchDocuments();
    } catch (error: any) {
      toast({
        title: "Upload failed",
        description: error?.data?.message || "Failed to upload document.",
        variant: "destructive",
      });
    }
  };

  const handleAssignSigners = async () => {
    if (!selectedDocumentId) {
      toast({
        title: "Document required",
        description: "Select a document first.",
        variant: "destructive",
      });
      return;
    }

    const normalized = signers
      .map((row, index) => ({
        name: row.name.trim(),
        email: row.email.trim(),
        signing_order: row.signing_order || index + 1,
      }))
      .filter((row) => row.name && row.email);

    if (normalized.length === 0) {
      toast({
        title: "Signer required",
        description: "Add at least one valid signer with name and email.",
        variant: "destructive",
      });
      return;
    }

    try {
      await assignSigners({ documentId: selectedDocumentId, signers: normalized }).unwrap();
      toast({
        title: "Signers saved",
        description: "Signer assignment completed successfully.",
      });
      await Promise.all([refetchDocuments(), refetchSignatureStatus()]);
    } catch (error: any) {
      toast({
        title: "Signer assignment failed",
        description: error?.data?.message || "Failed to assign signers.",
        variant: "destructive",
      });
    }
  };

  const handleSendForSignature = async () => {
    if (!selectedDocumentId) {
      toast({
        title: "Document required",
        description: "Select a document first.",
        variant: "destructive",
      });
      return;
    }

    const firstSigner = signers.find(s => s.name && s.email) || signers[0];
    setCurrentModalSigner({
      name: firstSigner?.name || "",
      email: firstSigner?.email || ""
    });
    setIsDocuSignFlow(true);
    setIsSignModalOpen(true);
  };

  const handleProcessOcr = async () => {
    if (!selectedDocumentId) {
      toast({
        title: "Document required",
        description: "Select a document first.",
        variant: "destructive",
      });
      return;
    }

    const languageHints = languageHintsText
      .split(",")
      .map((hint) => hint.trim())
      .filter(Boolean)
      .slice(0, 10);

    try {
      await processDocumentOcr({
        documentId: selectedDocumentId,
        payload: {
          feature_type: featureType,
          ...(languageHints.length ? { language_hints: languageHints } : {}),
        },
      }).unwrap();

      toast({
        title: "OCR requested",
        description: "Google Vision processing completed successfully.",
      });

      await Promise.all([refetchDocuments(), refetchLatestOcr()]);
    } catch (error: any) {
      toast({
        title: "OCR failed",
        description: error?.data?.message || "Failed to process OCR.",
        variant: "destructive",
      });
    }
  };

  const handleDeleteDocument = async (id: number) => {
    if (!confirm("Are you sure you want to delete this document? This will also remove associated signatures and OCR results.")) {
      return;
    }

    try {
      await deleteDocument(id).unwrap();
      toast({
        title: "Deleted",
        description: "Document deleted successfully.",
      });
      if (selectedDocumentId === id) {
        setSelectedDocumentId(null);
      }
      await refetchDocuments();
    } catch (error: any) {
      toast({
        title: "Delete failed",
        description: error?.data?.message || "Failed to delete document.",
        variant: "destructive",
      });
    }
  };

  const handleApplyLocalSignature = async (signatureData: string, signerInfo: { name: string; email: string }) => {
    if (isDocuSignFlow && selectedDocumentId) {
      try {
        const payload = {
          ...(emailSubject.trim() ? { email_subject: emailSubject.trim() } : {}),
          ...(emailMessage.trim() ? { email_message: emailMessage.trim() } : {}),
        };

        await sendForSignature({ documentId: selectedDocumentId, payload }).unwrap();
        toast({
          title: "Sent to DocuSign",
          description: `Document has been processed and sent to ${signerInfo.name}.`,
        });
        await Promise.all([refetchDocuments(), refetchSignatureStatus(), refetchSignatureHistory()]);
      } catch (error: any) {
        toast({
          title: "DocuSign failed",
          description: error?.data?.message || "Failed to process DocuSign request.",
          variant: "destructive",
        });
      }
    } else {
      toast({
        title: "Signature Captured",
        description: `Signature for ${signerInfo.name} has been captured locally.`,
      });
    }
    
    setIsDocuSignFlow(false);
  };

  const handleDeleteCredential = async (id: number) => {
    if (!confirm("Are you sure you want to delete this integration? This action cannot be undone.")) {
      return;
    }

    try {
      await deleteCredential(id).unwrap();
      toast({
        title: "Integration removed",
        description: "The service has been disconnected.",
      });
    } catch (error: any) {
      toast({
        title: "Delete failed",
        description: error?.data?.message || "Failed to remove integration.",
        variant: "destructive",
      });
    }
  };

  const openAddModal = () => {
    setEditingCredential(null);
    setIsIntegrationModalOpen(true);
  };

  const openEditModal = (credential: ApiCredential) => {
    setEditingCredential(credential);
    setIsIntegrationModalOpen(true);
  };

  // Warnings & Stats
  const integrationWarnings = [documentsError, latestOcrError]
    .map((error: any) => error?.data?.message)
    .filter((message): message is string => Boolean(message) && isConfigError(message));

  const activeIntegrationsCount = credentials.filter(c => c.is_active).length;
  const pendingDocumentsCount = documents.filter(
    (doc: Document) => doc.signature_status === "pending" || doc.ocr_status === "processing"
  ).length;
  const signedDocumentsCount = documents.filter((doc: Document) => doc.document_status === "signed").length;

  // Global Loading State
  if ((isDocumentsLoading && !documentsResponse) || (isCredentialsLoading && !credentialsResponse)) {
    return (
      <ProtectedRoute requiredRole={ROLES.ADMIN}>
        <div className="min-h-screen bg-linear-to-br from-background via-muted/30 to-accent/10 flex items-center justify-center">
           <IntegrationsLoader />
        </div>
      </ProtectedRoute>
    );
  }

  return (
    <ProtectedRoute requiredRole={ROLES.ADMIN}>
      <div className="min-h-screen bg-transparent overflow-x-hidden">
        <div className="relative z-10 py-6 md:py-8 px-4 sm:px-6 lg:px-8 space-y-8 max-w-full overflow-x-hidden">
          
          {/* Header */}
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div className="space-y-1">
              <h1 className="text-3xl md:text-4xl font-extrabold text-primary tracking-tight">Integrations</h1>
              <p className="text-muted-foreground">Manage third-party service connections and medical-legal API configurations</p>
            </div>
            <div className="flex items-center gap-3 w-full md:w-auto">
              <Button 
                variant="outline" 
                onClick={() => { refetchDocuments(); refetchCredentials(); }} 
                className="flex-1 md:flex-none border-primary/20 bg-background/50 backdrop-blur-sm"
              >
                <RefreshCw className={`h-4 w-4 mr-2 ${(isDocumentsFetching || isCredentialsLoading) ? "animate-spin" : ""}`} />
                Sync Data
              </Button>
              <Button 
                className="flex-1 md:flex-none bg-primary hover:bg-primary/90 shadow-xl shadow-primary/20 transition-all active:scale-95"
                onClick={openAddModal}
              >
                <Activity className="h-4 w-4 mr-2" />
                New Integration
              </Button>
            </div>
          </div>

          {/* Configuration Warnings */}
          {integrationWarnings.length > 0 && (
            <Alert variant="destructive" className="bg-rose-500/10 border-rose-500/30">
              <AlertCircle className="h-4 w-4" />
              <AlertTitle>Integration Configuration Warning</AlertTitle>
              <AlertDescription>
                {integrationWarnings[0]}
              </AlertDescription>
            </Alert>
          )}

          {/* Stats Section (Styled) */}
          <IntegrationStats 
            activeIntegrations={activeIntegrationsCount}
            totalDocuments={pagination?.total ?? 0}
            pendingDocuments={pendingDocumentsCount}
            signedDocuments={signedDocumentsCount}
          />

          {/* Main Content Tabs */}
          <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
            <div className="overflow-x-auto pb-2 -mx-4 px-4 md:mx-0 md:px-0 scrollbar-hide">
              <TabsList className="flex w-max md:w-full md:grid h-auto grid-cols-2 md:grid-cols-3 lg:grid-cols-6 bg-card/50 backdrop-blur-sm border border-border/50 p-1 gap-1">
                <TabsTrigger value="overview" className="px-4 py-2 text-xs sm:text-sm">Active Services</TabsTrigger>
                <TabsTrigger value="documents" className="px-4 py-2 text-xs sm:text-sm">Repository</TabsTrigger>
                <TabsTrigger value="signature" className="px-4 py-2 text-xs sm:text-sm">Signatures</TabsTrigger>
                <TabsTrigger value="ocr" className="px-4 py-2 text-xs sm:text-sm">AI OCR</TabsTrigger>
                <TabsTrigger value="api-keys" className="px-4 py-2 text-xs sm:text-sm">API Keys</TabsTrigger>
                <TabsTrigger value="webhooks" className="px-4 py-2 text-xs sm:text-sm">Webhooks</TabsTrigger>
              </TabsList>
            </div>

            {/* Overview Tab (Dynamic Cards) */}
            <TabsContent value="overview" className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
              {credentials.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-20 bg-card/30 backdrop-blur-sm rounded-2xl border border-dashed border-border/50">
                  <Activity className="h-12 w-12 text-muted-foreground mb-4 opacity-20" />
                  <h3 className="text-xl font-semibold text-muted-foreground">No integrations found</h3>
                  <p className="text-sm text-muted-foreground mb-6">Start by adding a third-party service integration.</p>
                  <Button onClick={openAddModal} variant="outline" className="border-primary/20 hover:bg-primary/5">
                    Connect First Service
                  </Button>
                </div>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {credentials.map((credential) => {
                    const config = PROVIDER_CONFIG[credential.provider] || PROVIDER_CONFIG.other;
                    const IconComponent = config.icon;
                    return (
                      <Card
                        key={credential.id}
                        className="bg-card/50 backdrop-blur-sm border-border/50 hover:shadow-xl transition-all duration-300 group"
                      >
                        <CardHeader className="pb-4">
                          <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                            <div className="flex items-start gap-3 min-w-0">
                              <div className="p-3 bg-primary/10 rounded-xl group-hover:bg-primary/20 transition-colors shrink-0">
                                <IconComponent className="h-6 w-6 text-primary" />
                              </div>
                              <div className="min-w-0">
                                <CardTitle className="text-base md:text-lg font-bold break-all leading-tight">
                                  {credential.name}
                                </CardTitle>
                                <CardDescription className="text-xs mt-1 font-medium text-primary/60">{config.type}</CardDescription>
                              </div>
                            </div>
                            <Badge
                              variant={credential.is_active ? "secondary" : "outline"}
                              className={cn(
                                "shrink-0",
                                credential.is_active ? "bg-emerald-500/10 text-emerald-600 border-emerald-500/20" : ""
                              )}
                            >
                              {credential.is_active ? "Connected" : "Inactive"}
                            </Badge>
                          </div>
                        </CardHeader>
                        <CardContent>
                          <p className="text-sm text-muted-foreground mb-6 leading-relaxed">
                            {config.description}
                          </p>
                          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-4 border-t border-border/50">
                            <span className="text-[10px] uppercase font-bold text-muted-foreground opacity-60">
                              Added: {formatDate(credential.created_at)}
                            </span>
                            <div className="flex items-center gap-2">
                              {["signature", "docusign", "ocr"].includes(credential.provider) ? (
                                <Button 
                                  variant="outline" 
                                  size="sm" 
                                  className="h-8 px-3 text-[10px] font-bold border-primary/20 hover:bg-primary/5 text-primary"
                                  onClick={() => setActiveTab(["signature", "docusign"].includes(credential.provider) ? 'signature' : 'ocr')}
                                >
                                  Workflow
                                </Button>
                              ) : null}
                              <div className="flex items-center bg-muted/30 rounded-lg p-0.5 border border-border/30">
                                <Button 
                                  variant="ghost" 
                                  size="sm" 
                                  className="h-7 w-7 p-0 hover:bg-background"
                                  onClick={() => openEditModal(credential)}
                                >
                                  <Settings className="h-3.5 w-3.5" />
                                </Button>
                                <Button 
                                  variant="ghost" 
                                  size="sm" 
                                  className="h-7 w-7 p-0 text-rose-500 hover:bg-rose-500/10"
                                  onClick={() => handleDeleteCredential(credential.id)}
                                >
                                  <Trash2 className="h-3.5 w-3.5" />
                                </Button>
                              </div>
                            </div>
                          </div>
                        </CardContent>
                      </Card>
                    );
                  })}
                </div>
              )}
            </TabsContent>

            <TabsContent value="documents" className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
              <DocumentUploadSection 
                uploadTitle={uploadTitle}
                setUploadTitle={setUploadTitle}
                setUploadFile={setUploadFile}
                handleUploadDocument={handleUploadDocument}
                isUploadingDocument={isUploadingDocument}
              />

              <DocumentListTable 
                documents={documents}
                isLoading={isDocumentsFetching}
                selectedDocumentId={selectedDocumentId}
                setSelectedDocumentId={setSelectedDocumentId}
                handleDeleteDocument={handleDeleteDocument}
                setIsSignModalOpen={setIsSignModalOpen}
                documentStatusFilter={documentStatusFilter}
                setDocumentStatusFilter={setDocumentStatusFilter}
                signatureStatusFilter={signatureStatusFilter}
                setSignatureStatusFilter={setSignatureStatusFilter}
                ocrStatusFilter={ocrStatusFilter}
                setOcrStatusFilter={setOcrStatusFilter}
                perPage={perPage}
                setPerPage={setPerPage}
                page={page}
                setPage={setPage}
                pagination={pagination}
                isDeletingDocument={isDeletingDocument}
              />
            </TabsContent>

            <TabsContent value="signature" className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
              <SignatureWorkflow 
                documents={documents}
                selectedDocument={selectedDocument}
                selectedDocumentId={selectedDocumentId}
                setSelectedDocumentId={setSelectedDocumentId}
                refetchSignatureStatus={refetchSignatureStatus}
                refetchSignatureHistory={refetchSignatureHistory}
                isSignatureStatusFetching={isSignatureStatusFetching}
                isSignatureHistoryFetching={isSignatureHistoryFetching}
                signatureStatusResponse={signatureStatusResponse}
                signatureHistoryResponse={signatureHistoryResponse}
                signers={signers}
                setSigners={setSigners}
                initialSignerRow={initialSignerRow}
                handleAssignSigners={handleAssignSigners}
                isAssigningSigners={isAssigningSigners}
                emailSubject={emailSubject}
                setEmailSubject={setEmailSubject}
                emailMessage={emailMessage}
                setEmailMessage={setEmailMessage}
                handleSendForSignature={handleSendForSignature}
                isSendingForSignature={isSendingForSignature}
              />
            </TabsContent>

            <TabsContent value="ocr" className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
              <OcrWorkflow 
                documents={documents}
                selectedDocument={selectedDocument}
                selectedDocumentId={selectedDocumentId}
                setSelectedDocumentId={setSelectedDocumentId}
                featureType={featureType}
                setFeatureType={setFeatureType}
                languageHintsText={languageHintsText}
                setLanguageHintsText={setLanguageHintsText}
                handleProcessOcr={handleProcessOcr}
                isProcessingOcr={isProcessingOcr}
                refetchLatestOcr={refetchLatestOcr}
                isLatestOcrFetching={isLatestOcrFetching}
                latestOcrResponse={latestOcrResponse}
                documentDetailsResponse={documentDetailsResponse}
                isDocumentDetailsFetching={isDocumentDetailsFetching}
              />
            </TabsContent>

            {/* API Keys Tab (Dynamic) */}
            <TabsContent value="api-keys" className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
              <Card className="bg-card/50 backdrop-blur-sm border-border/50">
                <CardHeader className="flex flex-row items-center justify-between">
                  <div>
                    <CardTitle>API Credentials Management</CardTitle>
                    <CardDescription>Securely manage your third-party API keys and secrets.</CardDescription>
                  </div>
                  <Button onClick={openAddModal} size="sm" className="bg-primary hover:bg-primary/90">
                    <Key className="h-4 w-4 mr-2" />
                    New Credential
                  </Button>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader className="bg-muted/50">
                      <TableRow>
                        <TableHead className="font-bold text-primary">Service Name</TableHead>
                        <TableHead className="hidden sm:table-cell">Provider</TableHead>
                        <TableHead className="hidden md:table-cell">Status</TableHead>
                        <TableHead className="hidden lg:table-cell">Added Date</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {credentials.length === 0 ? (
                        <TableRow>
                          <TableCell colSpan={5} className="text-center py-10 text-muted-foreground">
                            No credentials configured yet.
                          </TableCell>
                        </TableRow>
                      ) : (
                        credentials.map((key) => (
                          <TableRow key={key.id} className="hover:bg-muted/30 transition-colors">
                            <TableCell className="font-medium">
                              <div className="flex flex-col">
                                <div className="flex items-center gap-2">
                                  <Key className="h-4 w-4 text-primary opacity-60" />
                                  {key.name}
                                </div>
                                <span className="text-[10px] sm:hidden opacity-70 mt-1 uppercase tracking-widest">{key.provider}</span>
                              </div>
                            </TableCell>
                            <TableCell className="hidden sm:table-cell">
                              <Badge variant="outline" className="bg-primary/5 capitalize font-bold text-[10px]">
                                {key.provider}
                                </Badge>
                            </TableCell>
                            <TableCell className="hidden md:table-cell">
                              <Badge 
                                variant={key.is_active ? "secondary" : "outline"}
                                className={key.is_active ? "bg-emerald-500/10 text-emerald-600 font-bold text-[10px]" : "font-bold text-[10px]"}
                              >
                                {key.is_active ? "Active" : "Disabled"}
                              </Badge>
                            </TableCell>
                            <TableCell className="hidden lg:table-cell text-xs text-muted-foreground">{formatDate(key.created_at)}</TableCell>
                            <TableCell className="text-right">
                              <div className="flex items-center justify-end gap-2">
                                <Button 
                                  variant="ghost" 
                                  size="sm"
                                  className="h-8 w-8 p-0"
                                  onClick={() => openEditModal(key)}
                                  title="View/Edit"
                                >
                                  <Settings className="h-4 w-4" />
                                </Button>
                                <Button 
                                  variant="ghost" 
                                  size="sm"
                                  onClick={() => openEditModal(key)}
                                >
                                  Rotate
                                </Button>
                                <Button 
                                  variant="ghost" 
                                  size="sm" 
                                  className="text-rose-500 hover:bg-rose-500/10"
                                  onClick={() => handleDeleteCredential(key.id)}
                                >
                                  Revoke
                                </Button>
                              </div>
                            </TableCell>
                          </TableRow>
                        ))
                      )}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </TabsContent>

            {/* Webhooks Tab (Old Design) */}
            <TabsContent value="webhooks" className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
              <Card className="bg-card/50 backdrop-blur-sm border-border/50">
                <CardHeader>
                  <CardTitle>Webhook Configuration</CardTitle>
                  <CardDescription>Configure webhook endpoints for real-time notifications</CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                  <div className="space-y-4">
                    <div className="space-y-2">
                      <Label htmlFor="webhook-url">Webhook URL</Label>
                      <Input
                        id="webhook-url"
                        placeholder="https://your-app.com/webhooks"
                        defaultValue="https://fayda.com/api/webhooks/integrations"
                        className="bg-background/50 border-border/50"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="secret-key">Secret Key</Label>
                      <Input
                        id="secret-key"
                        type="password"
                        placeholder="Enter webhook secret key"
                        defaultValue="••••••••••••••••"
                        className="bg-background/50 border-border/50"
                      />
                    </div>
                    <div className="flex items-center space-x-2">
                      <Switch id="webhook-enabled" defaultChecked />
                      <Label htmlFor="webhook-enabled">Enable webhooks</Label>
                    </div>
                  </div>
                  <Button className="bg-primary hover:bg-primary/90 shadow-lg shadow-primary/20">Save Webhook Settings</Button>
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>
        </div>

        {/* Integration Form Modal */}
        <IntegrationFormModal
          isOpen={isIntegrationModalOpen}
          onClose={() => setIsIntegrationModalOpen(false)}
          editingCredential={editingCredential}
        />

        {/* Global Signature Modal */}
        <SignatureModal
          isOpen={isSignModalOpen}
          onClose={() => {
            setIsSignModalOpen(false);
            setCurrentModalSigner(null);
          }}
          onSave={handleApplyLocalSignature}
          documentTitle={selectedDocument?.title}
          initialSignerName={currentModalSigner?.name || ""}
          initialSignerEmail={currentModalSigner?.email || ""}
          isLoading={isSendingForSignature}
        />
      </div>
    </ProtectedRoute>
  );
}