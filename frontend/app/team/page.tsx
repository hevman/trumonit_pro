"use client";

import Link from "next/link";
import {useCallback,useEffect,useState} from "react";
import {ArrowLeft,Copy,MailPlus,Shield,UserMinus,Users} from "lucide-react";

type User={user_id:string;email:string;role:string;workspace_name:string};
type Member={id:string;user_id:string;email:string;display_name:string;role:string};
type Invite={id:string;email:string;role:string;expires_at:string};
type Workspace={id:string;name:string;role:string;active:boolean};
type Team={members:Member[];invitations:Invite[]};
type InviteResult={id:string;token:string;email:string;role:string;expires_at:string};
type ErrorData={detail?:string};
async function request<T>(url:string,method="GET",payload?:object):Promise<T>{
 const result=await fetch("/api/"+url,{
  method,credentials:"same-origin",cache:"no-store",
  ...(payload?{headers:{"Content-Type":"application/json"},body:JSON.stringify(payload)}:{}),
 });
 if(!result.ok){
  const data=await result.json().catch(()=>null) as ErrorData|null;
  throw Error(data?.detail||"Request failed ("+result.status+")");
 }
 return result.json() as Promise<T>;
}
export default function TeamPage(){
 const [me,setMe]=useState<User|null>(null);
 const [team,setTeam]=useState<Team>({members:[],invitations:[]});
 const [workspaces,setWorkspaces]=useState<Workspace[]>([]);
 const [email,setEmail]=useState("");
 const [role,setRole]=useState("editor");
 const [issuedLink,setIssuedLink]=useState("");
 const [error,setError]=useState("");
 const [notice,setNotice]=useState("");
 const [busy,setBusy]=useState(false);
 const load=useCallback(async()=>{
  const [profile,people,spaces]=await Promise.all([
   request<User>("auth/me"),
   request<Team>("team"),
   request<Workspace[]>("team/workspaces"),
  ]);
  setMe(profile);setTeam(people);setWorkspaces(spaces);
 },[]);
 useEffect(()=>{void load().catch(e=>setError(e instanceof Error?e.message:"Unavailable"));},[load]);
 async function action(fn:()=>Promise<unknown>,message:string){
  setBusy(true);setError("");setNotice("");
  try{await fn();await load();setNotice(message);}
  catch(e){setError(e instanceof Error?e.message:"Request failed");}
  finally{setBusy(false);}
 }
 async function invite(event:React.FormEvent){
  event.preventDefault();
  await action(async()=>{
   const result=await request<InviteResult>("team/invitations","POST",{email,role});
   // Fragment keeps the bearer secret out of destination paths, query strings
   // and HTTP referrers. It appears exactly once, on creation.
   setIssuedLink(window.location.origin+"/join#token="+result.token);
   setEmail("");
  },"Invitation created. Share the link privately with the intended person.");
 }
 return <main className="container team-page">
  <div className="team-back"><Link href="/app"><ArrowLeft size={15}/> Back to bid workspace</Link></div>
  <div className="team-heading"><div><span className="eyebrow">ORGANIZATION ADMINISTRATION</span>
   <h1>People & access</h1><p>Control exactly who can work with your company's tender material.</p>
  </div><div className="team-heading-icon"><Users size={29}/></div></div>
  {error&&<div className="alert alert-error" role="alert">{error}</div>}
  {notice&&<div className="alert alert-good" role="status">{notice}</div>}
  <div className="panel team-section">
   <h2>Active workspace</h2>
   <p className="muted">Your account may belong to multiple organizations. Each keeps its own projects and documents.</p>
   <div className="workspace-switches">
    {workspaces.map(item=><button key={item.id} type="button"
     disabled={busy||item.active} className={"workspace-switch "+(item.active?"active":"")}
     onClick={()=>void action(async()=>{
      await request("team/switch","POST",{workspace_id:item.id});
      window.location.assign("/app");
     },"Workspace changed.")}>
     <strong>{item.name}</strong><small>{item.role}{item.active?" · Active":""}</small>
    </button>)}
   </div>
  </div>
  <div className="panel team-section">
   <h2><Users size={20}/> Organization members</h2>
   <div className="team-member-list">{team.members.map(member=><div key={member.id} className="team-member-row">
    <div><strong>{member.display_name}</strong><small>{member.email}</small></div>
    {me?.role==="owner"&&member.role!=="owner"?<div className="team-row-controls">
     <select aria-label={"Role for "+member.email} disabled={busy} value={member.role}
      onChange={e=>void action(()=>request("team/members/"+member.id,"PATCH",{role:e.target.value}),
      "Permissions updated.")}>
      <option value="editor">Editor</option><option value="viewer">Viewer</option>
     </select>
     <button type="button" disabled={busy} className="btn btn-secondary" onClick={()=>{
      if(window.confirm("Remove this colleague's access to this organization?"))
       void action(()=>request("team/members/"+member.id+"/remove","POST"),"Member access revoked.");
     }}><UserMinus size={15}/> Remove</button>
    </div>:<span className="status status-met"><Shield size={13}/> {member.role}</span>}
   </div>)}</div>
  </div>
  {me?.role==="owner"&&<div className="panel team-section">
   <h2><MailPlus size={21}/> Invite a colleague</h2>
   <p className="muted">Create a single-use link valid for 3 days. **No emails are sent automatically** — send the link using your trusted channel. Anyone who obtains the link can attempt to claim it; protect it carefully.</p>
   <form className="team-invite-form" onSubmit={invite}>
    <label>Invited work email<input type="email" required value={email} maxLength={254} onChange={e=>setEmail(e.target.value)} placeholder="colleague@yourcompany.com"/></label>
    <label>Initial access<select value={role} onChange={e=>setRole(e.target.value)}>
     <option value="editor">Editor — prepare drafts and assign tasks</option>
     <option value="viewer">Viewer — read company bids only</option>
    </select></label>
    <button className="btn btn-dark" type="submit" disabled={busy}>Generate invitation</button>
   </form>
   {issuedLink&&<div className="team-copy">
    <label>Private invitation link (only shown now)<input readOnly value={issuedLink} onFocus={e=>e.target.select()}/></label>
    <button type="button" className="btn btn-primary" onClick={()=>
     void navigator.clipboard.writeText(issuedLink).then(()=>setNotice("Link copied.")).
      catch(()=>setError("Select and copy the link manually."))}><Copy size={14}/> Copy link</button>
   </div>}
   <h3>Pending invitations</h3>
   <div className="team-member-list">{team.invitations.length===0&&<p className="tiny muted">No pending invitations.</p>}
    {team.invitations.map(inv=><div className="team-member-row" key={inv.id}>
     <div><strong>{inv.email}</strong><small>{inv.role} · Expires {new Date(inv.expires_at).toLocaleDateString()}</small></div>
     <button type="button" className="btn btn-secondary" disabled={busy}
      onClick={()=>void action(()=>request("team/invitations/"+inv.id+"/revoke","POST"),"Invitation revoked.")}>Revoke</button>
    </div>)}
   </div>
  </div>}
  <p className="tiny muted">Development controls. Corporate rollout still requires verified email delivery, SSO, incident response and security review.</p>
 </main>;
}
