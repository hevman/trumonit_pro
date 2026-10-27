import Link from "next/link";
import { ArrowUpRight, ArrowRight, FileSearch, ListChecks, BookOpenCheck, Check, FolderKanban, ClipboardCheck, Users, FileSpreadsheet, CornerDownRight } from "lucide-react";
const features = [
 [FileSearch, "Keep the source in sight", "Review candidates from searchable PDF and Word documents with page or paragraph references. Open the original when context matters."],
 [BookOpenCheck, "Build on approved answers", "Import your answer library, track provenance and expiry, and copy approved content into working responses. Your team makes the final decision."],
 [Users, "Give every action an owner", "Assign tasks and due dates, work through gaps, and manage owner, editor and viewer access within your organization."],
 [FileSpreadsheet, "Work with the buyer’s RFP", "Map questions and response columns in XLSX workbooks. Prepare answers in the workspace and export them back into the spreadsheet."],
];
export default function Home() {
 return <main>
 <section className="hero"><div className="container hero-grid"><div>
 <div className="kicker"><span className="pulse"/> BUILT FOR BID & PROPOSAL TEAMS</div>
 <h1>Complex tender.<br/><span>Clear next move.</span></h1>
 <p className="hero-subtitle">Bring requirements, source documents and your team’s answers into one bid workspace. See what is covered, what needs evidence and who is taking it forward.</p>
 <div className="hero-actions"><a className="btn btn-primary" href="mailto:hello@trumonit.pro?subject=Trumonit%20workspace%20enquiry">Discuss your workflow <ArrowUpRight size={18}/></a><Link href="#workflow" className="hero-secondary">Explore the product <ArrowRight size={17}/></Link></div>
 <div className="hero-trust"><Check size={16}/> Source-linked review <span>·</span> Your answers <span>·</span> Human sign-off</div>
 </div><div className="bid-preview" aria-label="Illustrative bid workspace with example data">
 <div className="bid-preview-bar"><span className="preview-logo">t.</span><span>Workspace / IT services</span><span className="sample-label">EXAMPLE</span></div>
 <div className="bid-preview-heading"><span className="eyebrow">RESPONSE IN PROGRESS</span><h2>Regional IT services</h2><p>Requirements register <span>3 items shown</span></p></div>
 <div className="bid-summary"><div><strong>01</strong><span>Met</span></div><div><strong>01</strong><span>Needs review</span></div><div><strong>01</strong><span>Open gap</span></div></div>
 <div className="bid-row"><span className="bid-check"><Check size={15}/></span><div><strong>Professional insurance</strong><small>Specification · page 12</small></div><span className="bid-status met">Met</span></div>
 <div className="bid-row"><FileSearch size={18}/><div><strong>Service coverage</strong><small>Specification · page 18</small></div><span className="bid-status review">Review</span></div>
 <div className="bid-row"><CornerDownRight size={18}/><div><strong>Deployment experience</strong><small>Terms · page 7</small></div><span className="bid-status gap">Gap</span></div>
 <div className="bid-note"><ClipboardCheck size={18}/><span>Evidence first. A decision on every requirement.</span></div>
 </div></div></section>
 <div className="audience-strip container"><span>FOR THE PEOPLE BEHIND THE PROPOSAL</span><p>Bid managers</p><p>Sales engineers</p><p>Subject-matter experts</p></div>
 <section id="workflow" className="section container"><div className="section-heading"><div className="eyebrow">A CONNECTED RESPONSE WORKFLOW</div><h2>From scattered documents<br/>to a shared plan.</h2><p>A practical place to organize the work before your proposal is ready to submit.</p></div>
 <div className="steps">{[
 [FileSearch,"01","Start with the source","Create a tender project with a buyer and deadline. Upload searchable PDF, DOCX or an XLSX RFP to begin your review."],
 [ListChecks,"02","Turn review into decisions","Check requirement candidates against the original. Mark coverage, flag gaps and record notes for the people preparing the response."],
 [FolderKanban,"03","Prepare the response together","Reuse approved answers, assign follow-up tasks and export a working response pack, requirement register or mapped spreadsheet."],
 ].map(([Icon,n,title,text]) => { const I = Icon as typeof FileSearch; return <article key={String(n)}><div className="step-top"><I size={25}/><span>{String(n)}</span></div><h3>{String(title)}</h3><p>{String(text)}</p></article>; })}</div></section>
 <section id="features" className="product-section"><div className="container"><div className="product-heading"><div><div className="eyebrow">LESS SEARCHING. MORE SUBSTANCE.</div><h2>The detail stays connected.</h2></div><p>Designed for suppliers responding to complex B2B tenders, from IT and professional services to engineering and infrastructure.</p></div><div className="product-grid">{features.map(([Icon,title,text])=>{const I=Icon as typeof FileSearch;return <article key={String(title)}><I size={23}/><h3>{String(title)}</h3><p>{String(text)}</p></article>;})}</div>
 <div className="scope-note"><ClipboardCheck size={22}/><div><strong>A review aid. Your team stays accountable.</strong><p>Document extraction finds candidates using deterministic rules. It is not AI-generated bid writing, a completeness check or legal advice. Scanned PDFs need OCR and are not supported.</p></div></div></div></section>
 <section id="access" className="section container"><div className="access-card"><div><span className="eyebrow">LET’S TALK ABOUT YOUR BID PROCESS</span><h2>Make room for<br/>a clearer response.</h2><p>Tell us how your team handles tender documents, answer libraries and review. We’ll discuss whether the current product fits your workflow.</p><a className="btn btn-dark" href="mailto:hello@trumonit.pro?subject=Trumonit%20workspace%20enquiry">hello@trumonit.pro <ArrowUpRight size={18}/></a></div><aside><span className="access-symbol">t.</span><h3>Early product access</h3><p>Access is coordinated directly. There is no live subscription checkout.</p><p>Use sample or non-confidential documents while evaluating. Security review and enterprise identity are not part of the current release.</p><Link href="/login">Already have access? Sign in <ArrowRight size={16}/></Link></aside></div></section>
 </main>;
}
