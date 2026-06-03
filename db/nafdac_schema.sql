CREATE TABLE IF NOT EXISTS manufacturers (
    id                   SERIAL PRIMARY KEY,
    company_name         VARCHAR(255) NOT NULL,
    origin_country       VARCHAR(100) NOT NULL,
    reg_no               VARCHAR(100) NOT NULL UNIQUE,
    authorization_status VARCHAR(50)  NOT NULL DEFAULT 'authorized',
    created_at           TIMESTAMPTZ  DEFAULT NOW(),
    updated_at           TIMESTAMPTZ  DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS products (
    id                 SERIAL PRIMARY KEY,
    supplier_id        INTEGER REFERENCES manufacturers(id),
    product_name       VARCHAR(255) NOT NULL,
    inn_name           VARCHAR(255) NOT NULL,
    formulation        VARCHAR(100) NOT NULL,
    potency            VARCHAR(100) NOT NULL,
    market_auth_number VARCHAR(100) NOT NULL UNIQUE,
    created_at         TIMESTAMPTZ  DEFAULT NOW(),
    updated_at         TIMESTAMPTZ  DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS batches (
    id                 SERIAL PRIMARY KEY,
    product_id         INTEGER REFERENCES products(id),
    supplier_id        INTEGER REFERENCES manufacturers(id),
    lot_number         VARCHAR(100) NOT NULL,
    production_date    DATE NOT NULL,
    expiry_date        DATE NOT NULL,
    units_manufactured INTEGER,
    created_at         TIMESTAMPTZ  DEFAULT NOW(),
    updated_at         TIMESTAMPTZ  DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS product_recalls (
    id                  SERIAL PRIMARY KEY,
    lot_id              INTEGER REFERENCES batches(id),
    supplier_id         INTEGER REFERENCES manufacturers(id),
    alert_reference     VARCHAR(100) NOT NULL UNIQUE,
    recall_reason       TEXT NOT NULL,
    severity_grade      VARCHAR(20)  NOT NULL
                          CHECK (severity_grade IN ('Grade I','Grade II','Grade III')),
    laboratory_findings TEXT NOT NULL,
    recall_status       VARCHAR(20)  NOT NULL DEFAULT 'active'
                          CHECK (recall_status IN ('active','completed','closed')),
    issue_date          DATE NOT NULL,
    affected_regions    VARCHAR(255) NOT NULL DEFAULT 'National',
    created_at          TIMESTAMPTZ  DEFAULT NOW(),
    updated_at          TIMESTAMPTZ  DEFAULT NOW()
);
