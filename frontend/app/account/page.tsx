"use client";

import {useEffect,useState} from "react";
import Link from "next/link";
import {useRouter} from "next/navigation";
import {ArrowRight,KeyRound,MailCheck,ShieldCheck} from "lucide-react";

type ErrorData={detail?:string};
async function api<T>(route:string,payload:object):Promise<T>{
 const res=await fetch("/api/auth/"+route,{
  method:"POST",cache:"no-store",credentials:"same-origin",
  headers:{"Content-Type":"application/json"},body:JSON.stringify(payload),
 });
 if(!res.ok){
  const data=await res.json().catch(()=>null) as ErrorData|null;
  throw Error(data?.detail||"Account request failed ("+res.status+")");
 }
 return res.json() as Promise<T>;
}

export default function AccountSecurity(){
 const router=useRouter();
 const [mode,setMode]=useState<"verify"|"reset"|"request-verify"|"forgot">("forgot");
 const [secret,setSecret]=useState("");
 const [email,setEmail]=useState("");
 const [password,setPassword]=useState("");
 const [busy,setBusy]=useState(false);
 const [error,setError]=useState("");
 const [notice,setNotice]=useState("");
 useEffect(()=>{
  const url=new URL(window.location.href);
  const values=new URLSearchParams(url.hash.replace(/^#/,""));
  const verify=values.get("verify");
  const reset=values.get("reset");
  if(verify||reset){
   setSecret((verify||reset)||"");
   setMode(verify?"verify":"reset");
  }else if(url.searchParams.get("mode")==="verify"){setMode("request-verify");}
  // The bearer token is cleared from the browser URL and its history entry.
  window.history.replaceState(null,"",window.location.pathname);
 },[]);
 async function submit(event:React.FormEvent){
  event.preventDefault();setBusy(true);setError("");setNotice("");
  try{
   if(mode==="verify"){
    await api("verify",{token:secret});
    setSecret("");
    router.replace("/app");router.refresh();
   }else if(mode==="reset"){
    await api("password/reset",{token:secret,password});
    setSecret("");setPassword("");
    setNotice("Password updated. All previous sessions have been revoked. Sign in with the new password.");
    setMode("forgot");
   }else{
    await api(mode==="forgot"?"password/forgot":"verification/request",{email});
    setNotice("If your account is eligible, a private link was sent to the provided address.");
   }
  }catch(e){setError(e instanceof Error?e.message:"Request failed");}
  finally{setBusy(false);}
 }
 return <main className="container auth-page">
  <section className="auth-intro">
   <span className="eyebrow">SECURE COMPANY ACCESS</span>
   <h1>Protect access to your organization's data.</h1>
   <p>Confirm work-email ownership, recover an account securely and revoke old sessions after a password reset.</p>
   <div className="auth-benefits">
    <div><MailCheck size={20}/> One-time, short-lived verification links</div>
    <div><ShieldCheck size={20}/> No reset or verification token exposed through the API</div>
   </div>
   <p className="auth-disclaimer">The mail service must be explicitly configured. SMTP credentials and production HTTPS are required for a live deployment.</p>
  </section>
  <section className="panel auth-panel">
   <span className="eyebrow">ACCOUNT SECURITY</span>
   <h2>{mode==="verify"?"Verify work email":mode==="reset"?"Choose new password":mode==="forgot"?"Reset your password":"Resend verification email"}</h2>
   {error&&<div className="alert alert-error" role="alert">{error}</div>}
   {notice&&<div className="alert alert-good" role="status">{notice}</div>}
   <form className="auth-form" onSubmit={submit}>
    {(mode==="verify"||mode==="reset")&&<p className="muted">A private link was opened. This token is valid once and expires automatically.</p>}
    {(mode==="forgot"||mode==="request-verify")&&
     <label>Work email<input type="email" required autoComplete="email" maxLength={254}
      value={email} onChange={e=>setEmail(e.target.value)}/></label>}
    {mode==="reset"&&<label>New password<input type="password" required autoComplete="new-password"
     minLength={12} maxLength={128} value={password} onChange={e=>setPassword(e.target.value)}/>
     <small>At least 12 characters.</small></label>}
    <button className="btn btn-primary full" disabled={busy||((mode==="reset"||mode==="verify")&&!secret)} type="submit">
     {busy?"Processing…":mode==="verify"?"Verify and sign in":mode==="reset"?"Update password":mode==="forgot"?"Send password reset link":"Send verification link"} <ArrowRight size={16}/>
    </button>
   </form>
   <p className="auth-toggle">
    <button type="button" onClick={()=>{setMode(mode==="forgot"?"request-verify":"forgot");setError("");setNotice("");}}>
     {mode==="forgot"?"Need an email verification link?":"Forgot password?"}
    </button>
   </p>
   <Link href="/login" className="auth-home"><KeyRound size={14}/> Back to sign-in</Link>
  </section>
 </main>;
}
