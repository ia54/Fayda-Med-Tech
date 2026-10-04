import LegalDocumentUpload from "./LegalDocumentUpload"

export default async function LegalDocumentUploadPage({ searchParams }: { searchParams: Promise<{ case_id?: string | string[] }> }) {
  const params = await searchParams
  return <LegalDocumentUpload caseId={typeof params.case_id === "string" ? params.case_id : undefined} />
}
