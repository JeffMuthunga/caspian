# retrieve.py
import os
import httpx
from fastapi import APIRouter
from pydantic import BaseModel
from sqlalchemy import select, text
from database import SessionLocal
from models import PublishedObject, Embedding

router = APIRouter(prefix="/ai", tags=["ai"])

OLLAMA_HOST = os.getenv("OLLAMA_HOST", "http://localhost:11434")
EMBED_MODEL  = "nomic-embed-text"


class IngestRequest(BaseModel):
    object_ids: list[str]


async def embed_text(text_input: str) -> list[float]:
    async with httpx.AsyncClient(timeout=60) as client:
        resp = await client.post(
            f"{OLLAMA_HOST}/api/embeddings",
            json={"model": EMBED_MODEL, "prompt": text_input},
        )
        return resp.json()["embedding"]


def object_to_chunk(obj: PublishedObject) -> str:
    props = " | ".join(f"{k}: {v}" for k, v in obj.properties.items())
    return f"{obj.object_type} [{obj.source_nmra}:{obj.source_id}] — {props}"


@router.post("/ingest")
async def ingest(req: IngestRequest):
    ingested = 0
    async with SessionLocal() as session:
        for oid in req.object_ids:
            obj = await session.get(PublishedObject, oid)
            if not obj:
                continue

            existing = (await session.execute(
                select(Embedding).where(Embedding.object_id == oid)
            )).scalar_one_or_none()
            if existing:
                continue

            chunk = object_to_chunk(obj)
            vector = await embed_text(chunk)

            session.add(Embedding(
                object_id=oid,
                chunk_text=chunk,
                embedding=vector,
                extra_metadata={"object_type": obj.object_type, "source_nmra": obj.source_nmra},
            ))
            ingested += 1

        await session.commit()
    return {"ingested": ingested}


async def semantic_search(query: str, nmra_id: str, top_k: int = 10) -> list[dict]:
    vector = await embed_text(query)
    vector_str = "[" + ",".join(str(v) for v in vector) + "]"

    async with SessionLocal() as session:
        rows = (await session.execute(text("""
            SELECT e.chunk_text, e.object_id, e.metadata,
                   1 - (e.embedding <=> :vec::vector) AS score
            FROM embeddings e
            JOIN object_markings m ON m.object_id = e.object_id
            WHERE m.nmra_id = :nmra AND m.can_read = true
            ORDER BY e.embedding <=> :vec::vector
            LIMIT :top_k
        """), {"vec": vector_str, "nmra": nmra_id, "top_k": top_k})).fetchall()

        return [
            {
                "chunk_text": r.chunk_text,
                "object_id":  str(r.object_id),
                "metadata":   r.metadata,
                "score":      float(r.score),
            }
            for r in rows
        ]
