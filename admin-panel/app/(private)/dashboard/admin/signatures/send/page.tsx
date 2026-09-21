import { SendSignatureRequest } from "@/components/SendSignatureRequest"

export default function SendSignaturePage() {
  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      <div>
        <h1 className="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">
          Send for Signature
        </h1>
        <p className="text-muted-foreground mt-1">
          Select a document and assign it to a client or party to securely sign in-app.
        </p>
      </div>
      <SendSignatureRequest />
    </div>
  )
}
