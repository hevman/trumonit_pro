"use client";
import Link from "next/link";
import { usePathname } from "next/navigation";
import Brand from "./brand";
export default function SiteFrame({children}: {children: React.ReactNode}) {
 const path = usePathname();
 const workspace = path === "/app" || path.startsWith("/app/") || path === "/team";
 const auth = ["/login", "/account", "/join"].includes(path);
 if (workspace) return <>{children}</>;
 return <><a className="skip-link" href="#content">Skip to content</a><header className="topbar"><div className="container topbar-inner">
 <Link className="brand" href="/" aria-label="Trumonit home"><Brand/></Link>
 <nav aria-label="Main navigation">{!auth && <><Link href="/#workflow">How it works</Link><Link href="/#features">Workspace</Link><Link href="/#teams">Who it’s for</Link></>}<Link className="nav-signin" href={auth ? "/" : "/login"}>{auth ? "Home" : "Sign in"}</Link>{!auth && <a className="nav-cta" href="mailto:hello@trumonit.pro?subject=Trumonit%20product%20walkthrough">Discuss access <span aria-hidden="true">↗</span></a>}</nav>
 </div></header><div id="content">{children}</div>{!auth && <footer className="footer"><div className="container"><div className="footer-main"><div><Link className="brand" href="/" aria-label="Trumonit home"><Brand/></Link><p>One workspace for the detail<br/>behind your next proposal.</p></div><div><span className="footer-label">EXPLORE</span><Link href="/#workflow">How it works</Link><Link href="/#features">Workspace capabilities</Link><Link href="/login">Sign in to your workspace</Link></div><div><span className="footer-label">LET’S TALK</span><a href="mailto:hello@trumonit.pro">hello@trumonit.pro ↗</a><p>Tell us about your team<br/>and your tender workflow.</p></div></div><div className="footer-bottom"><span>© 2026 Trumonit</span><p>Requirement candidates need human review. Trumonit does not certify compliance or submit bids.</p></div></div></footer>}</>;
}
