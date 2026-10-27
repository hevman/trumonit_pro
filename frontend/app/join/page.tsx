"use client";

import {useEffect,useState} from "react";
import {useRouter} from "next/navigation";
import Link from "next/link";
import {ArrowRight,LockKeyhole,Users} from "lucide-react";

type Preview={workspace_name:string;email:string;role:string};
type ErrorPayload={detail?:string};
async function api<T>(route:string,body:object):Promise<T>{
 const response=await fetch("/api/"+route,{
  method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(body),
  credentials:"same-origin",cache:"no-store",
 });
 if(!response.ok){
  const problem=await response.json().catch(()=>null) as ErrorPayload|null;
  throw Error(problem?.detail||"Request failed ("+response.status+")");
 }
 return response.json() as Promise<T>;
}
export default function JoinPage(){
 const router=useRouter();
 const [token,setToken]=useState("");
 const [preview,setPreview]=useState<Preview|null>(null);
 const [name,setName]=useState("");
 const [email,setEmail]=useState("");
 const [password,setPassword]=useState("");
 const [existing,setExisting]=useState(false);
 const [busy,setBusy]=useState(false);
 const [error,setError]=useState("");
 useEffect(()=>{
  const params=new URLSearchParams(window.location.hash.replace(/^#/,""));
  const candidate=params.get("token")||"";
  window.history.replaceState(null,"",window.location.pathname);
  if(!/^[A-Za-z0-9_-]{43}$/.test(candidate)){
   setError("Invalid or missing private invitation token.");return;
  }
  setToken(candidate);
  api<Preview>("team/preview",{token:candidate}).then(info=>{
   setPreview(info);setEmail(info.email);
  }).catch(e=>setError(e instanceof Error?e.message:"Invitation unavailable"));
 },[]);
 async function submit(event:React.FormEvent){
  event.preventDefault();if(!preview||!token)return;
  setBusy(true);setError("");
  try{
   if(existing){
    const identity=await api<{email:string}>("auth/login",{email,password});
    if(identity.email.toLowerCase()!==preview.email.toLowerCase())
     throw Error("Sign in with the exact email address named in this invitation.");
    await api("team/accept",{token});
   }else{
    await api("team/claim",{token,name,password});
   }
   setToken("");
   router.replace("/app");router.refresh();
  }catch(e){setError(e instanceof Error?e.message:"Could not join workspace");}
  finally{setBusy(false);}
 }
 return <main className="container auth-page">
  <section className="auth-intro"><span className="eyebrow">SECURE TEAM INVITATION</span>
   <h1>Join your team's tender workspace.</h1>
   <p>The organization owner shared a private invitation. Access is scoped to your company only.</p>
   <div className="auth-benefits"><div><Users size={20}/> Work on shared bid documents and tasks</div>
    <div><LockKeyhole size={20}/> No access to other customers' data</div></div>
  </section>
  <section className="panel auth-panel">
   <span className="eyebrow">TRUMONIT TEAM ACCESS</span>
   <h2>{preview?preview.workspace_name:"Invitation"}</h2>
   {preview?<p className="muted">Invited email: <strong>{preview.email}</strong><br/>Role: <strong>{preview.role}</strong></p>:<p className="muted">Checking invitation validity…</p>}
   {error&&<div className="alert alert-error" role="alert">{error}</div>}
   {preview&&<form className="auth-form" onSubmit={submit}>
    <label><input type="checkbox" checked={existing} onChange={e=>setExisting(e.target.checked)}/> I already have a Trumonit account</label>
    {!existing&&<label>Full name<input required autoComplete="name" minLength={2} maxLength={100}
     value={name} onChange={e=>setName(e.target.value)}/></label>}
    <label>Email<input type="email" readOnly value={email}/></label>
    <label>Password<input type="password" required
     autoComplete={existing?"current-password":"new-password"}
     minLength={existing?1:12} maxLength={128} value={password}
     onChange={e=>setPassword(e.target.value)}/></label>
    <button className="btn btn-primary full" disabled={busy||!token} type="submit">
     {busy?"Joining…":"Join workspace"} <ArrowRight size={16}/></button>
   </form>}
   <p className="tiny muted">Invitation links are valid for three days, are usable once and should be shared privately. This early implementation has no verified-email delivery or SSO.</p>
   <Link className="auth-home" href="/">← Trumonit home</Link>
  </section>
 </main>;
}
