import {NextRequest,NextResponse} from "next/server";

type Context={params:Promise<{path:string[]}>};
const cookieName="trumonit_session";
const grantRoutes=new Set(["auth/login","auth/register","team/claim"]);

async function forward(req:NextRequest,ctx:Context){
  const origin=process.env.API_INTERNAL_URL;
  const gateway=process.env.TRUMONIT_GATEWAY_TOKEN;
  if(!origin||!gateway||gateway.length<32||gateway.startsWith("REPLACE_"))
    return NextResponse.json({detail:"API gateway not configured"},{status:503});
  const {path}=await ctx.params;
  if(!path?.length||path.length>6||path.some(p=>!/^[a-zA-Z0-9_-]{1,80}$/.test(p)))
    return NextResponse.json({detail:"Invalid route"},{status:400});
  const route=path.join("/");
  const site=req.headers.get("sec-fetch-site");
  const requestOrigin=req.headers.get("origin");
  if(req.method!=="GET"&&req.method!=="HEAD"){
    if(site==="cross-site")return NextResponse.json({detail:"Cross-site request denied"},{status:403});
    if(requestOrigin){
      try {
        if(new URL(requestOrigin).host!==req.headers.get("host"))
          return NextResponse.json({detail:"Untrusted origin"},{status:403});
      }catch{return NextResponse.json({detail:"Untrusted origin"},{status:403});}
    }
  }
  let body:ArrayBuffer|undefined;
  if(req.method==="POST"||req.method==="PATCH"){
    const limit=9*1024*1024;
    if(Number(req.headers.get("content-length")||0)>limit)
      return NextResponse.json({detail:"Upload too large"},{status:413});
    body=await req.arrayBuffer();
    if(body.byteLength>limit)
      return NextResponse.json({detail:"Upload too large"},{status:413});
  }
  const session=req.cookies.get(cookieName)?.value;
  const headers:Record<string,string>={
    "X-Trumonit-Gateway":gateway,
    ...(session?{"X-Trumonit-Session":session}:{}),
  };
  const contentType=req.headers.get("content-type");
  if(contentType&&(req.method==="POST"||req.method==="PATCH"))
    headers["Content-Type"]=contentType;
  try{
    const result=await fetch(origin.replace(/\/$/,"")+"/api/"+route,{
      method:req.method,headers,body,
      cache:"no-store",signal:AbortSignal.timeout(30000),
    });
    if(result.ok&&req.method==="POST"&&grantRoutes.has(route)){
      const grant=await result.json() as {session_token?:string;account?:unknown};
      if(!grant.session_token||!/^[a-zA-Z0-9_-]{43}$/.test(grant.session_token))
        return NextResponse.json({detail:"Invalid authentication response"},{status:502});
      const out=NextResponse.json(grant.account,{status:result.status});
      out.cookies.set(cookieName,grant.session_token,{
        httpOnly:true,sameSite:"strict",secure:req.nextUrl.protocol==="https:",
        path:"/",maxAge:7*24*3600,
      });
      out.headers.set("Cache-Control","no-store");
      return out;
    }
    const data=await result.arrayBuffer();
    const contentDisposition=result.headers.get("content-disposition");
    const response=new NextResponse(data,{
      status:result.status,
      headers:{
        "Content-Type":result.headers.get("content-type")||"application/json",
        "Cache-Control":"no-store",
        "X-Content-Type-Options":"nosniff",
        ...(contentDisposition?{"Content-Disposition":contentDisposition}:{}),
      },
    });
    if(route==="auth/logout"&&result.ok)response.cookies.delete(cookieName);
    if(result.status===401&&route==="auth/me")response.cookies.delete(cookieName);
    return response;
  }catch{
    return NextResponse.json({detail:"Trumonit API temporarily unavailable"},{status:502});
  }
}
export async function GET(req:NextRequest,ctx:Context){return forward(req,ctx);}
export async function POST(req:NextRequest,ctx:Context){return forward(req,ctx);}
export async function PATCH(req:NextRequest,ctx:Context){return forward(req,ctx);}
