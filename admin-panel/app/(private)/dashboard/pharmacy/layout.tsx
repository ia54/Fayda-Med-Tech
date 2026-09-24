import { AuthGuard } from "@/components/guards/auth-guard";
export default function PharmacyLayout({children}:{children:React.ReactNode}) { return <AuthGuard roles={["pharmacist","pharmacy_technician","medical_biller","admin"]}>{children}</AuthGuard> }
