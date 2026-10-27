import {NextRequest,NextResponse} from "next/server";

export function proxy(req:NextRequest){
  const pathname=req.nextUrl.pathname;
  if(pathname.startsWith("/app")&&!req.cookies.has("trumonit_session")){
    const login=new URL("/login",req.url);
    return NextResponse.redirect(login);
  }
  return NextResponse.next();
}
export const config={matcher:["/app/:path*"]};
