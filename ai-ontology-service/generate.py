# generate.py
import os
import json
import httpx
from fastapi import APIRouter
from pydantic import BaseModel
from retrieve import semantic_search

router = APIRouter()

OLLAMA_HOST = os.getenv("OLLAMA_HOST", "http://localhost:11434")
LLM_MODEL   = "gemma3:4b"

SYSTEM_PROMPT = """You are a regulatory intelligence assistant for National Medicines Regulatory Authorities.
You answer questions using ONLY the context provided. Every factual claim MUST include a citation in
the format [ObjectType SOURCE_NMRA:source_id]. If the context does not contain the answer, say so.
Do not invent information."""


class QueryRequest(BaseModel):
    question: str
    nmra_id:  str = "NAFDAC"


@router.post("/ai/query")
async def query(req: QueryRequest):
    chunks = await semantic_search(req.question, req.nmra_id)

    if not chunks:
        return {"answer": "No relevant regulatory data found.", "sources": []}

    context = "\n\n".join(
        f"[{c['metadata'].get('object_type', 'Object')} {c['metadata'].get('source_nmra', '')}:{c['object_id']}]\n{c['chunk_text']}"
        for c in chunks
    )
    prompt = f"Context:\n{context}\n\nQuestion: {req.question}\n\nAnswer (cite every claim):"

    answer_parts = []
    async with httpx.AsyncClient(timeout=120) as http_client:
        async with http_client.stream("POST", f"{OLLAMA_HOST}/api/generate", json={
            "model":  LLM_MODEL,
            "system": SYSTEM_PROMPT,
            "prompt": prompt,
            "stream": True,
        }) as stream:
            async for line in stream.aiter_lines():
                if line:
                    chunk = json.loads(line)
                    answer_parts.append(chunk.get("response", ""))
                    if chunk.get("done"):
                        break

    return {
        "answer":  "".join(answer_parts),
        "sources": [
            {"object_id": c["object_id"], "chunk_text": c["chunk_text"]}
            for c in chunks
        ],
    }
