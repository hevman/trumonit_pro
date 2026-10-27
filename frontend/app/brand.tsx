export function BrandSymbol({className = ""}: {className?: string}) {
 return <svg className={"brand-symbol " + className} viewBox="0 0 40 40" fill="none" aria-hidden="true"><path d="M7 7h26v7H23v19h-7V14H7V7Z" fill="currentColor"/><path d="m27 25 3 3 6-7" stroke="var(--brand-accent, #B48A50)" strokeWidth="3" strokeLinecap="square" strokeLinejoin="miter"/></svg>;
}
export default function Brand({compact = false}: {compact?: boolean}) {
 return <span className={"brand-lockup" + (compact ? " compact" : "")}><BrandSymbol/>{!compact && <span className="brand-wordmark">trumonit<span className="brand-descriptor">BID RESPONSE WORKSPACE</span></span>}</span>;
}
