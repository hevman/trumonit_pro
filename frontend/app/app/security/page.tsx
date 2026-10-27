"use client";

import Link from "next/link";
import {useCallback,useEffect,useState} from "react";
import {ArrowLeft,KeyRound,Lock,MonitorSmartphone,ShieldCheck,Trash2} from "lucide-react";

type SessionEntry={id:string;workspace_name:string;created_at:string;expires_at:string;current:boolean};
type ApiError={detail?:string};

async function api<T>(path:string,options:RequestInit={}):Promise<T>{
  const result=await fetch("/api/"+path,{
    ...options,credentials:"same-origin",cache:"no-store",
  });
  if(!result.ok){
    const data=await result.json().catch(()=>null) as ApiError|null;
    throw Error(data?.detail||"Request failed ("+result.status+")");
  }
  return result.json() as Promise<T>;
}

export default function SecuritySettings(){
  const [sessions,setSessions]=useState<SessionEntry[]>([]);
  const [password,setPassword]=useState("");
  const [busy,setBusy]=useState(false);
  const [error,setError]=useState("");
  const [message,setMessage]=useState("");
  const load=useCallback(async()=>{
    const result=await api<SessionEntry[]>("auth/sessions");
    setSessions(result);
  },[]);
  useEffect(()=>{void load().catch(e=>setError(e instanceof Error?e.message:"Could not load sessions"));},[load]);

  async function revoke(id?:string){
    if(!password)return;
    setBusy(true);setError("");setMessage("");
    try{
      const endpoint=id?"auth/sessions/"+id+"/revoke":"auth/sessions/revoke-others";
      const answer=await api<{revoked:number|boolean}>(endpoint,{
        method:"POST",headers:{"Content-Type":"application/json"},
        body:JSON.stringify({password}),
      });
      setPassword("");
      await load();
      setMessage(id?"The selected session was revoked.":"Other active sessions were revoked ("+answer.revoked+").");
    }catch(e){setError(e instanceof Error?e.message:"Could not revoke session");}
    finally{setBusy(false);}
  }
  const otherSessions=sessions.filter(s=>!s.current);
  return <main className="container security-page">
    <Link href="/app" className="security-return"><ArrowLeft size={16}/> Back to bid workspace</Link>
    <div className="security-header"><span className="eyebrow">ACCOUNT SECURITY</span>
      <h1>Active sessions</h1>
      <p>Review your Trumonit sign-ins and end sessions you no longer recognize. Changes take effect on the next authenticated request.</p></div>
    {error&&<p className="alert alert-error" role="alert">{error}</p>}
    {message&&<p className="alert alert-good" role="status">{message}</p>}
    <section className="panel security-panel">
      <h2><MonitorSmartphone size={22}/> Your active logins</h2>
      <p>Sessions across every organization attached to your account are included. Each session expires within seven days.</p>
      <div className="security-session-list">
        {sessions.length===0&&<p>No active sessions were returned.</p>}
        {sessions.map(item=><div key={item.id} className="security-session">
          <div className="security-device"><span className="security-device-icon"><Lock size={19}/></span>
            <div><strong>{item.current?"This session":"Another session"}</strong>
              <small>Workspace: {item.workspace_name}</small>
              <small>Created: {new Date(item.created_at).toLocaleString()}</small>
              <small>Expires: {new Date(item.expires_at).toLocaleString()}</small>
            </div></div>
          {item.current?<span className="status status-met"><ShieldCheck size={14}/> Current</span>:
            <button type="button" className="btn btn-secondary" disabled={busy||!password}
              onClick={()=>void revoke(item.id)}><Trash2 size={15}/> Revoke</button>}
        </div>)}
      </div>
      <div className="security-confirmation">
        <label htmlFor="session-confirm-password"><KeyRound size={15}/> Confirm your current password
          <input id="session-confirm-password" type="password"
            autoComplete="current-password" minLength={1} maxLength={128}
            placeholder="Enter your account password"
            value={password} onChange={event=>setPassword(event.target.value)}/>
        </label>
        <button type="button" className="btn btn-primary"
          disabled={busy||!password||otherSessions.length===0}
          onClick={()=>void revoke()}>{busy?"Updating…":"Sign out all other sessions"}</button>
      </div>
      <p className="tiny muted">This list does not collect device fingerprints, precise location or IP addresses. To end this browser's session, use Sign out in the workspace.</p>
    </section>
    <p className="tiny muted">Enterprise SSO, MFA and managed device policies are not yet enabled. Password reset already revokes all existing sessions.</p>
  </main>;
}
