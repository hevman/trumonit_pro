"use client";

import {useCallback,useEffect,useState} from "react";
import {ArrowDownToLine,CheckCircle2,FileSpreadsheet,RefreshCcw,UploadCloud} from "lucide-react";

type SampleCell={column:string;value:string};
type SampleRow={row:number;cells:SampleCell[]};
type Sheet={name:string;max_row:number;max_column:number;sample:SampleRow[];protected:boolean};
type Preview={sheets:Sheet[];maximum_questions:number;notice:string};
type Workbook={document_id:string;filename:string;sheet_name:string;question_count:number;approved_count:number;final_export_ready:boolean};
type ApiError={detail?:string};

async function request<T>(url:string,options:RequestInit={}):Promise<T>{
  const result=await fetch("/api/"+url,{...options,credentials:"same-origin",cache:"no-store"});
  if(!result.ok){
    const data=await result.json().catch(()=>null) as ApiError|null;
    throw Error(data?.detail||"Request failed ("+result.status+")");
  }
  return result.json() as Promise<T>;
}

export default function SpreadsheetWorkbench({projectId,readonly,onImported}:{
  projectId:string;readonly:boolean;onImported:()=>Promise<void>;
}){
  const [file,setFile]=useState<File|null>(null);
  const [preview,setPreview]=useState<Preview|null>(null);
  const [sheetName,setSheetName]=useState("");
  const [questionColumn,setQuestionColumn]=useState("B");
  const [answerColumn,setAnswerColumn]=useState("C");
  const [startRow,setStartRow]=useState(2);
  const [endRow,setEndRow]=useState(3);
  const [workbooks,setWorkbooks]=useState<Workbook[]>([]);
  const [busy,setBusy]=useState(false);
  const [error,setError]=useState("");
  const [notice,setNotice]=useState("");
  const reload=useCallback(async()=>{
    setWorkbooks(await request<Workbook[]>("projects/"+projectId+"/spreadsheets"));
  },[projectId]);
  useEffect(()=>{void reload().catch(e=>setError(e instanceof Error?e.message:"Could not load workbooks"));},[reload]);

  async function inspect(){
    if(!file)return;
    setBusy(true);setError("");setNotice("");setPreview(null);
    try{
      if(file.size>8*1024*1024)throw Error("Maximum file size is 8 MiB");
      const form=new FormData();form.append("file",file);
      const result=await request<Preview>("projects/"+projectId+"/spreadsheets/preview",{
        method:"POST",body:form,
      });
      setPreview(result);
      const first=result.sheets.find(sheet=>!sheet.protected&&sheet.max_row>=2);
      setSheetName(first?.name||result.sheets[0]?.name||"");
      setStartRow(2);setEndRow(Math.min(first?.max_row||2,251));
      if(!first)setError("The workbook has no unprotected worksheet with question rows.");
    }catch(e){setError(e instanceof Error?e.message:"Could not inspect workbook");}
    finally{setBusy(false);}
  }
  async function importQuestions(event:React.FormEvent){
    event.preventDefault();
    if(!file||!preview||readonly)return;
    setBusy(true);setError("");setNotice("");
    try{
      const form=new FormData();
      form.append("file",file);
      form.append("sheet_name",sheetName);
      form.append("question_column",questionColumn);
      form.append("answer_column",answerColumn);
      form.append("start_row",String(startRow));
      form.append("end_row",String(endRow));
      const saved=await request<{questions_imported:number;blank_rows_skipped:number}>(
        "projects/"+projectId+"/spreadsheets",{method:"POST",body:form});
      setNotice(saved.questions_imported+" RFP questions imported. Review them below, prepare the answers and request owner approval.");
      setFile(null);setPreview(null);
      await Promise.all([reload(),onImported()]);
    }catch(e){setError(e instanceof Error?e.message:"Could not import mapped questions");}
    finally{setBusy(false);}
  }
  const current=preview?.sheets.find(sheet=>sheet.name===sheetName);
  const columns=Array.from({length:52},(_,index)=>index<26
    ?String.fromCharCode(65+index):"A"+String.fromCharCode(65+index-26));
  return <section className="panel xlsx-workbench">
    <div className="panel-heading"><div>
      <h2><FileSpreadsheet size={21}/> Respond to customer Excel RFPs</h2>
      <p>Map real buyer questions to their blank answer column. Keep the original workbook layout; export only approved answers. No AI.</p>
    </div><button type="button" className="btn btn-secondary" onClick={()=>void reload()} disabled={busy}><RefreshCcw size={15}/> Refresh</button></div>
    {error&&<div className="alert alert-error" role="alert">{error}</div>}
    {notice&&<div className="alert alert-good" role="status">{notice}</div>}
    {!readonly&&<div className="xlsx-import">
      <div className="xlsx-step"><b>1. Choose the buyer's .xlsx template</b>
        <div className="xlsx-upload">
          <input aria-label="Buyer XLSX workbook" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
            disabled={busy} onChange={e=>{setFile(e.target.files?.[0]||null);setPreview(null);}}/>
          <button type="button" className="btn btn-dark" disabled={!file||busy} onClick={()=>void inspect()}>
            <UploadCloud size={15}/> {busy?"Checking…":"Inspect workbook"}
          </button>
        </div>
        <p className="tiny muted">Maximum 8 MiB and 250 questions per mapping. No macros or external links. Review the exported Excel for fidelity before submitting it to a buyer.</p>
      </div>
      {preview&&<form className="xlsx-step" onSubmit={e=>void importQuestions(e)}>
        <b>2. Map questions and answer locations</b>
        <div className="xlsx-map-fields">
          <label>Worksheet<select required value={sheetName} disabled={busy} onChange={e=>{
            setSheetName(e.target.value);
            const selected=preview.sheets.find(s=>s.name===e.target.value);
            setStartRow(2);setEndRow(Math.min(selected?.max_row||2,251));
          }}>{preview.sheets.map(s=><option key={s.name} value={s.name} disabled={s.protected}>{s.name}{s.protected?" (protected)":""}</option>)}</select></label>
          <label>Question column<select value={questionColumn} disabled={busy} onChange={e=>setQuestionColumn(e.target.value)}>
            {columns.map(column=><option key={column}>{column}</option>)}</select></label>
          <label>Blank answer column<select value={answerColumn} disabled={busy} onChange={e=>setAnswerColumn(e.target.value)}>
            {columns.map(column=><option key={column}>{column}</option>)}</select></label>
          <label>From row<input type="number" min={1} max={5000} required disabled={busy} value={startRow}
            onChange={e=>setStartRow(Number(e.target.value))}/></label>
          <label>To row<input type="number" min={1} max={5000} required disabled={busy} value={endRow}
            onChange={e=>setEndRow(Number(e.target.value))}/></label>
        </div>
        {current&&<div className="xlsx-preview"><b>Workbook sample: {current.name}</b>
          <small>{current.max_row} rows · {current.max_column} columns · first 12 rows shown</small>
          {current.sample.map(row=><div key={row.row} className="xlsx-preview-row">
            <span>Row {row.row}</span>
            <span>{row.cells.map(cell=>cell.column+": "+cell.value).join("  |  ")}</span>
          </div>)}
        </div>}
        <button className="btn btn-primary" type="submit" disabled={
          busy||!current||current.protected||questionColumn===answerColumn||
          startRow<1||endRow<startRow||endRow>current.max_row||endRow-startRow>=250}>
          Import mapped RFP questions
        </button>
      </form>}
    </div>}
    <div className="xlsx-step">
      <b>3. Deliver an approved buyer workbook</b>
      {workbooks.length===0?<p className="tiny muted">No mapped Excel RFPs yet. Import a workbook to start.</p>:
        <div className="xlsx-workbooks">{workbooks.map(item=><article key={item.document_id} className="xlsx-file">
          <FileSpreadsheet size={23}/>
          <div className="xlsx-file-info"><strong>{item.filename}</strong>
            <small>{item.sheet_name} · {item.approved_count}/{item.question_count} answers approved</small>
            <span className={item.final_export_ready?"status status-met":"status status-unreviewed"}>
              {item.final_export_ready?<><CheckCircle2 size={13}/> Approved responses complete</>:"Approval in progress"}
            </span>
          </div>
          <div className="xlsx-file-actions">
            <a className="btn btn-secondary" href={"/api/projects/"+projectId+"/spreadsheets/"+item.document_id+"/export?mode=working"}>
              <ArrowDownToLine size={15}/> Working copy</a>
            {item.final_export_ready&&<a className="btn btn-dark" href={"/api/projects/"+projectId+"/spreadsheets/"+item.document_id+"/export"}>
              <CheckCircle2 size={15}/> Export approved XLSX</a>}
          </div>
        </article>)}</div>}
      <p className="tiny muted">Working copies include approved answers only. Final export is available after every mapped question is marked Met and the response has owner approval. This does not certify legal completeness.</p>
    </div>
  </section>;
}
