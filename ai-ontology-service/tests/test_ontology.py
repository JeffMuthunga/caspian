import pytest
from unittest.mock import AsyncMock, MagicMock, patch
from httpx import AsyncClient

MANUFACTURER_PAYLOAD = {
    "source_nmra": "RWANDA_FDA",
    "object_type": "Manufacturer",
    "source_id":   "1",
    "properties": {
        "name": "PharmaCo Ltd", "country": "Kenya",
        "registration_number": "MFG-441", "license_status": "active",
    },
}

@pytest.mark.asyncio
async def test_publish_creates_object(client: AsyncClient):
    mock_obj = MagicMock()
    mock_obj.id = "new-uuid-123"
    mock_obj.source_nmra = "RWANDA_FDA"
    mock_obj.object_type = "Manufacturer"
    mock_obj.source_id = "1"
    mock_obj.properties = MANUFACTURER_PAYLOAD["properties"]

    execute_result = MagicMock()
    execute_result.scalar_one_or_none.return_value = None

    mock_session = AsyncMock()
    mock_session.execute = AsyncMock(return_value=execute_result)
    mock_session.flush = AsyncMock()
    mock_session.commit = AsyncMock()
    mock_session.refresh = AsyncMock(side_effect=lambda obj: setattr(obj, 'id', 'new-uuid-123'))
    mock_session.add = MagicMock()

    with patch("ontology.SessionLocal") as mock_session_class:
        mock_session_class.return_value.__aenter__ = AsyncMock(return_value=mock_session)
        mock_session_class.return_value.__aexit__ = AsyncMock(return_value=False)

        response = await client.post("/ontology/publish", json=MANUFACTURER_PAYLOAD)

    assert response.status_code == 200
    body = response.json()
    assert body["object_type"] == "Manufacturer"
    assert body["source_nmra"] == "RWANDA_FDA"

@pytest.mark.asyncio
async def test_publish_returns_existing_on_duplicate(client: AsyncClient):
    existing = MagicMock()
    existing.id = "existing-uuid"
    existing.source_nmra = "RWANDA_FDA"
    existing.object_type = "Manufacturer"
    existing.source_id = "1"
    existing.properties = MANUFACTURER_PAYLOAD["properties"]

    execute_result = MagicMock()
    execute_result.scalar_one_or_none.return_value = existing

    mock_session = AsyncMock()
    mock_session.execute = AsyncMock(return_value=execute_result)

    with patch("ontology.SessionLocal") as mock_session_class:
        mock_session_class.return_value.__aenter__ = AsyncMock(return_value=mock_session)
        mock_session_class.return_value.__aexit__ = AsyncMock(return_value=False)

        response = await client.post("/ontology/publish", json=MANUFACTURER_PAYLOAD)

    assert response.status_code == 200
    assert response.json()["id"] == "existing-uuid"
