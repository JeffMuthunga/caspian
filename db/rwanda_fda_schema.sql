CREATE TABLE IF NOT EXISTS manufacturers (
    id                    SERIAL PRIMARY KEY,
    name                  VARCHAR(255) NOT NULL,
    country               VARCHAR(100) NOT NULL,
    registration_number   VARCHAR(100) NOT NULL UNIQUE,
    license_status        VARCHAR(50)  NOT NULL DEFAULT 'active',
    internal_vendor_rating INTEGER,
    contract_terms        TEXT,
    created_at            TIMESTAMPTZ  DEFAULT NOW(),
    updated_at            TIMESTAMPTZ  DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS products (
    id                  SERIAL PRIMARY KEY,
    manufacturer_id     INTEGER REFERENCES manufacturers(id),
    name                VARCHAR(255) NOT NULL,
    generic_name        VARCHAR(255) NOT NULL,
    dosage_form         VARCHAR(100) NOT NULL,
    strength            VARCHAR(100) NOT NULL,
    registration_number VARCHAR(100) NOT NULL UNIQUE,
    internal_cost       NUMERIC(12,2),
    created_at          TIMESTAMPTZ  DEFAULT NOW(),
    updated_at          TIMESTAMPTZ  DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS batches (
    id               SERIAL PRIMARY KEY,
    product_id       INTEGER REFERENCES products(id),
    manufacturer_id  INTEGER REFERENCES manufacturers(id),
    batch_number     VARCHAR(100) NOT NULL,
    manufacture_date DATE NOT NULL,
    expiry_date      DATE NOT NULL,
    quantity_produced INTEGER,
    internal_lot_code VARCHAR(100),
    created_at       TIMESTAMPTZ  DEFAULT NOW(),
    updated_at       TIMESTAMPTZ  DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS product_recalls (
    id                           SERIAL PRIMARY KEY,
    batch_id                     INTEGER REFERENCES batches(id),
    manufacturer_id              INTEGER REFERENCES manufacturers(id),
    recall_number                VARCHAR(100) NOT NULL UNIQUE,
    reason                       TEXT NOT NULL,
    classification               VARCHAR(20)  NOT NULL
                                   CHECK (classification IN ('Class I','Class II','Class III')),
    qc_summary                   TEXT NOT NULL,
    status                       VARCHAR(20)  NOT NULL DEFAULT 'active'
                                   CHECK (status IN ('active','completed','closed')),
    date_issued                  DATE NOT NULL,
    scope                        VARCHAR(100) NOT NULL DEFAULT 'National',
    internal_investigation_notes TEXT,
    inspector_id                 INTEGER,
    created_at                   TIMESTAMPTZ  DEFAULT NOW(),
    updated_at                   TIMESTAMPTZ  DEFAULT NOW()
);
