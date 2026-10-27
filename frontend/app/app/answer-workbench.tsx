"use client";

import {useCallback,useEffect,useState} from "react";
import {BookOpenCheck,Download,FileCheck2,Plus,RefreshCcw,ShieldCheck,Upload} from "lucide-react";

type LibraryEntry={
 id:string;title:string;category:string;body:string;evidence_reference:string;
 valid_until:string|null;status:"draft"|"approved";usable:boolean;
};
type Answer={id:string;requirement_id:string;body:string;status:"draft"|"approved";
 library_entry_id:string|null;approved_at:string|null;};
type Requirement={id:string;locator:string;evidence:string;category:string;status:string};
type ServerError={detail?:string};
type AuditEvent={id:string;action:string;resource_id:string;actor_email:string;created_at:string};

async function json<T>(path:string,options:RequestInit={}):Promise<T>{
 const response=await fetch("/api/"+path,{credentials:"same-origin",cache:"no-store",...options});
 if(!response.ok){
  const data=await response.json().catch(()=>null) as ServerError|null;
  throw Error(typeof data?.detail==="string"?data.detail:"Request failed ("+response.status+")");
 }
 return response.json() as Promise<T>;
}
function body(value:object):RequestInit{
 return {headers:{"Content-Type":"application/json"},body:JSON.stringify(value)};
}

export default function AnswerWorkbench({projectId,requirements}:{
 projectId:string;requirements:Requirement[];
}){
 const [library,setLibrary]=useState<LibraryEntry[]>([]);
 const [answers,setAnswers]=useState<Answer[]>([]);
 const [audit,setAudit]=useState<AuditEvent[]>([]);
 const [currentId,setCurrentId]=useState("");
 const [answerText,setAnswerText]=useState("");
 const [title,setTitle]=useState("");
 const [category,setCategory]=useState("general");
 const [reference,setReference]=useState("");
 const [validUntil,setValidUntil]=useState("");
 const [template,setTemplate]=useState("");
 const [editId,setEditId]=useState<string|null>(null);
 const [busy,setBusy]=useState(false);
 const [error,setError]=useState("");
 const [notice,setNotice]=useState("");
 const [csvFile,setCsvFile]=useState<File|null>(null);
 const [showAudit,setShowAudit]=useState(false);
 const refresh=useCallback(async()=>{
  const [items,filled]=await Promise.all([
   json<LibraryEntry[]>("library"),
   json<Answer[]>("projects/"+projectId+"/responses"),
  ]);
  setLibrary(items);setAnswers(filled);
 },[projectId]);
 useEffect(()=>{
  let active=true;
  Promise.all([json<LibraryEntry[]>("library"),json<Answer[]>("projects/"+projectId+"/responses")])
   .then(([items,filled])=>{if(active){setLibrary(items);setAnswers(filled);}})
   .catch(e=>{if(active)setError(e instanceof Error?e.message:"Loading failed");});
  return ()=>{active=false;};
 },[projectId]);
 useEffect(()=>{
  setCurrentId(previous=>requirements.some(item=>item.id===previous)?previous:requirements[0]?.id||"");
 },[projectId,requirements[0]?.id]);
 const current=requirements.find(item=>item.id===currentId);
 const answer=answers.find(item=>item.requirement_id===currentId);
 useEffect(()=>{setAnswerText(answer?.body||"");},[currentId,answer?.body]);
 const approved=answers.filter(item=>item.status==="approved").length;
 async function perform(task:()=>Promise<unknown>,message:string){
  setBusy(true);setError("");setNotice("");
  try{await task();await refresh();setNotice(message);}
  catch(e){setError(e instanceof Error?e.message:"Action failed");}
  finally{setBusy(false);}
 }
 function resetLibrary(){
  setTitle("");setCategory("general");setReference("");setValidUntil("");
  setTemplate("");setEditId(null);
 }
 function edit(item:LibraryEntry){
  setEditId(item.id);setTitle(item.title);setCategory(item.category);
  setReference(item.evidence_reference);setValidUntil(item.valid_until||"");
  setTemplate(item.body);
 }
 async function saveTemplate(e:React.FormEvent){
  e.preventDefault();
  await perform(async()=>{
   await json(editId?"library/"+editId:"library",{
    method:editId?"PATCH":"POST",
    ...body({title,category,body:template,evidence_reference:reference,
     valid_until:validUntil||null}),
   });
   resetLibrary();
  },"Company answer saved as a draft awaiting approval.");
 }
 async function importCsv(e:React.FormEvent){
  e.preventDefault();if(!csvFile)return;
  await perform(async()=>{
   if(csvFile.size>256*1024)throw Error("Maximum CSV size is 256 KiB");
   const form=new FormData();form.append("file",csvFile);
   const imported=await json<{imported:number;skipped_duplicates:number}>("library/import",{
    method:"POST",body:form,
   });
   setCsvFile(null);
   const input=document.getElementById("library-csv") as HTMLInputElement|null;
   if(input)input.value="";
   setNotice(imported.imported+" entries imported as drafts; "+imported.skipped_duplicates+" duplicates skipped.");
  },"Import finished. All new entries require approval.");
 }
 async function loadAudit(){
  setError("");
  try{
   const events=await json<AuditEvent[]>("audit");
   setAudit(events);setShowAudit(true);
  }catch(e){setError(e instanceof Error?e.message:"Cannot load audit history");}
 }
 return <section className="panel knowledge-workbench">
  <div className="panel-heading">
   <div><h2><BookOpenCheck size={20}/> Company knowledge & response workflow</h2>
    <p>Only your organization's own content. Human approval, version control and an editable Word pack. No AI.</p>
   </div>
   <a className="btn btn-secondary" href={"/api/projects/"+projectId+"/response-pack"}>
    <Download size={16}/> Export approved DOCX
   </a>
  </div>
  <div className="knowledge-stats">
   <div><small>Company answers</small><strong>{library.length}</strong></div>
   <div><small>Reusable and valid</small><strong>{library.filter(x=>x.usable).length}</strong></div>
   <div><small>Bid drafts</small><strong>{answers.length}</strong></div>
   <div><small>Approved responses</small><strong>{approved}</strong></div>
  </div>
  {error&&<div className="alert alert-error" role="alert">{error}</div>}
  {notice&&<div className="alert alert-good" role="status">{notice}</div>}
  <div className="knowledge-grid">
   <div className="knowledge-column">
    <h3>1. Your reusable answer library</h3>
    <p className="tiny muted">Store verified company statements. Edits revoke approval; expired answers cannot be reused.</p>
    <div className="csv-panel">
     <a href="/api/library/template" className="btn btn-secondary"><Download size={14}/> CSV template</a>
     <form onSubmit={importCsv}>
      <label htmlFor="library-csv">Import your existing answers (UTF-8 CSV)</label>
      <input id="library-csv" type="file" accept=".csv,text/csv" onChange={e=>setCsvFile(e.target.files?.[0]||null)}/>
      <button type="submit" className="btn btn-dark" disabled={busy||!csvFile}>
       <Upload size={14}/> Import drafts</button>
     </form>
    </div>
    <form className="knowledge-form" onSubmit={saveTemplate}>
     <label>Answer title<input minLength={3} maxLength={150} required value={title} onChange={e=>setTitle(e.target.value)} placeholder="Current insurance coverage"/></label>
     <label>Category<input maxLength={40} required value={category} onChange={e=>setCategory(e.target.value)}/></label>
     <label>Approved-source wording<textarea minLength={15} maxLength={10000} rows={5} required value={template}
      onChange={e=>setTemplate(e.target.value)} placeholder="Write a statement supported by your company's documentation."/></label>
     <label>Evidence document reference<input maxLength={400} value={reference} onChange={e=>setReference(e.target.value)}
      placeholder="Certificate or internal policy reference"/></label>
     <label>Valid until (optional)<input type="date" value={validUntil} onChange={e=>setValidUntil(e.target.value)}/></label>
     <div className="knowledge-actions">
      {editId&&<button type="button" className="btn btn-secondary" onClick={resetLibrary}>Cancel edit</button>}
      <button type="submit" className="btn btn-dark" disabled={busy}><Plus size={15}/>{editId?"Update draft":"Save new draft"}</button>
     </div>
    </form>
    <div className="library-list">
     {library.length===0&&<p className="muted tiny">Import a CSV or add your first reusable answer.</p>}
     {library.map(entry=><article key={entry.id} className="library-item">
      <div className="library-head"><strong>{entry.title}</strong><span className={"status "+(entry.usable?"status-met":"status-unreviewed")}>{entry.usable?"Approved":entry.status==="approved"?"Expired":"Draft"}</span></div>
      <p>{entry.body}</p>
      {entry.evidence_reference&&<small>Evidence: {entry.evidence_reference}</small>}
      {entry.valid_until&&<small>Expires: {entry.valid_until}</small>}
      <div className="knowledge-actions">
       <button type="button" className="btn btn-secondary" disabled={busy} onClick={()=>edit(entry)}>Edit</button>
       {entry.status==="draft"&&<button type="button" className="btn btn-secondary" disabled={busy}
        onClick={()=>void perform(()=>json("library/"+entry.id+"/approve",{method:"POST"}),"Reusable answer approved.")}>
        <ShieldCheck size={14}/> Approve</button>}
       {entry.usable&&<button type="button" className="btn btn-mini" disabled={!current||busy}
        onClick={()=>void perform(()=>json("projects/"+projectId+"/requirements/"+currentId+"/response",{
         method:"PATCH",...body({library_entry_id:entry.id}),
        }),"Verified wording copied to an unapproved project draft.")}>Use for selected requirement</button>}
      </div>
     </article>)}
    </div>
   </div>
   <div className="knowledge-column">
    <h3>2. Respond to a specific requirement</h3>
    <p className="tiny muted">Select a source clause and supply your company's wording; only approved items marked Met enter the Word pack.</p>
    {requirements.length===0?<p className="tiny muted">Import a tender before drafting responses.</p>:<>
     <label>Requirement<select value={currentId} onChange={e=>setCurrentId(e.target.value)}>
      {requirements.map(item=><option key={item.id} value={item.id}>{item.category} · {item.evidence.slice(0,90)}</option>)}
     </select></label>
     {current&&<div className="requirement-evidence"><small>{current.locator} · {current.category} · {current.status}</small><p>{current.evidence}</p></div>}
     <form className="knowledge-form" onSubmit={e=>{
      e.preventDefault();
      void perform(()=>json("projects/"+projectId+"/requirements/"+currentId+"/response",{
       method:"PATCH",...body({body:answerText}),
      }),"Response saved as an unapproved draft.");
     }}>
      <label>Working response<textarea rows={10} minLength={10} maxLength={10000} required
       value={answerText} onChange={e=>setAnswerText(e.target.value)}
       placeholder="Your team's verified response; no text is generated automatically."/></label>
      <button type="submit" className="btn btn-dark" disabled={busy||answerText.trim().length<10}>Save draft</button>
     </form>
     <div className="approval-panel">
      <strong>Review state: {answer?.status||"No draft"}</strong>
      {answer?.status==="approved"&&<span className="status status-met"><FileCheck2 size={13}/> Owner approved</span>}
      {answer?.status==="draft"&&<button type="button" className="btn btn-primary"
       disabled={busy||current?.status!=="met"}
       onClick={()=>void perform(()=>json("projects/"+projectId+"/requirements/"+currentId+"/response/approve",{
        method:"POST",
       }),"Response approved for export.")}><ShieldCheck size={14}/> Approve response</button>}
      {current?.status!=="met"&&<p className="tiny muted">First mark this requirement Met in the register above.</p>}
     </div>
    </>}
    <div className="audit-panel">
     <h3>3. Accountability and audit</h3>
     <p className="tiny muted">Review project and company answer changes recorded with actor and time.</p>
     <button type="button" className="btn btn-secondary" onClick={()=>void loadAudit()}><RefreshCcw size={14}/> Load organization audit</button>
     {showAudit&&<div className="audit-list">
      {audit.length===0?<p className="tiny muted">No recorded events.</p>:audit.map(event=><div key={event.id}>
       <b>{event.action}</b><small>{event.actor_email}</small>
       <time>{new Date(event.created_at).toLocaleString()}</time>
      </div>)}
     </div>}
    </div>
   </div>
  </div>
  <p className="tiny muted">Trumonit documents are internal work products. Sign-offs are manual; export is not a legally verified submission. Enterprise SSO, advanced retention controls and production security reviews are pending.</p>
 </section>;
}
