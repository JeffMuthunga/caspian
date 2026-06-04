from contextlib import asynccontextmanager
from fastapi import FastAPI
from ontology import router as ontology_router
from retrieve import router as retrieve_router
from generate import router as generate_router


@asynccontextmanager
async def lifespan(app: FastAPI):
    yield


app = FastAPI(title="AI Ontology Service", lifespan=lifespan)

app.include_router(ontology_router)
app.include_router(retrieve_router)
app.include_router(generate_router)


@app.get("/health")
async def health():
    return {"status": "ok"}
