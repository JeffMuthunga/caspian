# tests/test_retrieve.py
import pytest
from unittest.mock import AsyncMock, MagicMock, patch
from httpx import AsyncClient

@pytest.mark.asyncio
async def test_ingest_calls_ollama_embed_and_stores_embedding(client: AsyncClient):
    mock_obj = MagicMock()
    mock_obj.id = "obj-1"
    mock_obj.object_type = "ProductRecall"
    mock_obj.source_nmra = "RWANDA_FDA"
    mock_obj.source_id = "1"
    mock_obj.properties = {"recall_number": "RCL-001", "status": "active"}

    mock_session = AsyncMock()
    mock_session.get = AsyncMock(return_value=mock_obj)
    mock_session.execute = AsyncMock(return_value=MagicMock(scalar_one_or_none=MagicMock(return_value=None)))
    mock_session.add = MagicMock()
    mock_session.commit = AsyncMock()

    mock_embed_response = MagicMock()
    mock_embed_response.json.return_value = {"embedding": [0.1] * 768}

    with patch("retrieve.SessionLocal") as mock_db, \
         patch("retrieve.httpx.AsyncClient") as mock_http:
        mock_db.return_value.__aenter__ = AsyncMock(return_value=mock_session)
        mock_db.return_value.__aexit__ = AsyncMock(return_value=False)

        mock_http_instance = AsyncMock()
        mock_http.return_value.__aenter__ = AsyncMock(return_value=mock_http_instance)
        mock_http.return_value.__aexit__ = AsyncMock(return_value=False)
        mock_http_instance.post = AsyncMock(return_value=mock_embed_response)

        response = await client.post("/ai/ingest", json={"object_ids": ["obj-1"]})

    assert response.status_code == 200
    assert response.json()["ingested"] == 1
    mock_session.add.assert_called_once()

@pytest.mark.asyncio
async def test_ingest_skips_already_embedded_object(client: AsyncClient):
    mock_obj = MagicMock()
    mock_obj.id = "obj-1"
    mock_obj.object_type = "Manufacturer"
    mock_obj.source_nmra = "RWANDA_FDA"
    mock_obj.source_id = "1"
    mock_obj.properties = {"name": "PharmaCo"}

    existing_embedding = MagicMock()

    mock_session = AsyncMock()
    mock_session.get = AsyncMock(return_value=mock_obj)
    mock_session.execute = AsyncMock(return_value=MagicMock(
        scalar_one_or_none=MagicMock(return_value=existing_embedding)
    ))
    mock_session.add = MagicMock()
    mock_session.commit = AsyncMock()

    with patch("retrieve.SessionLocal") as mock_db:
        mock_db.return_value.__aenter__ = AsyncMock(return_value=mock_session)
        mock_db.return_value.__aexit__ = AsyncMock(return_value=False)

        response = await client.post("/ai/ingest", json={"object_ids": ["obj-1"]})

    assert response.status_code == 200
    assert response.json()["ingested"] == 0
    mock_session.add.assert_not_called()
