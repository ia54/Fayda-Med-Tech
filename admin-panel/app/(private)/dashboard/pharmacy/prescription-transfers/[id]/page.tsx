'use client'
import Link from 'next/link'
import {useParams} from 'next/navigation'
import {useAuth} from '@/hooks/useAuth'
import {PrescriptionTransferReview} from '@/components/pharmacy/prescription-transfer-review'
export default function TransferDetail(){
 const id=Number(useParams().id);const {user}=useAuth()
 if(!['pharmacist','pharmacy_technician'].includes(user?.role||''))return <p>Pharmacy staff access is required.</p>
 return <div className="space-y-5 min-w-0"><Link className="underline" href="/dashboard/pharmacy/prescription-transfers">Back to prescription transfers</Link><PrescriptionTransferReview key={`${user?.id}-${id}`} id={id} actorId={Number(user?.id)} role={user?.role||''}/></div>
}
