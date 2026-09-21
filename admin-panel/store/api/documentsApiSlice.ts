import { apiSlice, TAG_TYPES } from "./apiSlice";

export type DocumentStatus = "draft" | "sent_for_signature" | "signed" | "cancelled";
export type SignatureStatus = "not_sent" | "pending" | "completed" | "declined" | "voided";
export type OcrStatus = "not_processed" | "processing" | "processed" | "failed";
export type DocumentSignerStatus = "pending" | "sent" | "signed" | "declined" | "failed";
export type SignatureEventStatus = "pending" | "completed" | "declined" | "failed";

export interface DocumentSigner {
    id: number;
    document_id: number;
    user_id: number | null;
    name: string;
    email: string;
    signing_order: number;
    status: DocumentSignerStatus;
    docusign_recipient_id: string | null;
    signed_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface SignatureEvent {
    id: number;
    document_id: number;
    document_signer_id: number | null;
    provider: string;
    provider_envelope_id: string | null;
    provider_event: string | null;
    status: SignatureEventStatus;
    signed_file_path: string | null;
    signed_file_url: string | null;
    provider_payload: unknown;
    processed_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface OcrResult {
    id: number;
    document_id: number;
    processed_by: number | null;
    provider: string;
    status: "processing" | "processed" | "failed";
    extracted_text: string | null;
    full_response: unknown;
    metadata: Record<string, unknown> | null;
    processed_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface Document {
    id: number;
    uploaded_by: number | null;
    title: string;
    original_name: string;
    filename: string;
    mime_type: string;
    size: number;
    path: string;
    url: string;
    document_status: DocumentStatus;
    signature_status: SignatureStatus;
    ocr_status: OcrStatus;
    docusign_envelope_id: string | null;
    sent_at: string | null;
    signed_at: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
    signers?: DocumentSigner[];
    signatures?: SignatureEvent[];
    ocr_results?: OcrResult[];
}

interface Pagination {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

export interface DocumentsResponse {
    status: boolean;
    message: string;
    data: {
        documents: Document[];
        pagination: Pagination;
    };
}

export interface DocumentResponse {
    status: boolean;
    message: string;
    data: Document;
}

export interface SignatureStatusResponse {
    status: boolean;
    message: string;
    data: {
        document_id: number;
        document_status: DocumentStatus;
        signature_status: SignatureStatus;
        envelope_id: string | null;
        signers: DocumentSigner[];
        signature_events: SignatureEvent[];
    };
}

export interface SignatureHistoryResponse {
    status: boolean;
    message: string;
    data: {
        document_id: number;
        signature_status: SignatureStatus;
        signatures: SignatureEvent[];
        signers: DocumentSigner[];
    };
}

export interface OcrLatestResponse {
    status: boolean;
    message: string;
    data: OcrResult;
}

export interface GenericResponse {
    status: boolean;
    message: string;
}

export interface SendForSignatureResponse {
    status: boolean;
    message: string;
    data: {
        document_id: number;
        envelope_id: string | null;
        signature_status: SignatureStatus;
    };
}

export interface OcrProcessResponse {
    status: boolean;
    message: string;
    data: {
        document_id: number;
        ocr_result_id: number;
        ocr_status: OcrStatus;
        extracted_text: string;
    };
}

export interface GetDocumentsParams {
    page?: number;
    per_page?: number;
    document_status?: DocumentStatus;
    signature_status?: SignatureStatus;
    ocr_status?: OcrStatus;
}

export interface AssignSignerInput {
    name: string;
    email: string;
    user_id?: number;
    signing_order?: number;
}

export interface SendForSignatureInput {
    email_subject?: string;
    email_message?: string;
}

export interface ProcessOcrInput {
    feature_type?: "TEXT_DETECTION" | "DOCUMENT_TEXT_DETECTION";
    language_hints?: string[];
}

export const documentsApiSlice = apiSlice.injectEndpoints({
    endpoints: (builder) => ({
        getDocuments: builder.query<DocumentsResponse, GetDocumentsParams>({
            query: ({ page = 1, per_page = 10, document_status, signature_status, ocr_status } = {}) => ({
                url: "/documents",
                method: "GET",
                params: {
                    page,
                    per_page,
                    ...(document_status ? { document_status } : {}),
                    ...(signature_status ? { signature_status } : {}),
                    ...(ocr_status ? { ocr_status } : {}),
                },
            }),
            providesTags: (result) =>
                result
                    ? [
                        ...result.data.documents.map(({ id }) => ({
                            type: TAG_TYPES.DOCUMENT,
                            id,
                        })),
                        { type: TAG_TYPES.DOCUMENT, id: "LIST" },
                    ]
                    : [{ type: TAG_TYPES.DOCUMENT, id: "LIST" }],
        }),

        getDocumentById: builder.query<DocumentResponse, number>({
            query: (id) => ({
                url: `/documents/${id}`,
                method: "GET",
            }),
            providesTags: (result, error, id) => [{ type: TAG_TYPES.DOCUMENT, id }],
        }),

        uploadDocument: builder.mutation<DocumentResponse, FormData>({
            query: (formData) => ({
                url: "/documents",
                method: "POST",
                body: formData,
            }),
            invalidatesTags: [{ type: TAG_TYPES.DOCUMENT, id: "LIST" }],
        }),

        assignSigners: builder.mutation<DocumentResponse, { documentId: number; signers: AssignSignerInput[] }>({
            query: ({ documentId, signers }) => ({
                url: `/documents/${documentId}/signers`,
                method: "POST",
                body: { signers },
            }),
            invalidatesTags: (result, error, { documentId }) => [
                { type: TAG_TYPES.DOCUMENT, id: documentId },
                { type: TAG_TYPES.DOCUMENT, id: "LIST" },
                { type: TAG_TYPES.SIGNATURE, id: documentId },
            ],
        }),

        sendForSignature: builder.mutation<SendForSignatureResponse, { documentId: number; payload?: SendForSignatureInput }>({
            query: ({ documentId, payload }) => ({
                url: `/documents/${documentId}/send-for-signature`,
                method: "POST",
                body: payload || {},
            }),
            invalidatesTags: (result, error, { documentId }) => [
                { type: TAG_TYPES.DOCUMENT, id: documentId },
                { type: TAG_TYPES.DOCUMENT, id: "LIST" },
                { type: TAG_TYPES.SIGNATURE, id: documentId },
            ],
        }),

        signInApp: builder.mutation<DocumentResponse, { documentId: number; formData: FormData }>({
            query: ({ documentId, formData }) => ({
                url: `/documents/${documentId}/sign-in-app`,
                method: "POST",
                body: formData,
            }),
            invalidatesTags: (result, error, { documentId }) => [
                { type: TAG_TYPES.DOCUMENT, id: documentId },
                { type: TAG_TYPES.DOCUMENT, id: "LIST" },
                { type: TAG_TYPES.SIGNATURE, id: documentId },
            ],
        }),

        getDocumentSignatureStatus: builder.query<SignatureStatusResponse, number>({
            query: (documentId) => ({
                url: `/documents/${documentId}/signature-status`,
                method: "GET",
            }),
            providesTags: (result, error, documentId) => [
                { type: TAG_TYPES.SIGNATURE, id: documentId },
            ],
        }),

        getSignatureHistoryByDocument: builder.query<SignatureHistoryResponse, number>({
            query: (documentId) => ({
                url: `/signatures/document/${documentId}`,
                method: "GET",
            }),
            providesTags: (result, error, documentId) => [
                { type: TAG_TYPES.SIGNATURE, id: documentId },
            ],
        }),

        processDocumentOcr: builder.mutation<OcrProcessResponse, { documentId: number; payload?: ProcessOcrInput }>({
            query: ({ documentId, payload }) => ({
                url: `/ocr/documents/${documentId}/process`,
                method: "POST",
                body: payload || {},
            }),
            invalidatesTags: (result, error, { documentId }) => [
                { type: TAG_TYPES.DOCUMENT, id: documentId },
                { type: TAG_TYPES.DOCUMENT, id: "LIST" },
                { type: TAG_TYPES.OCR_RESULT, id: documentId },
            ],
        }),

        getLatestDocumentOcr: builder.query<OcrLatestResponse, number>({
            query: (documentId) => ({
                url: `/ocr/documents/${documentId}/latest`,
                method: "GET",
            }),
            providesTags: (result, error, documentId) => [{ type: TAG_TYPES.OCR_RESULT, id: documentId }],
        }),

        deleteDocument: builder.mutation<GenericResponse, number>({
            query: (id) => ({
                url: `/documents/${id}`,
                method: "DELETE",
            }),
            invalidatesTags: [{ type: TAG_TYPES.DOCUMENT, id: "LIST" }],
        }),
    }),
});

export const {
    useGetDocumentsQuery,
    useGetDocumentByIdQuery,
    useUploadDocumentMutation,
    useAssignSignersMutation,
    useSendForSignatureMutation,
    useSignInAppMutation,
    useGetDocumentSignatureStatusQuery,
    useGetSignatureHistoryByDocumentQuery,
    useProcessDocumentOcrMutation,
    useGetLatestDocumentOcrQuery,
    useDeleteDocumentMutation,
} = documentsApiSlice;

export function createDocumentUploadFormData(title: string, file: File): FormData {
    const formData = new FormData();
    formData.append("title", title);
    formData.append("file", file);
    return formData;
}
