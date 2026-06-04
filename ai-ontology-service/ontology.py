from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from sqlalchemy import select
from database import SessionLocal
from models import PublishedObject, ObjectLink, ObjectMarking

router = APIRouter(prefix="/ontology", tags=["ontology"])


class PublishRequest(BaseModel):
    source_nmra: str
    object_type: str
    source_id:   str
    properties:  dict


class LinkRequest(BaseModel):
    from_object_id:  str
    to_object_id:    str
    link_type:       str
    created_by_nmra: str


@router.post("/publish")
async def publish_object(req: PublishRequest):
    async with SessionLocal() as session:
        stmt = select(PublishedObject).where(
            PublishedObject.source_nmra == req.source_nmra,
            PublishedObject.object_type == req.object_type,
            PublishedObject.source_id   == req.source_id,
        )
        existing = (await session.execute(stmt)).scalar_one_or_none()
        if existing:
            return {
                "id":          existing.id,
                "source_nmra": existing.source_nmra,
                "object_type": existing.object_type,
                "source_id":   existing.source_id,
                "properties":  existing.properties,
            }

        obj = PublishedObject(
            source_nmra=req.source_nmra,
            object_type=req.object_type,
            source_id=req.source_id,
            properties=req.properties,
        )
        session.add(obj)

        for nmra in ["RWANDA_FDA", "NAFDAC"]:
            session.add(ObjectMarking(
                object=obj,
                nmra_id=nmra,
                can_read=True,
                can_write=(nmra == req.source_nmra),
            ))

        await session.flush()
        await session.refresh(obj)
        await session.commit()

        return {
            "id":          obj.id,
            "source_nmra": obj.source_nmra,
            "object_type": obj.object_type,
            "source_id":   obj.source_id,
            "properties":  obj.properties,
        }


@router.post("/link")
async def create_link(req: LinkRequest):
    async with SessionLocal() as session:
        for oid in [req.from_object_id, req.to_object_id]:
            obj = await session.get(PublishedObject, oid)
            if not obj:
                raise HTTPException(status_code=404, detail=f"Object {oid} not found")

        link = ObjectLink(
            from_object_id=req.from_object_id,
            to_object_id=req.to_object_id,
            link_type=req.link_type,
            created_by_nmra=req.created_by_nmra,
        )
        session.add(link)
        await session.commit()
        await session.refresh(link)
        return {"id": link.id, "link_type": link.link_type}


@router.get("/objects/{object_id}")
async def get_object(object_id: str, nmra_id: str = "NAFDAC"):
    async with SessionLocal() as session:
        obj = await session.get(PublishedObject, object_id)
        if not obj:
            raise HTTPException(status_code=404, detail="Not found")

        marking = (await session.execute(
            select(ObjectMarking).where(
                ObjectMarking.object_id == object_id,
                ObjectMarking.nmra_id   == nmra_id,
                ObjectMarking.can_read  == True,
            )
        )).scalar_one_or_none()

        if not marking:
            raise HTTPException(status_code=403, detail="Access denied")

        props = {k: v for k, v in obj.properties.items()
                 if k not in (marking.property_exclusions or [])}
        return {"id": obj.id, "object_type": obj.object_type, "properties": props}
