import type {Metadata} from "next";
import Link from "next/link";
import "./globals.css";

export const metadata: Metadata={
  title:{default:"Trumonit — Tender intelligence",template:"%s | Trumonit"},
  description:"A structured, source-linked workspace for B2B tender and RFP responses. Make evidence review faster.",
  robots:{index:true,follow:true},
};

export default function RootLayout({children}:Readonly<{children:React.ReactNode}>){
  return <html lang="en"><body>
    <header className="topbar"><div className="container topbar-inner">
      <Link className="brand" href="/"><span className="brand-mark">T<span>.</span></span><span>trumonit<span className="brand-domain">.pro</span></span></Link>
      <nav aria-label="Main navigation"><Link href="/#workflow">Workflow</Link><Link href="/#features">Features</Link><Link href="/#pricing">Pricing</Link><Link href="/login">Sign in</Link><Link className="nav-cta" href="/app">Open workspace <span aria-hidden="true">↗</span></Link></nav>
    </div></header>
    {children}
    <footer className="footer"><div className="container footer-inner"><div><b>trumonit.pro</b><span>Built for teams who win complex work.</span></div><p>Document analysis surfaces review candidates, not legal advice or a guarantee of bid compliance.</p><span>© 2026 Trumonit</span></div></footer>
  </body></html>;
}
