import Link from "next/link";
import {ArrowUpRight, BookOpenCheck, CheckCircle2, FileSearch, FolderKanban, ListTodo, ShieldCheck, Sparkles, Timer} from "lucide-react";

export default function Home(){
  return <main>
    <section className="hero"><div className="container hero-grid">
      <div>
        <div className="kicker"><span className="pulse"/>THE TENDER RESPONSE WORKSPACE</div>
        <h1>Complex tenders.<br/><span>Clear requirements.</span><br/>Better responses.</h1>
        <p className="hero-subtitle">Turn long PDF and Word tender packs into a reviewable requirement register. Track evidence, spot gaps and keep the whole bid team aligned — without losing the original source.</p>
        <div className="hero-actions"><Link href="/login?mode=register" className="btn btn-primary">Create workspace <ArrowUpRight size={17}/></Link><Link href="#workflow" className="btn btn-outline-light">See how it works</Link></div>
        <div className="hero-trust"><ShieldCheck size={17}/> Each company has a separate workspace. Human review stays in control.</div>
      </div>
      <div className="hero-preview" aria-label="Illustrative requirements dashboard">
        <div className="preview-top"><span>TRUMONIT / BID WORKSPACE</span><span className="preview-live">● REVIEW IN PROGRESS</span></div>
        <div className="preview-title"><span className="preview-icon"><FolderKanban size={20}/></span><div><small>OPEN BID</small><strong>Infrastructure service contract</strong></div></div>
        <div className="preview-metrics">
          <div><small>Requirements</small><strong>42</strong></div><div><small>Reviewed</small><strong>31</strong></div><div><small>Gaps</small><strong className="red">4</strong></div>
        </div>
        <div className="preview-list">
          <div className="preview-list-head">REQUIREMENT REGISTER <span>Source linked</span></div>
          <div className="preview-line"><span className="dot green"/><div><strong>Proof of professional insurance</strong><small>Specification · page 12</small></div><span className="tag green">Met</span></div>
          <div className="preview-line"><span className="dot orange"/><div><strong>ISO certification documentation</strong><small>Specification · page 18</small></div><span className="tag amber">Review</span></div>
          <div className="preview-line"><span className="dot red-dot"/><div><strong>Minimum deployment experience</strong><small>Terms · page 7</small></div><span className="tag red-tag">Gap</span></div>
        </div>
        <div className="preview-footer"><CheckCircle2 size={15}/> Example interface data, not a customer case study.</div>
      </div>
    </div></section>
    <section id="workflow" className="section container">
      <div className="section-heading"><div className="eyebrow">HOW IT WORKS</div><h2>From a 200-page pack to a structured review.</h2><p>Less time searching across attachments. More time on the substance of your proposal.</p></div>
      <div className="steps">
        <article><span>01</span><FileSearch size={28}/><h3>Upload source documents</h3><p>Start with searchable PDF or DOCX tender documents. Every extracted candidate retains its page or paragraph reference.</p></article>
        <article><span>02</span><BookOpenCheck size={28}/><h3>Review requirements</h3><p>See potential obligations, deadlines and compliance documents. Mark each as met, a gap, or requiring clarification.</p></article>
        <article><span>03</span><ListTodo size={28}/><h3>Manage the bid response</h3><p>Track the bid stage, buyer and deadline. Export an evidence register your proposal team can work from.</p></article>
      </div>
    </section>
    <section id="features" className="feature-band"><div className="container feature-grid">
      <div><div className="eyebrow">MADE FOR SERIOUS BID TEAMS</div><h2>Your company's procurement knowledge, accountable.</h2><p>Bring your existing answers, procedures and evidence into a single controlled workspace. Every edit and approval is traceable. Reuse your company's wording, not machine-generated text.</p></div>
      <div className="feature-checks"><div><ShieldCheck size={21}/> Separate workspaces and scoped access</div><div><FileSearch size={21}/> Source-linked requirement candidates</div><div><FolderKanban size={21}/> Saved projects and document history</div><div><Timer size={21}/> Bid dates and stage tracking</div><div><Sparkles size={21}/> Own data, manual sign-off and reusable answers</div></div>
    </div></section>
    <section id="pricing" className="section container">
      <div className="section-heading"><div className="eyebrow">FUTURE SUBSCRIPTIONS</div><h2>Built for high-value B2B teams.</h2><p>Proposed pricing for coordinated B2B teams, not a live subscription. Security review, enterprise identity, integrations and billing are still in development.</p></div>
      <div className="price-grid">
        <article className="price-card"><span>Professional · proposed</span><h3>€249 <small>/month</small></h3><p>For small proposal teams building a repeatable tender workflow.</p><Link href="/login?mode=register">Explore preview <ArrowUpRight size={15}/></Link></article>
        <article className="price-card emphasized"><span>Business · proposed</span><h3>€499 <small>/month</small></h3><p>For procurement and proposal teams managing reusable answers, document evidence and human approvals.</p><Link href="/login?mode=register">Explore preview <ArrowUpRight size={15}/></Link></article>
        <article className="price-card"><span>Enterprise · proposed</span><h3>Custom</h3><p>For governed workflows, integrations and security reviews.</p><Link href="/login?mode=register">Explore preview <ArrowUpRight size={15}/></Link></article>
      </div>
    </section>
    <section className="closing-cta"><div className="container"><h2>Make every requirement accountable.</h2><p>Start with your own tender documents. Keep your team focused on evidence and next actions.</p><Link className="btn btn-primary" href="/login?mode=register">Open Trumonit workspace <ArrowUpRight size={17}/></Link></div></section>
  </main>;
}
