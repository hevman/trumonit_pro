"use client";
import {useCallback,useEffect,useState} from "react";
import {useRouter} from "next/navigation";
import Link from "next/link";
import AnswerWorkbench from "./answer-workbench";
import TaskWorkbench from "./task-workbench";
import {ArrowDownToLine,ArrowRight,CheckCircle2,Clock3,FileText,FolderPlus,LayoutGrid,LogOut,Plus,RefreshCcw,UploadCloud} from "lucide-react";

type Account={user_id:string;email:string;workspace_id:string;workspace_name:string;role:string};
type Project={id:string;name:string;buyer:string;deadline:string|null;stage:string;created_at:string;requirement_counts:Record<string,number>;requirements_total:number};
type Document={id:string;filename:string;byte_count:number;uploaded_at:string};
type Requirement={id:string;document_id:string;locator:string;evidence:string;category:string;status:string;note:string};
type Detail=Project&{documents:Document[];requirements:Requirement[];requirements_truncated:boolean};
type ApiError={detail?:string};
const statusLabels:Record<string,string>={unreviewed:"Not reviewed",met:"Met",gap:"Gap",needs_review:"Needs clarification",not_applicable:"N/A"};
const stages=["review","preparing","submitted","won","lost","archived"];
async function api<T>(route:string,options:RequestInit={}):Promise<T>{
  const result=await fetch("/api/"+route,{...options,credentials:"same-origin",cache:"no-store"});
  if(!result.ok){
    const detail=await result.json().catch(()=>null) as ApiError|null;
    throw Error(detail?.detail||"Request failed ("+result.status+")");
  }
  return result.json() as Promise<T>;
}
function StatusPill({status}:{status:string}){
  return <span className={"status status-"+status.replace("_","-")}>{statusLabels[status]||status}</span>;
}
function RequirementItem({item,filename,onSaved,readonly}:{item:Requirement;filename:string;onSaved:()=>void;readonly:boolean}){
  const [status,setStatus]=useState(item.status);
  const [note,setNote]=useState(item.note);
  const [busy,setBusy]=useState(false);
  const [error,setError]=useState("");
  useEffect(()=>{setStatus(item.status);setNote(item.note);},[item.id,item.note,item.status]);
  async function save(){
    setBusy(true);setError("");
    try{
      const projectId=(document.querySelector("[data-project-id]") as HTMLElement|null)?.dataset.projectId;
      if(!projectId)throw Error("Select a project");
      await api("projects/"+projectId+"/requirements/"+item.id,{
        method:"PATCH",headers:{"Content-Type":"application/json"},
        body:JSON.stringify({status,note}),
      });
      onSaved();
    }catch(e){setError(e instanceof Error?e.message:"Could not update");}
    finally{setBusy(false);}
  }
  return <article className="requirement-row">
    <div className="requirement-main"><div className="requirement-meta"><span className="category">{item.category}</span><span>{filename} · {item.locator}</span></div>
      <p>{item.evidence}</p><StatusPill status={item.status}/></div>
    <div className="requirement-actions">
      <label>Status<select disabled={readonly} value={status} onChange={e=>setStatus(e.target.value)}>
        {Object.entries(statusLabels).map(([key,value])=><option key={key} value={key}>{value}</option>)}
      </select></label>
      <label>Reviewer note<input disabled={readonly} placeholder="Add evidence or follow-up" maxLength={1000} value={note} onChange={e=>setNote(e.target.value)}/></label>
      <button className="btn btn-mini" disabled={readonly||busy||status===item.status&&note===item.note} onClick={()=>void save()}>{busy?"Saving…":"Save review"}</button>
    </div>
    {error&&<p className="alert alert-error" role="alert">{error}</p>}
  </article>;
}
export default function Workspace(){
  const router=useRouter();
  const [account,setAccount]=useState<Account|null>(null);
  const [projects,setProjects]=useState<Project[]>([]);
  const [selectedId,setSelectedId]=useState<string|null>(null);
  const [detail,setDetail]=useState<Detail|null>(null);
  const [error,setError]=useState("");
  const [notice,setNotice]=useState("");
  const [busy,setBusy]=useState(false);
  const [newName,setNewName]=useState("");
  const [newBuyer,setNewBuyer]=useState("");
  const [newDeadline,setNewDeadline]=useState("");
  const [showNew,setShowNew]=useState(false);
  const [file,setFile]=useState<File|null>(null);
  const refresh=useCallback(async()=>{
    try{
      const [me,items]=await Promise.all([api<Account>("auth/me"),api<Project[]>("projects")]);
      setAccount(me);setProjects(items);
      setSelectedId(previous=>previous||items[0]?.id||null);
    }catch(e){
      if(e instanceof Error&&/Sign in|session|401/i.test(e.message))router.replace("/login");
      else setError(e instanceof Error?e.message:"Unable to load workspace");
    }
  },[router]);
  const loadDetail=useCallback(async(id:string)=>{
    try{setDetail(await api<Detail>("projects/"+id));}
    catch(e){setError(e instanceof Error?e.message:"Could not open project");}
  },[]);
  useEffect(()=>{void refresh();},[refresh]);
  useEffect(()=>{if(selectedId)void loadDetail(selectedId);else setDetail(null);},[selectedId,loadDetail]);
  async function create(e:React.FormEvent){
    e.preventDefault();setBusy(true);setError("");setNotice("");
    try{
      const project=await api<Project>("projects",{
        method:"POST",headers:{"Content-Type":"application/json"},
        body:JSON.stringify({name:newName,buyer:newBuyer,deadline:newDeadline||null}),
      });
      await refresh();setSelectedId(project.id);setShowNew(false);setNewName("");setNewBuyer("");setNewDeadline("");
      setNotice("Tender project created.");
    }catch(e){setError(e instanceof Error?e.message:"Could not create project");}
    finally{setBusy(false);}
  }
  async function upload(e:React.FormEvent){
    e.preventDefault();if(!selectedId||!file)return;
    setBusy(true);setError("");setNotice("");
    try{
      if(file.size>8*1024*1024)throw Error("Maximum file size is 8 MiB");
      const body=new FormData();body.set("file",file);
      const result=await api<{requirements_created:number;note:string}>("projects/"+selectedId+"/documents",{
        method:"POST",body,
      });
      setNotice("Document saved. "+result.requirements_created+" requirement candidates found. "+result.note);
      setFile(null);
      const field=document.getElementById("document-file") as HTMLInputElement|null;
      if(field)field.value="";
      await loadDetail(selectedId);await refresh();
    }catch(e){setError(e instanceof Error?e.message:"Could not import document");}
    finally{setBusy(false);}
  }
  async function changeStage(stage:string){
    if(!selectedId)return;
    setBusy(true);setError("");
    try{
      await api("projects/"+selectedId,{method:"PATCH",
        headers:{"Content-Type":"application/json"},body:JSON.stringify({stage})});
      await loadDetail(selectedId);await refresh();
    }catch(e){setError(e instanceof Error?e.message:"Could not update stage");}
    finally{setBusy(false);}
  }
  async function logout(){
    try{await api("auth/logout",{method:"POST",headers:{"Content-Type":"application/json"},body:"{}"});}
    finally{router.replace("/login");router.refresh();}
  }
  return <main className="workspace-shell">
    <aside className="sidebar">
      <Link className="brand" href="/"><span className="brand-mark">T<span>.</span></span><span>trumonit<span className="brand-domain">.pro</span></span></Link>
      <div className="sidebar-label">YOUR COMPANY</div>
      <div className="organization"><span className="org-icon">{account?.workspace_name?.slice(0,1).toUpperCase()||"T"}</span><div><b>{account?.workspace_name||"Loading workspace"}</b><small>Private organization</small></div></div>
      <div className="sidebar-label">BID PROJECTS</div>
      <div className="project-nav">
        {projects.map(project=><button type="button" key={project.id}
          className={"project-link "+(project.id===selectedId?"active":"")}
          onClick={()=>{setSelectedId(project.id);setError("");setNotice("");}}>
          <FileText size={17}/><span>{project.name}</span></button>)}
        {projects.length===0&&<p className="muted tiny">No tenders yet. Start a new bid project.</p>}
      </div>
      <button type="button" className="sidebar-add" disabled={account?.role==="viewer"} onClick={()=>setShowNew(true)}><Plus size={16}/> New tender</button>
      <div className="sidebar-bottom"><Link href="/team" className="sidebar-team-link">People & access</Link><span>{account?.email}</span><button type="button" onClick={()=>void logout()}><LogOut size={17}/> Sign out</button></div>
    </aside>
    <section className="workspace-main">
      <div className="workspace-top"><div><span className="eyebrow">TRUMONIT / WORKSPACE</span><h1>Bid command center</h1><p>Review every requirement. Keep the source close.</p></div><button className="btn btn-dark" disabled={account?.role==="viewer"} onClick={()=>setShowNew(true)}><FolderPlus size={17}/> New tender</button></div>
      {error&&<div className="alert alert-error" role="alert">{error}</div>}
      {notice&&<div className="alert alert-good" role="status">{notice}</div>}
      {showNew&&<form className="panel new-form" onSubmit={create}>
        <h2>Create a tender project</h2><div className="form-grid">
          <label>Project name<input required minLength={3} maxLength={140} value={newName} onChange={e=>setNewName(e.target.value)} placeholder="e.g. Regional IT services tender"/></label>
          <label>Buyer / contracting authority<input maxLength={140} value={newBuyer} onChange={e=>setNewBuyer(e.target.value)} placeholder="Organization name"/></label>
          <label>Submission deadline<input type="date" value={newDeadline} onChange={e=>setNewDeadline(e.target.value)}/></label>
        </div><div className="form-actions"><button type="button" className="btn btn-secondary" onClick={()=>setShowNew(false)}>Cancel</button><button className="btn btn-primary" type="submit" disabled={busy}>Create project <ArrowRight size={16}/></button></div>
      </form>}
      {!selectedId&&!showNew&&<div className="panel empty-state"><LayoutGrid size={43}/><h2>Your first bid, organized.</h2><p>Create a tender project to import its documentation and build a transparent list of requirements.</p><button className="btn btn-primary" onClick={()=>setShowNew(true)}>Create first project <Plus size={17}/></button></div>}
      {detail&&selectedId&&<div data-project-id={selectedId}>
        <div className="panel project-head"><div><div className="eyebrow">CURRENT TENDER</div><h2>{detail.name}</h2><p>{detail.buyer||"Buyer not specified"}{detail.deadline?" · Due "+detail.deadline:" · No deadline entered"}</p></div><label className="stage-control">Bid stage<select value={detail.stage} disabled={busy||account?.role==="viewer"} onChange={e=>void changeStage(e.target.value)}>{stages.map(s=><option key={s} value={s}>{s.replace("_"," ")}</option>)}</select></label></div>
        <div className="metric-grid"><div className="metric"><small>Requirements found</small><strong>{detail.requirements_total}</strong><span>Evidence candidates</span></div><div className="metric"><small>Reviewed as met</small><strong>{detail.requirement_counts.met||0}</strong><span>Confirmed by your team</span></div><div className="metric"><small>Open gaps</small><strong className="accent-red">{detail.requirement_counts.gap||0}</strong><span>Require action</span></div><div className="metric"><small>Unreviewed</small><strong>{detail.requirement_counts.unreviewed||0}</strong><span>Awaiting a decision</span></div></div>
        <div className="panel upload-panel"><div className="panel-heading"><div><h2><UploadCloud size={20}/> Upload tender documents</h2><p>PDF or DOCX with selectable text, maximum 8 MiB per file and 25 files per tender.</p></div></div>
          <form onSubmit={upload} className="upload-form"><input disabled={account?.role==="viewer"} id="document-file" type="file" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required onChange={e=>setFile(e.target.files?.[0]||null)}/><button className="btn btn-dark" disabled={busy||!file||account?.role==="viewer"} type="submit">{busy?"Processing…":"Analyze document"} <ArrowRight size={16}/></button></form>
          {detail.documents.length>0&&<div className="document-list">{detail.documents.map(doc=><a key={doc.id} href={"/api/projects/"+selectedId+"/documents/"+doc.id}><FileText size={16}/>{doc.filename}<small>{Math.round(doc.byte_count/1024)} KB</small><ArrowDownToLine size={14}/></a>)}</div>}
        </div>
        <div className="panel requirements-panel">
          <div className="panel-heading"><div><h2><CheckCircle2 size={20}/> Requirements register</h2><p>Every candidate links back to its page or paragraph. Confirm before relying on it.</p></div><a className="btn btn-secondary" href={"/api/projects/"+selectedId+"/export"}>Export CSV <ArrowDownToLine size={15}/></a></div>
          {detail.requirements_truncated&&<div className="alert alert-error">Showing the first 500 entries; CSV export includes the full list.</div>}
          {detail.requirements.length===0?<div className="requirements-empty"><Clock3 size={26}/><p>Upload a document to discover potential requirements. Scanned PDFs require OCR and are not supported yet.</p></div>:detail.requirements.map(item=><RequirementItem key={item.id} item={item}
            filename={detail.documents.find(doc=>doc.id===item.document_id)?.filename||"Source"} readonly={account?.role==="viewer"}
            onSaved={()=>{void loadDetail(selectedId);void refresh();}}/>)}
        </div>
        <TaskWorkbench projectId={selectedId} requirements={detail.requirements} readonly={account?.role==="viewer"}/>
        {account?.role==="viewer"?<div className="panel readonly-hint">Read-only team access: ask an editor to prepare and manage bid responses.</div>:<AnswerWorkbench projectId={selectedId} requirements={detail.requirements}/>}
      </div>}
      <p className="workspace-disclaimer">Early product preview. All supplier answers are entered or imported by your organization, then manually approved. Source-linked extraction is deterministic and requires human review. This is not legal advice or a final submission.</p>
    </section>
  </main>;
}
