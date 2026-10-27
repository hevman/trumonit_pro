"use client";
import Link from "next/link";
import { usePathname } from "next/navigation";
export default function SiteFrame({children}: {children: React.ReactNode}) {
 const path = usePathname();
 const workspace = path === "/app" || path.startsWith("/app/") || path === "/team";
 const auth = ["/login", "/account", "/join"].includes(path);
 if (workspace) return <>{children}</>;
 return <><a className="skip-link" href="#content">Skip to content</a><header className="topbar"><div className="container topbar-inner">
 <Link className="brand" href="/" aria-label="Trumonit home"><span className="brand-mark">t.</span><span>trumonit</span></Link>
 <nav aria-label="Main navigation">{!auth && <><Link href="/#workflow">Workflow</Link><Link href="/#features">Product</Link><Link href="/#access">Get in touch</Link></>}<Link className="nav-cta" href={auth ? "/" : "/login"}>{auth ? "Home" : "Sign in"} <span aria-hidden="true">↗</span></Link></nav>
 </div></header><div id="content">{children}</div>{!auth && <footer className="footer"><div className="container footer-inner"><div><b>trumonit</b><span>Clarity for the work behind your bid.</span></div><p>Requirement candidates need human review. Trumonit does not certify compliance or submit bids.</p><a href="mailto:hello@trumonit.pro">hello@trumonit.pro ↗</a></div></footer>}</>;
}
