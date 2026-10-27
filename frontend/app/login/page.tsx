"use client";
import {useEffect,useState} from "react";
import {useRouter} from "next/navigation";
import Link from "next/link";
import {ArrowRight,LockKeyhole,ShieldCheck} from "lucide-react";

export default function Login(){
  const router=useRouter();
  const [register,setRegister]=useState(false);
  const [email,setEmail]=useState("");
  const [password,setPassword]=useState("");
  const [name,setName]=useState("");
  const [workspaceName,setWorkspaceName]=useState("");
  const [busy,setBusy]=useState(false);
  const [error,setError]=useState("");
  useEffect(()=>{
    if(new URLSearchParams(window.location.search).get("mode")==="register")
      setRegister(true);
  },[]);
  async function submit(e:React.FormEvent){
    e.preventDefault();setBusy(true);setError("");
    try{
      const response=await fetch("/api/auth/"+(register?"register":"login"),{
        method:"POST",cache:"no-store",credentials:"same-origin",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify(register
          ?{email,password,name,workspace_name:workspaceName}
          :{email,password}),
      });
      if(!response.ok){
        const data=await response.json().catch(()=>null) as {detail?:string}|null;
        throw Error(data?.detail||"Could not access workspace");
      }
      router.replace("/app");router.refresh();
    }catch(e){setError(e instanceof Error?e.message:"Request failed");}
    finally{setBusy(false);}
  }
  return <main className="auth-page container">
    <section className="auth-intro">
      <span className="eyebrow">PRIVATE BID WORKSPACE</span>
      <h1>Every response starts with clarity.</h1>
      <p>Create a company workspace, add tender documents and build an auditable requirement register.</p>
      <div className="auth-benefits"><div><ShieldCheck size={20}/> Company-scoped project access</div><div><LockKeyhole size={20}/> Encrypted passwords and revocable sessions</div></div>
      <p className="auth-disclaimer">Development preview: email verification, subscription billing and enterprise review are not active. Do not upload confidential live tender material yet.</p>
    </section>
    <section className="auth-panel panel">
      <span className="eyebrow">TRUMONIT WORKSPACE</span>
      <h2>{register?"Create your account":"Welcome back"}</h2>
      <p className="muted">{register?"Start a new, empty organization.":"Sign in to review your bids and requirements."}</p>
      {error&&<div className="alert alert-error" role="alert">{error}</div>}
      <form onSubmit={submit} className="auth-form">
        {register&&<>
          <label>Full name<input autoComplete="name" value={name} maxLength={100} required minLength={2} onChange={e=>setName(e.target.value)}/></label>
          <label>Organization<input value={workspaceName} maxLength={100} required minLength={2} onChange={e=>setWorkspaceName(e.target.value)}/></label>
        </>}
        <label>Work email<input type="email" autoComplete="email" required maxLength={254} value={email} onChange={e=>setEmail(e.target.value)}/></label>
        <label>Password<input type="password" autoComplete={register?"new-password":"current-password"}
          minLength={register?12:1} maxLength={128} required value={password} onChange={e=>setPassword(e.target.value)}/>
          {register&&<small>Minimum 12 characters.</small>}</label>
        <button className="btn btn-primary full" type="submit" disabled={busy}>{busy?"Processing…":register?"Create workspace":"Sign in"} <ArrowRight size={16}/></button>
      </form>
      <p className="auth-toggle">{register?"Already have an account?":"New to Trumonit?"}{" "}
        <button type="button" onClick={()=>{setRegister(!register);setError("");}}>
          {register?"Sign in":"Create a workspace"}</button>
      </p>
      <Link href="/" className="auth-home">← Back to trumonit.pro</Link>
    </section>
  </main>;
}
