import type { Metadata } from "next";
import SiteFrame from "./site-frame";
import "./globals.css";
export const metadata: Metadata = {
 metadataBase: new URL("https://trumonit.pro"),
 title: { default: "Trumonit — A clearer way to respond to tenders", template: "%s | Trumonit" },
 description: "A source-linked bid workspace for proposal teams. Review tender requirements, reuse approved answers and coordinate your response.",
 robots: { index: true, follow: true },
};
export default function RootLayout({children}: Readonly<{children: React.ReactNode}>) {
 return <html lang="en"><body><SiteFrame>{children}</SiteFrame></body></html>;
}
