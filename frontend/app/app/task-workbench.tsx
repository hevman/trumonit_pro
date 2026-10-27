"use client";

import {useCallback,useEffect,useState} from "react";
import {CalendarClock,ClipboardList,UserRoundCheck} from "lucide-react";

type Requirement={id:string;locator:string;evidence:string;status:string};
type Member={id:string;user_id:string;email:string;display_name:string;role:string};
type Task={id:string;requirement_id:string;assignee_id:string|null;assignee_email:string|null;
 due_date:string|null;status:"open"|"in_progress"|"done";note:string;overdue:boolean;updated_at:string};
type Err={detail?:string};

async function fetchJson<T>(route:string,method="GET",body?:object):Promise<T>{
 const r=await fetch("/api/"+route,{
  method,credentials:"same-origin",cache:"no-store",
  ...(body?{headers:{"Content-Type":"application/json"},body:JSON.stringify(body)}:{}),
 });
 if(!r.ok){const payload=await r.json().catch(()=>null) as Err|null;throw Error(payload?.detail||"Request failed");}
 return r.json() as Promise<T>;
}
function TaskRow({item,task,members,projectId,readonly,onUpdate}:{
 item:Requirement;task:Task|undefined;members:Member[];projectId:string;readonly:boolean;onUpdate:()=>void;
}){
 const [assignee,setAssignee]=useState(task?.assignee_id||"");
 const [due,setDue]=useState(task?.due_date||"");
 const [status,setStatus]=useState<Task["status"]>(task?.status||"open");
 const [note,setNote]=useState(task?.note||"");
 const [busy,setBusy]=useState(false);
 const [error,setError]=useState("");
 useEffect(()=>{
  setAssignee(task?.assignee_id||"");setDue(task?.due_date||"");
  setStatus(task?.status||"open");setNote(task?.note||"");
 },[task?.id,task?.assignee_id,task?.due_date,task?.status,task?.note]);
 async function save(){
  setBusy(true);setError("");
  try{
   await fetchJson("projects/"+projectId+"/tasks/"+item.id,"PATCH",{
    assignee_id:assignee||null,due_date:due||null,status,note,
   });
   onUpdate();
  }catch(e){setError(e instanceof Error?e.message:"Save failed");}
  finally{setBusy(false);}
 }
 return <div className="task-row">
  <div className="task-source"><span className="category">{item.status}</span><strong>{item.evidence}</strong>
   <small>{item.locator}{task?.overdue?" · OVERDUE":""}</small></div>
  <div className="task-fields">
   <label>Owner<select disabled={readonly||busy} value={assignee} onChange={e=>setAssignee(e.target.value)}>
    <option value="">Unassigned</option>
    {members.map(member=><option key={member.user_id} value={member.user_id}>{member.display_name} ({member.email})</option>)}
   </select></label>
   <label>Due date<input disabled={readonly||busy} type="date" value={due} onChange={e=>setDue(e.target.value)}/></label>
   <label>Progress<select disabled={readonly||busy} value={status} onChange={e=>setStatus(e.target.value)}>
    <option value="open">Open</option><option value="in_progress">In progress</option><option value="done">Done</option>
   </select></label>
   <label>Action note<input disabled={readonly||busy} maxLength={1000} value={note} onChange={e=>setNote(e.target.value)} placeholder="What is required?"/></label>
   {!readonly&&<button type="button" className="btn btn-mini" disabled={busy} onClick={()=>void save()}>{busy?"Saving…":"Save task"}</button>}
  </div>
  {error&&<div className="alert alert-error" role="alert">{error}</div>}
 </div>;
}
export default function TaskWorkbench({projectId,requirements,readonly}:{
 projectId:string;requirements:Requirement[];readonly:boolean;
}){
 const [team,setTeam]=useState<Member[]>([]);
 const [tasks,setTasks]=useState<Task[]>([]);
 const [error,setError]=useState("");
 const reload=useCallback(async()=>{
  const [people,assignments]=await Promise.all([
   fetchJson<{members:Member[]}>("team"),
   fetchJson<Task[]>("projects/"+projectId+"/tasks"),
  ]);
  setTeam(people.members);setTasks(assignments);
 },[projectId]);
 useEffect(()=>{void reload().catch(e=>setError(e instanceof Error?e.message:"Task loading failed"));},[reload]);
 const overdue=tasks.filter(item=>item.overdue).length;
 const open=tasks.filter(item=>item.status!=="done").length;
 return <section className="panel task-workbench">
  <div className="panel-heading"><div><h2><ClipboardList size={20}/> Bid responsibilities & deadlines</h2>
   <p>Assign every tender requirement to a real member of your company, record a due date and track progress.</p>
  </div><div className="task-totals"><span><UserRoundCheck size={15}/> {open} outstanding</span>
   <span><CalendarClock size={15}/> {overdue} overdue</span></div></div>
  {error&&<div className="alert alert-error" role="alert">{error}</div>}
  {requirements.length===0?<p className="muted tiny">Upload a tender to start assigning requirements.</p>:<>
   {readonly&&<p className="muted tiny">Read-only access: ask an editor to change assignments or deadlines.</p>}
   {requirements.map(item=><TaskRow key={item.id} item={item} projectId={projectId}
    task={tasks.find(t=>t.requirement_id===item.id)} members={team} readonly={readonly} onUpdate={()=>void reload()}/>)}
  </>}
 </section>;
}
