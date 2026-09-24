'use client';

import { useEffect, useState } from 'react';
import Image from 'next/image';
import { useSelector } from 'react-redux';
import { RootState } from '@/store/store';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { FileText } from 'lucide-react';
import { fetchProtectedDocument, type DocumentArtifact } from '@/lib/protectedDocument';

export function AuthenticatedDocumentPreview({ id, title }: { id: number; title: string }) {
  const token = useSelector((state: RootState) => state.auth.token?.access_token);
  const [mimeType, setMimeType] = useState('');
  const [open, setOpen] = useState(false);
  const [url, setUrl] = useState('');
  const [error, setError] = useState('');
  const [artifact, setArtifact] = useState<DocumentArtifact>('document');
  const [version, setVersion] = useState('original');
  const [hasCertificate, setHasCertificate] = useState(false);
  const [retry, setRetry] = useState(0);
  useEffect(() => {
    if (!open) return;
    const controller = new AbortController(); let objectUrl = '';
    setUrl(''); setError(''); setHasCertificate(false); setVersion('original');
    const base = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://localhost:8000/api';
    fetchProtectedDocument(base, id, token, artifact, controller.signal)
      .then(result => {
        if (!controller.signal.aborted) {
          objectUrl = URL.createObjectURL(result.blob);
          setMimeType(result.blob.type.split(';')[0]); setUrl(objectUrl);
          setVersion(result.version); setHasCertificate(result.hasCertificate);
        }
      })
      .catch(err => { if (!controller.signal.aborted) setError(err instanceof Error ? err.message : 'Unable to load document.'); });
    return () => { controller.abort(); if (objectUrl) URL.revokeObjectURL(objectUrl); };
  }, [id, token, open, artifact, retry]);
  const label = version === 'certificate' ? 'Completion certificate' : version === 'signed' ? 'Signed document' : 'Original document';
  const filename = version === 'original' ? title : `${title.replace(/\.pdf$/i, '')}-${version}.pdf`;
  return <><Button variant="outline" size="icon" className="h-8 w-8" aria-label={`Preview ${title}`} onClick={() => { setArtifact('document'); setOpen(true); }}><FileText className="h-4 w-4" /></Button>
    <Dialog open={open} onOpenChange={setOpen}><DialogContent style={{ width: 'min(90vw, 900px)', maxWidth: '90vw' }}><DialogHeader><DialogTitle>{title}</DialogTitle><DialogDescription>{url ? `${label} · Protected preview` : 'Protected document preview'}</DialogDescription></DialogHeader>
      {error ? <div className="space-y-3"><p role="alert">{error}</p><Button variant="outline" onClick={() => setRetry(value => value + 1)}>Try again</Button>{artifact === 'certificate' && <Button variant="ghost" onClick={() => setArtifact('document')}>Back to signed document</Button>}</div> : url ? <>
        {hasCertificate && <div className="flex flex-wrap gap-2" role="group" aria-label="Signed files"><Button variant={artifact === 'document' ? 'default' : 'outline'} aria-pressed={artifact === 'document'} onClick={() => setArtifact('document')}>Signed document</Button><Button variant={artifact === 'certificate' ? 'default' : 'outline'} aria-pressed={artifact === 'certificate'} onClick={() => setArtifact('certificate')}>Completion certificate</Button></div>}
        {mimeType === 'application/pdf' ? <iframe title={`${title} — ${label}`} src={url} className="h-[60vh] w-full border rounded" /> : ['image/png', 'image/jpeg'].includes(mimeType) ? <Image unoptimized width={900} height={700} alt={title} src={url} className="max-h-[60vh] w-full object-contain" /> : <p>Preview is unavailable for this file type. Download it to view.</p>}
        <a href={url} download={filename} className="underline">Download {label.toLowerCase()}</a>
      </> : <p role="status">Loading document… Signed files may take a moment to retrieve.</p>}
    </DialogContent></Dialog>
  </>;
}
