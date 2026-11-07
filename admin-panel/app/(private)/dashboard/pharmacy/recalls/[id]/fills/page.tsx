import {redirect} from 'next/navigation'

export default async function RecallFills({params}:{params:Promise<{id:string}>}){
 const {id}=await params
 redirect(`/dashboard/pharmacy/recalls/${encodeURIComponent(id)}`)
}
