'use client'
const linkStyle='inline-flex min-h-11 max-w-full items-center rounded-sm px-2 py-2 text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary [overflow-wrap:anywhere]'
export function PrescriptionSectionLinks({canAccessSources}:{canAccessSources:boolean}){
 return <nav id="prescription-section-navigation" tabIndex={-1} aria-label="Prescription sections" className="scroll-mt-20 rounded-lg border bg-muted/30 p-2"><p className="px-2 text-sm font-semibold">Jump to prescription section</p><ul className="flex flex-wrap gap-x-3">{[
  ['prescription-order','Current prescription'],
  ...(canAccessSources?[['prescription-sources','Original evidence']]:[]),
  ['prescription-fills','Fills and billing'],['prescription-history','Action history'],
 ].map(([id,label])=><li key={id} className="min-w-0"><a className={linkStyle} href={`#${id}`}>{label}</a></li>)}</ul></nav>
}
export function PrescriptionSectionReturn(){return <a className={linkStyle} href="#prescription-section-navigation">Back to prescription sections</a>}
