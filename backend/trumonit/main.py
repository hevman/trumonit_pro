from fastapi import Depends, FastAPI, HTTPException
from fastapi.responses import JSONResponse
from .security import require_gateway
from . import auth, projects

app = FastAPI(
    title="Trumonit Supplier RFP Workbench",
    version="0.1.0",
    docs_url=None,
    redoc_url=None,
    openapi_url=None,
)


@app.get("/health")
def health():
    return {"status": "ok", "product": "trumonit"}


app.include_router(auth.router, prefix="/api", dependencies=[Depends(require_gateway)])
app.include_router(projects.router, prefix="/api", dependencies=[Depends(require_gateway)])


@app.exception_handler(Exception)
async def internal_error(request, exc: Exception):
    if isinstance(exc, HTTPException):
        raise exc
    # Avoid exposing internals, document contents or connection strings.
    return JSONResponse({"detail": "Internal service error"}, status_code=500)
