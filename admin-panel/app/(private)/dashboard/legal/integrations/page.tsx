"use client";

import { useEffect, useMemo, useState } from "react";
import { ProtectedRoute } from "@/components/protected-route";
import { ROLES } from "@/lib/roleConstants";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useToast } from "@/hooks/use-toast";
import { SignatureModal } from "@/components/SignatureModal";
import { 
  RefreshCw, 
  Activity, 
  Bot, 
  FileText, 
  Zap,
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
  ApiCredential,
} from "@/store/api/apiCredentialsApiSlice";

// Fragments (Using absolute imports from admin integrations directory to reuse components)
import { IntegrationsLoader } from "../../admin/integrations/fragments/IntegrationsLoader";
import { IntegrationStats } from "../../admin/integrations/fragments/IntegrationStats";
import { DocumentUploadSection } from "../../admin/integrations/fragments/DocumentUploadSection";
import { DocumentListTable } from "../../admin/integrations/fragments/DocumentListTable";
import { SignatureWorkflow } from "../../admin/integrations/fragments/SignatureWorkflow";
import { OcrWorkflow } from "../../admin/integrations/fragments/OcrWorkflow";

// Utils
import { formatDate } from "../../admin/integrations/utils";

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
  other: { icon: Activity, type: "Generic", description: "Third-party service integration" },
};

import { useAuth } from "@/hooks/useAuth";

export default function FirmIntegrationsManagementPage() {
  return (
    <ProtectedRoute requiredRole={ROLES.FIRM_ADMIN}>
      <FirmIntegrationsManagementContent />
    </ProtectedRoute>
  );
}

function FirmIntegrationsManagementContent() {
  const { user } = useAuth();
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

  // Queries
  const {
    data: documentsResponse,
    isLoading: isDocumentsLoading,
    isFetching: isDocumentsFetching,
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
    if (!confirm("Are you sure you want to delete this document?")) {
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
          description: `Document sent to ${signerInfo.name}.`,
        });
        await Promise.all([refetchDocuments(), refetchSignatureStatus(), refetchSignatureHistory()]);
      } catch (error: any) {
        toast({
          title: "DocuSign failed",
          description: error?.data?.message || "Failed to process DocuSign request.",
          variant: "destructive",
        });
      }
    }
    setIsDocuSignFlow(false);
  };

  const activeIntegrationsCount = credentials.filter(c => c.is_active).length;
  const pendingDocumentsCount = documents.filter(
    (doc: Document) => doc.signature_status === "pending" || doc.ocr_status === "processing"
  ).length;
  const signedDocumentsCount = documents.filter((doc: Document) => doc.document_status === "signed").length;

  if ((isDocumentsLoading && !documentsResponse) || (isCredentialsLoading && !credentialsResponse)) {
    return (
      <ProtectedRoute requiredRole={ROLES.FIRM_ADMIN}>
        <div className="min-h-screen bg-transparent flex items-center justify-center">
           <IntegrationsLoader />
        </div>
      </ProtectedRoute>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-emerald-50/50 via-white to-green-50/30 dark:from-emerald-950/20 dark:via-slate-950 dark:to-green-950/20 overflow-x-hidden">
      <div className="relative z-10 py-6 md:py-8 px-4 sm:px-6 lg:px-8 space-y-8 max-w-full overflow-x-hidden">
        
        {/* Header */}
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-emerald-900 dark:text-white">Firm Integrations</h1>
            <p className="text-emerald-600 dark:text-slate-300 mt-2">Manage your firm's document workflows and integrations</p>
          </div>
          <div className="flex items-center gap-2">
            <Button 
              variant="outline" 
              onClick={() => { refetchDocuments(); refetchCredentials(); }} 
              className="border-emerald-200 dark:border-emerald-800 hover:bg-emerald-50 dark:hover:bg-emerald-900/50 bg-transparent text-emerald-700 dark:text-white"
            >
              <RefreshCw className={`h-4 w-4 mr-2 ${(isDocumentsFetching || isCredentialsLoading) ? "animate-spin" : ""}`} />
              Refresh
            </Button>
          </div>
        </div>

        {/* Stats Section */}
        <IntegrationStats 
          activeIntegrations={activeIntegrationsCount}
          totalDocuments={pagination?.total ?? 0}
          pendingDocuments={pendingDocumentsCount}
          signedDocuments={signedDocumentsCount}
        />

        {/* Main Content Tabs */}
        <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
          <TabsList className="grid w-full grid-cols-2 md:grid-cols-4 bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border border-emerald-100 dark:border-emerald-900/50 p-1">
            <TabsTrigger value="overview">Active Services</TabsTrigger>
            <TabsTrigger value="documents">Documents</TabsTrigger>
            <TabsTrigger value="signature">Signatures</TabsTrigger>
            <TabsTrigger value="ocr">AI OCR</TabsTrigger>
          </TabsList>

          <TabsContent value="overview" className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {credentials.map((credential) => {
                const config = PROVIDER_CONFIG[credential.provider] || PROVIDER_CONFIG.other;
                const IconComponent = config.icon;
                return (
                  <Card
                    key={credential.id}
                    className="bg-white/70 dark:bg-slate-900/70 backdrop-blur-sm border-emerald-100 dark:border-emerald-900/50 hover:shadow-xl transition-all duration-300 group"
                  >
                    <CardHeader className="pb-4">
                      <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                        <div className="flex items-start gap-3 min-w-0 flex-1">
                          <div className="p-3 bg-emerald-500/10 dark:bg-emerald-500/20 rounded-xl shrink-0">
                            <IconComponent className="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                          </div>
                          <div className="min-w-0 flex-1">
                            <CardTitle className="text-lg font-bold break-all leading-tight text-emerald-900 dark:text-white">
                              {credential.name}
                            </CardTitle>
                            <CardDescription className="text-emerald-600 dark:text-slate-400">{config.type}</CardDescription>
                          </div>
                        </div>
                        <Badge
                          variant={credential.is_active ? "secondary" : "outline"}
                          className={`w-fit shrink-0 whitespace-nowrap ${
                            credential.is_active 
                              ? "bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border-emerald-500/20" 
                              : "dark:border-emerald-800 dark:text-slate-200"
                          }`}
                        >
                          {credential.is_active ? "Connected" : "Inactive"}
                        </Badge>
                      </div>
                    </CardHeader>
                    <CardContent>
                      <p className="text-sm text-muted-foreground mb-6 leading-relaxed">
                        {config.description}
                      </p>
                      <div className="flex items-center justify-between pt-4 border-t border-border/50">
                        <span className="text-xs text-muted-foreground">Status: {credential.is_active ? "Verified" : "Action Required"}</span>
                        <div className="flex items-center gap-2">
                          <Button 
                            variant="outline" 
                            size="sm" 
                            className="border-emerald-200 dark:border-emerald-800 hover:bg-emerald-50 dark:hover:bg-emerald-900/50 text-emerald-700 dark:text-white"
                            onClick={() => setActiveTab(["signature", "docusign"].includes(credential.provider) ? 'signature' : 'ocr')}
                          >
                            Go to Workflow
                          </Button>
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                );
              })}
            </div>
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
        </Tabs>
      </div>

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
  );
}
