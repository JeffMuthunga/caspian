CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE IF NOT EXISTS published_objects (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    source_nmra  VARCHAR(50)  NOT NULL,
    object_type  VARCHAR(100) NOT NULL,
    source_id    VARCHAR(255) NOT NULL,
    properties   JSONB        NOT NULL,
    created_at   TIMESTAMPTZ  DEFAULT NOW(),
    updated_at   TIMESTAMPTZ  DEFAULT NOW(),
    UNIQUE (source_nmra, object_type, source_id)
);

CREATE TABLE IF NOT EXISTS object_links (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    from_object_id  UUID REFERENCES published_objects(id),
    to_object_id    UUID REFERENCES published_objects(id),
    link_type       VARCHAR(100) NOT NULL,
    created_by_nmra VARCHAR(50)  NOT NULL,
    created_at      TIMESTAMPTZ  DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS object_markings (
    id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    object_id           UUID REFERENCES published_objects(id),
    nmra_id             VARCHAR(50) NOT NULL,
    can_read            BOOLEAN     DEFAULT TRUE,
    can_write           BOOLEAN     DEFAULT FALSE,
    property_exclusions TEXT[]      DEFAULT '{}'
);

CREATE TABLE IF NOT EXISTS embeddings (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    object_id  UUID REFERENCES published_objects(id),
    chunk_text TEXT NOT NULL,
    embedding  vector(768),
    metadata   JSONB DEFAULT '{}'
);

CREATE INDEX IF NOT EXISTS embeddings_ivfflat_idx
    ON embeddings USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);
