import uuid
from datetime import datetime
from sqlalchemy import String, Boolean, Text, DateTime, ForeignKey, ARRAY
from sqlalchemy.dialects.postgresql import UUID, JSONB
from sqlalchemy.orm import Mapped, mapped_column, relationship
from pgvector.sqlalchemy import Vector
from database import Base


def new_uuid() -> str:
    return str(uuid.uuid4())


class PublishedObject(Base):
    __tablename__ = "published_objects"

    id:          Mapped[str]      = mapped_column(String(36), primary_key=True, default=new_uuid)
    source_nmra: Mapped[str]      = mapped_column(String(50))
    object_type: Mapped[str]      = mapped_column(String(100))
    source_id:   Mapped[str]      = mapped_column(String(255))
    properties:  Mapped[dict]     = mapped_column(JSONB)
    created_at:  Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow)
    updated_at:  Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow)

    links_from: Mapped[list["ObjectLink"]]    = relationship("ObjectLink", foreign_keys="ObjectLink.from_object_id", back_populates="from_object")
    links_to:   Mapped[list["ObjectLink"]]    = relationship("ObjectLink", foreign_keys="ObjectLink.to_object_id",   back_populates="to_object")
    markings:   Mapped[list["ObjectMarking"]] = relationship(back_populates="object")
    embeddings: Mapped[list["Embedding"]]     = relationship(back_populates="object")


class ObjectLink(Base):
    __tablename__ = "object_links"

    id:              Mapped[str]      = mapped_column(String(36), primary_key=True, default=new_uuid)
    from_object_id:  Mapped[str]      = mapped_column(ForeignKey("published_objects.id"))
    to_object_id:    Mapped[str]      = mapped_column(ForeignKey("published_objects.id"))
    link_type:       Mapped[str]      = mapped_column(String(100))
    created_by_nmra: Mapped[str]      = mapped_column(String(50))
    created_at:      Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow)

    from_object: Mapped["PublishedObject"] = relationship("PublishedObject", foreign_keys=[from_object_id], back_populates="links_from")
    to_object:   Mapped["PublishedObject"] = relationship("PublishedObject", foreign_keys=[to_object_id],   back_populates="links_to")


class ObjectMarking(Base):
    __tablename__ = "object_markings"

    id:                  Mapped[str]  = mapped_column(String(36), primary_key=True, default=new_uuid)
    object_id:           Mapped[str]  = mapped_column(ForeignKey("published_objects.id"))
    nmra_id:             Mapped[str]  = mapped_column(String(50))
    can_read:            Mapped[bool] = mapped_column(Boolean, default=True)
    can_write:           Mapped[bool] = mapped_column(Boolean, default=False)
    property_exclusions: Mapped[list] = mapped_column(ARRAY(Text), default=list)

    object: Mapped["PublishedObject"] = relationship(back_populates="markings")


class Embedding(Base):
    __tablename__ = "embeddings"

    id:         Mapped[str]   = mapped_column(String(36), primary_key=True, default=new_uuid)
    object_id:  Mapped[str]   = mapped_column(ForeignKey("published_objects.id"))
    chunk_text: Mapped[str]   = mapped_column(Text)
    embedding:  Mapped[list]  = mapped_column(Vector(768), nullable=True)
    metadata:   Mapped[dict]  = mapped_column(JSONB, default=dict)

    object: Mapped["PublishedObject"] = relationship(back_populates="embeddings")
