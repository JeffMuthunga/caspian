# tests/test_generate.py
import pytest
from unittest.mock import AsyncMock, MagicMock, patch
from httpx import AsyncClient

MOCK_CHUNKS = [
    {
        "chunk_text": "ProductRecall [RWANDA_FDA:1] — recall_number: RCL-001 | status: active | classification: Class II",
        "object_id": "obj-recall-1",
        "metadata": {"object_type": "ProductRecall", "source_nmra": "RWANDA_FDA"},
        "score": 0.95,
    },
    {
        "chunk_text": "Manufacturer [RWANDA_FDA:1] — name: PharmaCo Ltd | country: Kenya",
        "object_id": "obj-mfg-1",
        "metadata": {"object_type": "Manufacturer", "source_nmra": "RWANDA_FDA"},
        "score": 0.88,
    },
]

@pytest.mark.asyncio
async def test_query_returns_cited_answer_with_sources(client: AsyncClient):
    async def mock_stream_lines():
        yield '{"response": "Rwanda FDA issued recall RCL-001 [ProductRecall RWANDA_FDA:1].", "done": false}'
        yield '{"response": "", "done": true}'

    mock_stream_ctx = AsyncMock()
    mock_stream_ctx.aiter_lines = mock_stream_lines
    mock_stream_ctx.__aenter__ = AsyncMock(return_value=mock_stream_ctx)
    mock_stream_ctx.__aexit__ = AsyncMock(return_value=False)

    mock_http_instance = MagicMock()
    mock_http_instance.stream = MagicMock(return_value=mock_stream_ctx)

    with patch("generate.semantic_search", return_value=MOCK_CHUNKS), \
         patch("generate.httpx.AsyncClient") as mock_http:
        mock_http.return_value.__aenter__ = AsyncMock(return_value=mock_http_instance)
        mock_http.return_value.__aexit__ = AsyncMock(return_value=False)

        response = await client.post("/ai/query", json={
            "question": "Are there any active recalls I should know about before approving this batch import?",
            "nmra_id": "NAFDAC",
        })

    assert response.status_code == 200
    body = response.json()
    assert "answer" in body
    assert "sources" in body
    assert len(body["sources"]) == 2
    assert body["sources"][0]["object_id"] == "obj-recall-1"

@pytest.mark.asyncio
async def test_query_returns_no_data_message_when_no_chunks(client: AsyncClient):
    with patch("generate.semantic_search", return_value=[]):
        response = await client.post("/ai/query", json={
            "question": "Are there any recalls?",
            "nmra_id": "NAFDAC",
        })

    assert response.status_code == 200
    assert response.json()["answer"] == "No relevant regulatory data found."
    assert response.json()["sources"] == []
