"use client";

import {useState} from "react";
import {AlertTriangle,ShieldAlert,Trash2,X} from "lucide-react";

type Preview={
  project_id:string;project_name:string;documents:number;
  requirements:number;responses:number;tasks:number;
  document_bytes:number;audit_events_preserved:boolean;
};
type ErrorData={detail?:string};
type Props={projectId:string;projectName:string;onDeleted:()=>Promise<void>};
async function json<T>(url:string,options:RequestInit={}):Promise<T>{
  const response=await fetch(url,{...options,credentials:"same-origin",cache:"no-store"});
  if(!response.ok){
    const error=await response.json().catch(()=>null) as ErrorData|null;
    throw Error(error?.detail||"Request failed ("+response.status+")");
  }
  return response.json() as Promise<T>;
}
export default function ProjectDeletion({projectId,projectName,onDeleted}:Props){
  const [open,setOpen]=useState(false);
  const [preview,setPreview]=useState<Preview|null>(null);
  const [confirmation,setConfirmation]=useState("");
  const [busy,setBusy]=useState(false);
  const [error,setError]=useState("");
  async function inspect(){
    setBusy(true);setError("");
    try{
      const details=await json<Preview>("/api/projects/"+projectId+"/deletion-preview");
      setPreview(details);setConfirmation("");setOpen(true);
    }catch(e){setError(e instanceof Error?e.message:"Cannot load deletion details");}
    finally{setBusy(false);}
  }
  async function remove(event:React.FormEvent){
    event.preventDefault();
    if(confirmation!==projectName||!preview)return;
    setBusy(true);setError("");
    try{
      await json("/api/projects/"+projectId+"/delete",{
        method:"POST",headers:{"Content-Type":"application/json"},
        body:JSON.stringify({confirm_project_name:confirmation}),
      });
      setOpen(false);setPreview(null);setConfirmation("");
      await onDeleted();
    }catch(e){setError(e instanceof Error?e.message:"Project deletion failed");}
    finally{setBusy(false);}
  }
  return <section className="panel project-deletion">
    <div className="project-deletion-intro">
      <div><h2><ShieldAlert size={19}/> Data lifecycle</h2>
        <p>Workspace owners can inspect and permanently remove a project. Company-wide answer library and audit records are preserved.</p></div>
      <button type="button" className="btn btn-secondary" disabled={busy}
        onClick={()=>void inspect()}><Trash2 size={15}/> Review deletion</button>
    </div>
    {error&&<p role="alert" className="alert alert-error">{error}</p>}
    {open&&preview&&<form className="project-delete-confirm" onSubmit={e=>void remove(e)}>
      <div className="project-delete-top"><h3><AlertTriangle size={19}/> Permanent project deletion</h3>
        <button type="button" className="btn btn-secondary" disabled={busy}
          onClick={()=>{setOpen(false);setConfirmation("");setError("");}}><X size={15}/> Cancel</button></div>
      <p>This deletes <strong>{preview.documents} documents</strong> ({(preview.document_bytes/1024/1024).toFixed(2)} MiB),
        <strong> {preview.requirements} extracted requirements</strong>,
        <strong> {preview.responses} draft/approved responses</strong> and
        <strong> {preview.tasks} task assignments</strong>. The operation cannot be undone.
        Audit events and the company answer library remain.</p>
      <p>Database backups and replicas may keep copies according to operator retention policy. This action alone does not guarantee complete regulatory erasure.</p>
      <label>Type the exact project name <strong>{projectName}</strong> to confirm:
        <input required autoComplete="off" maxLength={140} value={confirmation}
          onChange={e=>setConfirmation(e.target.value)} placeholder="Exact project name"/></label>
      <button type="submit" className="btn btn-primary" disabled={busy||confirmation!==projectName}>
        <Trash2 size={15}/>{busy?"Deleting…":"Permanently delete project"}
      </button>
    </form>}
  </section>;
}
