'use client';

import { useEffect, useState } from 'react';
import Image from 'next/image';
import { useSelector } from 'react-redux';
import { RootState } from '@/store/store';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { FileText } from 'lucide-react';

export function AuthenticatedDocumentPreview({ id, title }: { id: number; title: string }) {
  const token = useSelector((state: RootState) => state.auth.token?.access_token);
  const [mimeType, setMimeType] = useState('');
  const [open, setOpen] = useState(false); const [url, setUrl] = useState(''); const [error, setError] = useState('');
  useEffect(() => {
    if (!open) return;
    const controller = new AbortController(); let objectUrl = '';
    setUrl(''); setError('');
    const base = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost:8000/api';
    fetch(`${base}/documents/${id}/preview`, { headers: { Authorization: `Bearer ${token}`, Accept: 'application/pdf,image/*' }, cache: 'no-store', signal: controller.signal })
      .then(async response => { if (!response.ok) throw new Error(response.status === 401 ? 'Your session expired. Sign in again.' : 'This document is unavailable or you do not have access.'); return response.blob(); })
      .then(blob => { if (!controller.signal.aborted) { objectUrl = URL.createObjectURL(blob); setMimeType(blob.type.split(';')[0]); setUrl(objectUrl); } })
      .catch(err => { if (!controller.signal.aborted) setError(err instanceof Error ? err.message : 'Unable to load document.'); });
    return () => { controller.abort(); if (objectUrl) URL.revokeObjectURL(objectUrl); };
  }, [id, token, open]);
  return <><Button variant="outline" size="icon" className="h-8 w-8" aria-label={`Preview ${title}`} onClick={() => setOpen(true)}><FileText className="h-4 w-4" /></Button>
    <Dialog open={open} onOpenChange={setOpen}><DialogContent style={{ width: "min(90vw, 900px)", maxWidth: "90vw" }}><DialogHeader><DialogTitle>{title}</DialogTitle><DialogDescription>Protected document preview</DialogDescription></DialogHeader>
      {error ? <p role="alert">{error}</p> : url ? <>{mimeType === 'application/pdf' ? <iframe title={title} src={url} className="h-[65vh] w-full border rounded" /> : ['image/png', 'image/jpeg'].includes(mimeType) ? <Image unoptimized width={900} height={700} alt={title} src={url} className="max-h-[65vh] w-full object-contain" /> : <p>Preview is unavailable for this file type. Download it to view.</p>}<a href={url} download={title} className="underline">Download document</a></> : <p role="status">Loading document…</p>}
    </DialogContent></Dialog>
  </>;
}
