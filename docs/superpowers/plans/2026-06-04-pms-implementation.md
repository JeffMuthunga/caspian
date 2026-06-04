# PMS Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Scaffold Rwanda FDA Laravel backend, NAFDAC Laravel backend, and Python FastAPI AI Ontology Service implementing the Post-Market Surveillance module — ending with a NAFDAC analyst querying active recalls and receiving a cited answer surfacing Rwanda FDA's data.

**Architecture:** Two Laravel 11 backends each own a private PostgreSQL instance with PMS tables (manufacturers, products, batches, product_recalls). Each publishes governed projections to a shared Python FastAPI service storing embeddings in pgvector. The two schemas use different column names; each backend's `OntologyPublisher` service maps to canonical field names. The Python service embeds via Ollama `nomic-embed-text` and answers RAG queries via `llama3.1:8b`.

**Tech Stack:** Laravel 11, PHP 8.3, Python 3.12, FastAPI, PostgreSQL 16, pgvector, Ollama, Docker Compose

---

## File Structure

```
nmra-prototype/
├── docker-compose.yml
├── db/
│   ├── rwanda_fda_schema.sql
│   ├── nafdac_schema.sql
│   └── ontology_schema.sql
├── rwanda-fda/backend/
│   ├── Dockerfile
│   ├── app/Http/Controllers/
│   │   ├── ManufacturerController.php
│   │   ├── ProductController.php
│   │   ├── BatchController.php
│   │   └── ProductRecallController.php
│   ├── app/Models/
│   │   ├── Manufacturer.php  Product.php  Batch.php  ProductRecall.php
│   ├── app/Services/OntologyPublisher.php
│   ├── database/migrations/  (4 files)
│   ├── routes/api.php
│   └── tests/Feature/  (4 test files)
├── nafdac/backend/
│   └── (same structure, different column names in migrations + models)
└── ai-ontology-service/
    ├── Dockerfile
    ├── requirements.txt
    ├── main.py
    ├── database.py
    ├── models.py
    ├── ontology.py
    ├── retrieve.py
    ├── generate.py
    └── tests/  conftest.py  test_ontology.py  test_retrieve.py  test_generate.py
```

---

### Task 1: Project skeleton — directories, Docker, DB schemas

**Files:** `docker-compose.yml`, `db/*.sql`, `*/Dockerfile`, `ai-ontology-service/requirements.txt`

- [ ] **Step 1: Create directory structure**

```bash
mkdir -p rwanda-fda/backend nafdac/backend ai-ontology-service/tests db
```

- [ ] **Step 2: Write docker-compose.yml**

```yaml
services:
  rwanda-fda-db:
    image: postgres:16
    environment:
      POSTGRES_DB: rwanda_fda
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: secret
    ports:
      - "5433:5432"
    volumes:
      - rwanda_fda_data:/var/lib/postgresql/data
      - ./db/rwanda_fda_schema.sql:/docker-entrypoint-initdb.d/01_schema.sql
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U postgres"]
      interval: 5s
      retries: 5

  nafdac-db:
    image: postgres:16
    environment:
      POSTGRES_DB: nafdac
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: secret
    ports:
      - "5434:5432"
    volumes:
      - nafdac_data:/var/lib/postgresql/data
      - ./db/nafdac_schema.sql:/docker-entrypoint-initdb.d/01_schema.sql
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U postgres"]
      interval: 5s
      retries: 5

  ontology-db:
    image: pgvector/pgvector:pg16
    environment:
      POSTGRES_DB: ontology
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: secret
    ports:
      - "5435:5432"
    volumes:
      - ontology_data:/var/lib/postgresql/data
      - ./db/ontology_schema.sql:/docker-entrypoint-initdb.d/01_schema.sql
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U postgres"]
      interval: 5s
      retries: 5

  rwanda-fda-backend:
    build: ./rwanda-fda/backend
    ports:
      - "8001:8000"
    environment:
      APP_KEY: base64:PLACEHOLDER
      DB_CONNECTION: pgsql
      DB_HOST: rwanda-fda-db
      DB_PORT: 5432
      DB_DATABASE: rwanda_fda
      DB_USERNAME: postgres
      DB_PASSWORD: secret
      ONTOLOGY_SERVICE_URL: http://ai-ontology-service:8000
      NMRA_ID: RWANDA_FDA
    depends_on:
      rwanda-fda-db:
        condition: service_healthy

  nafdac-backend:
    build: ./nafdac/backend
    ports:
      - "8002:8000"
    environment:
      APP_KEY: base64:PLACEHOLDER
      DB_CONNECTION: pgsql
      DB_HOST: nafdac-db
      DB_PORT: 5432
      DB_DATABASE: nafdac
      DB_USERNAME: postgres
      DB_PASSWORD: secret
      ONTOLOGY_SERVICE_URL: http://ai-ontology-service:8000
      NMRA_ID: NAFDAC
    depends_on:
      nafdac-db:
        condition: service_healthy

  ai-ontology-service:
    build: ./ai-ontology-service
    ports:
      - "8000:8000"
    environment:
      DATABASE_URL: postgresql+asyncpg://postgres:secret@ontology-db:5432/ontology
      OLLAMA_HOST: http://host.docker.internal:11434
    extra_hosts:
      - "host.docker.internal:host-gateway"
    depends_on:
      ontology-db:
        condition: service_healthy

volumes:
  rwanda_fda_data:
  nafdac_data:
  ontology_data:
```

- [ ] **Step 3: Write db/rwanda_fda_schema.sql**

```sql
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
```

- [ ] **Step 4: Write db/nafdac_schema.sql**

```sql
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
```

- [ ] **Step 5: Write db/ontology_schema.sql**

```sql
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
```

- [ ] **Step 6: Write rwanda-fda/backend/Dockerfile**

```dockerfile
FROM php:8.3-cli
RUN apt-get update && apt-get install -y git curl zip unzip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
COPY . .
RUN composer install --no-dev --optimize-autoloader
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
```

- [ ] **Step 7: Copy identical Dockerfile to nafdac/backend/Dockerfile**

```bash
cp rwanda-fda/backend/Dockerfile nafdac/backend/Dockerfile
```

- [ ] **Step 8: Write ai-ontology-service/Dockerfile**

```dockerfile
FROM python:3.12-slim
RUN apt-get update && apt-get install -y libpq-dev gcc && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY . .
EXPOSE 8000
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000", "--reload"]
```

- [ ] **Step 9: Write ai-ontology-service/requirements.txt**

```
fastapi==0.115.5
uvicorn[standard]==0.32.1
sqlalchemy[asyncio]==2.0.36
asyncpg==0.30.0
pgvector==0.3.6
httpx==0.28.0
python-dotenv==1.0.1
pytest==8.3.4
pytest-asyncio==0.24.0
```

- [ ] **Step 10: Commit skeleton**

```bash
git init && git add . && git commit -m "chore: project skeleton — docker-compose, db schemas, Dockerfiles"
```

Expected: initial commit.

---

### Task 2: Scaffold Rwanda FDA Laravel project

**Files:** Full Laravel 11 project in `rwanda-fda/backend/`

- [ ] **Step 1: Scaffold via Docker (no local PHP required)**

```bash
docker run --rm \
  -v "$(pwd)/rwanda-fda/backend:/app" \
  -w /app \
  composer:latest \
  create-project laravel/laravel . --prefer-dist --no-interaction
```

Expected: ends with `Application key set successfully.`

- [ ] **Step 2: Enable API routes**

```bash
docker run --rm \
  -v "$(pwd)/rwanda-fda/backend:/app" \
  -w /app \
  php:8.3-cli \
  php artisan install:api --no-interaction
```

Expected: `routes/api.php` created, Sanctum installed.

- [ ] **Step 3: Configure phpunit.xml for in-memory SQLite tests**

Open `rwanda-fda/backend/phpunit.xml`. Inside `<php>`, add:

```xml
<env name="APP_ENV" value="testing"/>
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

- [ ] **Step 4: Add ontology service config to config/services.php**

Append inside the return array:

```php
'ontology' => [
    'url'     => env('ONTOLOGY_SERVICE_URL', 'http://localhost:8000'),
    'nmra_id' => env('NMRA_ID', 'RWANDA_FDA'),
],
```

- [ ] **Step 5: Remove default migrations**

```bash
rm rwanda-fda/backend/database/migrations/*.php
```

- [ ] **Step 6: Verify tests run on clean scaffold**

```bash
cd rwanda-fda/backend && ./vendor/bin/phpunit --no-coverage
```

Expected: `OK (2 tests, 2 assertions)` — only the default ExampleTest passes.

- [ ] **Step 7: Commit**

```bash
git add rwanda-fda/backend/ && git commit -m "chore(rwanda-fda): laravel 11 scaffold with api routes and sqlite test config"
```

---

### Task 3: Rwanda FDA — Manufacturers

**Files:**
- Create: `rwanda-fda/backend/database/migrations/2026_06_04_000001_create_manufacturers_table.php`
- Create: `rwanda-fda/backend/app/Models/Manufacturer.php`
- Create: `rwanda-fda/backend/app/Http/Controllers/ManufacturerController.php`
- Modify: `rwanda-fda/backend/routes/api.php`
- Create: `rwanda-fda/backend/tests/Feature/ManufacturerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// rwanda-fda/backend/tests/Feature/ManufacturerTest.php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManufacturerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_manufacturer(): void
    {
        $response = $this->postJson('/api/manufacturers', [
            'name'                => 'PharmaCo Ltd',
            'country'             => 'Kenya',
            'registration_number' => 'MFG-441',
            'license_status'      => 'active',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'PharmaCo Ltd')
                 ->assertJsonPath('registration_number', 'MFG-441');
    }

    public function test_can_list_manufacturers(): void
    {
        $this->postJson('/api/manufacturers', [
            'name' => 'PharmaCo Ltd', 'country' => 'Kenya',
            'registration_number' => 'MFG-441', 'license_status' => 'active',
        ]);

        $this->getJson('/api/manufacturers')
             ->assertStatus(200)
             ->assertJsonCount(1);
    }

    public function test_internal_fields_are_not_returned(): void
    {
        $response = $this->postJson('/api/manufacturers', [
            'name'                   => 'PharmaCo Ltd',
            'country'                => 'Kenya',
            'registration_number'    => 'MFG-441',
            'license_status'         => 'active',
            'internal_vendor_rating' => 3,
            'contract_terms'         => 'NET-30',
        ]);

        $response->assertStatus(201)
                 ->assertJsonMissingPath('internal_vendor_rating')
                 ->assertJsonMissingPath('contract_terms');
    }
}
```

- [ ] **Step 2: Run — confirm it fails**

```bash
cd rwanda-fda/backend && ./vendor/bin/phpunit tests/Feature/ManufacturerTest.php
```

Expected: FAIL — route not found.

- [ ] **Step 3: Write the migration**

```php
<?php
// rwanda-fda/backend/database/migrations/2026_06_04_000001_create_manufacturers_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country', 100);
            $table->string('registration_number', 100)->unique();
            $table->string('license_status', 50)->default('active');
            $table->integer('internal_vendor_rating')->nullable();
            $table->text('contract_terms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturers');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php
// rwanda-fda/backend/app/Models/Manufacturer.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{
    protected $fillable = [
        'name', 'country', 'registration_number', 'license_status',
        'internal_vendor_rating', 'contract_terms',
    ];

    protected $hidden = ['internal_vendor_rating', 'contract_terms'];

    public function products(): HasMany  { return $this->hasMany(Product::class); }
    public function batches(): HasMany   { return $this->hasMany(Batch::class); }
    public function productRecalls(): HasMany { return $this->hasMany(ProductRecall::class); }
}
```

- [ ] **Step 5: Write the controller**

```php
<?php
// rwanda-fda/backend/app/Http/Controllers/ManufacturerController.php
namespace App\Http\Controllers;

use App\Models\Manufacturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManufacturerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Manufacturer::all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                   => 'required|string|max:255',
            'country'                => 'required|string|max:100',
            'registration_number'    => 'required|string|max:100|unique:manufacturers',
            'license_status'         => 'sometimes|string|in:active,suspended,revoked',
            'internal_vendor_rating' => 'sometimes|integer|min:1|max:5',
            'contract_terms'         => 'sometimes|string',
        ]);

        return response()->json(Manufacturer::create($data), 201);
    }

    public function show(Manufacturer $manufacturer): JsonResponse
    {
        return response()->json($manufacturer);
    }
}
```

- [ ] **Step 6: Register routes**

```php
<?php
// rwanda-fda/backend/routes/api.php
use App\Http\Controllers\ManufacturerController;
use Illuminate\Support\Facades\Route;

Route::apiResource('manufacturers', ManufacturerController::class)->only(['index', 'store', 'show']);
```

- [ ] **Step 7: Run — confirm it passes**

```bash
cd rwanda-fda/backend && ./vendor/bin/phpunit tests/Feature/ManufacturerTest.php
```

Expected: 3 tests, 6 assertions — PASS.

- [ ] **Step 8: Commit**

```bash
git add rwanda-fda/backend/ && git commit -m "feat(rwanda-fda): manufacturers endpoint with hidden internal fields"
```

---

### Task 4: Rwanda FDA — Products

**Files:**
- Create: `rwanda-fda/backend/database/migrations/2026_06_04_000002_create_products_table.php`
- Create: `rwanda-fda/backend/app/Models/Product.php`
- Create: `rwanda-fda/backend/app/Http/Controllers/ProductController.php`
- Modify: `rwanda-fda/backend/routes/api.php`
- Create: `rwanda-fda/backend/tests/Feature/ProductTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// rwanda-fda/backend/tests/Feature/ProductTest.php
namespace Tests\Feature;

use App\Models\Manufacturer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function manufacturer(): Manufacturer
    {
        return Manufacturer::create([
            'name' => 'PharmaCo Ltd', 'country' => 'Kenya',
            'registration_number' => 'MFG-441', 'license_status' => 'active',
        ]);
    }

    public function test_can_create_product(): void
    {
        $mfg = $this->manufacturer();

        $this->postJson('/api/products', [
            'manufacturer_id'     => $mfg->id,
            'name'                => 'Amoxicillin 500mg',
            'generic_name'        => 'Amoxicillin',
            'dosage_form'         => 'Capsule',
            'strength'            => '500mg',
            'registration_number' => 'PRD-112',
        ])->assertStatus(201)
          ->assertJsonPath('name', 'Amoxicillin 500mg')
          ->assertJsonMissingPath('internal_cost');
    }

    public function test_can_list_products(): void
    {
        $mfg = $this->manufacturer();
        $this->postJson('/api/products', [
            'manufacturer_id' => $mfg->id, 'name' => 'Amoxicillin 500mg',
            'generic_name' => 'Amoxicillin', 'dosage_form' => 'Capsule',
            'strength' => '500mg', 'registration_number' => 'PRD-112',
        ]);

        $this->getJson('/api/products')->assertStatus(200)->assertJsonCount(1);
    }
}
```

- [ ] **Step 2: Run — confirm it fails**

```bash
cd rwanda-fda/backend && ./vendor/bin/phpunit tests/Feature/ProductTest.php
```

Expected: FAIL.

- [ ] **Step 3: Write the migration**

```php
<?php
// rwanda-fda/backend/database/migrations/2026_06_04_000002_create_products_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_id')->constrained();
            $table->string('name');
            $table->string('generic_name');
            $table->string('dosage_form', 100);
            $table->string('strength', 100);
            $table->string('registration_number', 100)->unique();
            $table->decimal('internal_cost', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('products'); }
};
```

- [ ] **Step 4: Write the model**

```php
<?php
// rwanda-fda/backend/app/Models/Product.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'manufacturer_id', 'name', 'generic_name',
        'dosage_form', 'strength', 'registration_number', 'internal_cost',
    ];

    protected $hidden = ['internal_cost'];

    public function manufacturer(): BelongsTo { return $this->belongsTo(Manufacturer::class); }
    public function batches(): HasMany        { return $this->hasMany(Batch::class); }
}
```

- [ ] **Step 5: Write the controller**

```php
<?php
// rwanda-fda/backend/app/Http/Controllers/ProductController.php
namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Product::all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'manufacturer_id'     => 'required|exists:manufacturers,id',
            'name'                => 'required|string|max:255',
            'generic_name'        => 'required|string|max:255',
            'dosage_form'         => 'required|string|max:100',
            'strength'            => 'required|string|max:100',
            'registration_number' => 'required|string|max:100|unique:products',
            'internal_cost'       => 'sometimes|numeric|min:0',
        ]);

        return response()->json(Product::create($data), 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($product);
    }
}
```

- [ ] **Step 6: Add to routes/api.php**

```php
use App\Http\Controllers\ProductController;
// add after manufacturers line:
Route::apiResource('products', ProductController::class)->only(['index', 'store', 'show']);
```

- [ ] **Step 7: Run — confirm it passes**

```bash
cd rwanda-fda/backend && ./vendor/bin/phpunit tests/Feature/ProductTest.php
```

Expected: 2 tests — PASS.

- [ ] **Step 8: Commit**

```bash
git add rwanda-fda/backend/ && git commit -m "feat(rwanda-fda): products endpoint"
```

---

### Task 5: Rwanda FDA — Batches

**Files:**
- Create: `rwanda-fda/backend/database/migrations/2026_06_04_000003_create_batches_table.php`
- Create: `rwanda-fda/backend/app/Models/Batch.php`
- Create: `rwanda-fda/backend/app/Http/Controllers/BatchController.php`
- Modify: `rwanda-fda/backend/routes/api.php`
- Create: `rwanda-fda/backend/tests/Feature/BatchTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// rwanda-fda/backend/tests/Feature/BatchTest.php
namespace Tests\Feature;

use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchTest extends TestCase
{
    use RefreshDatabase;

    private function product(): array
    {
        $mfg = Manufacturer::create([
            'name' => 'PharmaCo Ltd', 'country' => 'Kenya',
            'registration_number' => 'MFG-441', 'license_status' => 'active',
        ]);
        $product = Product::create([
            'manufacturer_id' => $mfg->id, 'name' => 'Amoxicillin 500mg',
            'generic_name' => 'Amoxicillin', 'dosage_form' => 'Capsule',
            'strength' => '500mg', 'registration_number' => 'PRD-112',
        ]);
        return ['manufacturer' => $mfg, 'product' => $product];
    }

    public function test_can_create_batch(): void
    {
        ['manufacturer' => $mfg, 'product' => $product] = $this->product();

        $this->postJson('/api/batches', [
            'product_id'      => $product->id,
            'manufacturer_id' => $mfg->id,
            'batch_number'    => 'LOT-4421',
            'manufacture_date'=> '2025-01-01',
            'expiry_date'     => '2027-01-01',
        ])->assertStatus(201)
          ->assertJsonPath('batch_number', 'LOT-4421')
          ->assertJsonMissingPath('quantity_produced')
          ->assertJsonMissingPath('internal_lot_code');
    }
}
```

- [ ] **Step 2: Run — confirm it fails**

```bash
cd rwanda-fda/backend && ./vendor/bin/phpunit tests/Feature/BatchTest.php
```

Expected: FAIL.

- [ ] **Step 3: Write the migration**

```php
<?php
// rwanda-fda/backend/database/migrations/2026_06_04_000003_create_batches_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('manufacturer_id')->constrained();
            $table->string('batch_number', 100);
            $table->date('manufacture_date');
            $table->date('expiry_date');
            $table->integer('quantity_produced')->nullable();
            $table->string('internal_lot_code', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('batches'); }
};
```

- [ ] **Step 4: Write the model**

```php
<?php
// rwanda-fda/backend/app/Models/Batch.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $fillable = [
        'product_id', 'manufacturer_id', 'batch_number',
        'manufacture_date', 'expiry_date', 'quantity_produced', 'internal_lot_code',
    ];

    protected $hidden = ['quantity_produced', 'internal_lot_code'];

    public function product(): BelongsTo      { return $this->belongsTo(Product::class); }
    public function manufacturer(): BelongsTo { return $this->belongsTo(Manufacturer::class); }
    public function productRecalls(): HasMany  { return $this->hasMany(ProductRecall::class, 'batch_id'); }
}
```

- [ ] **Step 5: Write the controller**

```php
<?php
// rwanda-fda/backend/app/Http/Controllers/BatchController.php
namespace App\Http\Controllers;

use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Batch::all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'       => 'required|exists:products,id',
            'manufacturer_id'  => 'required|exists:manufacturers,id',
            'batch_number'     => 'required|string|max:100',
            'manufacture_date' => 'required|date',
            'expiry_date'      => 'required|date|after:manufacture_date',
            'quantity_produced'=> 'sometimes|integer|min:1',
            'internal_lot_code'=> 'sometimes|string|max:100',
        ]);

        return response()->json(Batch::create($data), 201);
    }

    public function show(Batch $batch): JsonResponse
    {
        return response()->json($batch);
    }
}
```

- [ ] **Step 6: Add to routes/api.php**

```php
use App\Http\Controllers\BatchController;
Route::apiResource('batches', BatchController::class)->only(['index', 'store', 'show']);
```

- [ ] **Step 7: Run — confirm it passes**

```bash
cd rwanda-fda/backend && ./vendor/bin/phpunit tests/Feature/BatchTest.php
```

Expected: 1 test — PASS.

- [ ] **Step 8: Commit**

```bash
git add rwanda-fda/backend/ && git commit -m "feat(rwanda-fda): batches endpoint"
```

---

### Task 6: Rwanda FDA — Product Recalls

**Files:**
- Create: `rwanda-fda/backend/database/migrations/2026_06_04_000004_create_product_recalls_table.php`
- Create: `rwanda-fda/backend/app/Models/ProductRecall.php`
- Create: `rwanda-fda/backend/app/Http/Controllers/ProductRecallController.php`
- Modify: `rwanda-fda/backend/routes/api.php`
- Create: `rwanda-fda/backend/tests/Feature/ProductRecallTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// rwanda-fda/backend/tests/Feature/ProductRecallTest.php
namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Manufacturer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRecallTest extends TestCase
{
    use RefreshDatabase;

    private function seedBatchAndManufacturer(): array
    {
        $mfg = Manufacturer::create([
            'name' => 'PharmaCo Ltd', 'country' => 'Kenya',
            'registration_number' => 'MFG-441', 'license_status' => 'active',
        ]);
        $product = Product::create([
            'manufacturer_id' => $mfg->id, 'name' => 'Amoxicillin 500mg',
            'generic_name' => 'Amoxicillin', 'dosage_form' => 'Capsule',
            'strength' => '500mg', 'registration_number' => 'PRD-112',
        ]);
        $batch = Batch::create([
            'product_id' => $product->id, 'manufacturer_id' => $mfg->id,
            'batch_number' => 'LOT-4421',
            'manufacture_date' => '2025-01-01', 'expiry_date' => '2027-01-01',
        ]);
        return ['mfg' => $mfg, 'batch' => $batch];
    }

    public function test_can_create_product_recall(): void
    {
        ['mfg' => $mfg, 'batch' => $batch] = $this->seedBatchAndManufacturer();

        $this->postJson('/api/product-recalls', [
            'batch_id'        => $batch->id,
            'manufacturer_id' => $mfg->id,
            'recall_number'   => 'RCL-001',
            'reason'          => 'Substandard API content',
            'classification'  => 'Class II',
            'qc_summary'      => 'Active ingredient at 72% (spec: 95-105%)',
            'status'          => 'active',
            'date_issued'     => '2026-01-15',
            'scope'           => 'National',
        ])->assertStatus(201)
          ->assertJsonPath('recall_number', 'RCL-001')
          ->assertJsonPath('classification', 'Class II')
          ->assertJsonMissingPath('internal_investigation_notes')
          ->assertJsonMissingPath('inspector_id');
    }

    public function test_only_active_recalls_are_listed_by_default(): void
    {
        ['mfg' => $mfg, 'batch' => $batch] = $this->seedBatchAndManufacturer();

        $this->postJson('/api/product-recalls', [
            'batch_id' => $batch->id, 'manufacturer_id' => $mfg->id,
            'recall_number' => 'RCL-001', 'reason' => 'Substandard',
            'classification' => 'Class II',
            'qc_summary' => 'API at 72%', 'status' => 'active',
            'date_issued' => '2026-01-15', 'scope' => 'National',
        ]);

        $this->getJson('/api/product-recalls?status=active')
             ->assertStatus(200)
             ->assertJsonCount(1);
    }
}
```

- [ ] **Step 2: Run — confirm it fails**

```bash
cd rwanda-fda/backend && ./vendor/bin/phpunit tests/Feature/ProductRecallTest.php
```

Expected: FAIL.

- [ ] **Step 3: Write the migration**

```php
<?php
// rwanda-fda/backend/database/migrations/2026_06_04_000004_create_product_recalls_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_recalls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches');
            $table->foreignId('manufacturer_id')->constrained('manufacturers');
            $table->string('recall_number', 100)->unique();
            $table->text('reason');
            $table->string('classification', 20);
            $table->text('qc_summary');
            $table->string('status', 20)->default('active');
            $table->date('date_issued');
            $table->string('scope', 100)->default('National');
            $table->text('internal_investigation_notes')->nullable();
            $table->integer('inspector_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('product_recalls'); }
};
```

- [ ] **Step 4: Write the model**

```php
<?php
// rwanda-fda/backend/app/Models/ProductRecall.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRecall extends Model
{
    protected $fillable = [
        'batch_id', 'manufacturer_id', 'recall_number', 'reason',
        'classification', 'qc_summary', 'status', 'date_issued', 'scope',
        'internal_investigation_notes', 'inspector_id',
    ];

    protected $hidden = ['internal_investigation_notes', 'inspector_id'];

    public function batch(): BelongsTo        { return $this->belongsTo(Batch::class); }
    public function manufacturer(): BelongsTo { return $this->belongsTo(Manufacturer::class); }
}
```

- [ ] **Step 5: Write the controller**

```php
<?php
// rwanda-fda/backend/app/Http/Controllers/ProductRecallController.php
namespace App\Http\Controllers;

use App\Models\ProductRecall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductRecallController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductRecall::query();
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'batch_id'                     => 'required|exists:batches,id',
            'manufacturer_id'              => 'required|exists:manufacturers,id',
            'recall_number'                => 'required|string|max:100|unique:product_recalls',
            'reason'                       => 'required|string',
            'classification'               => 'required|string|in:Class I,Class II,Class III',
            'qc_summary'                   => 'required|string',
            'status'                       => 'sometimes|string|in:active,completed,closed',
            'date_issued'                  => 'required|date',
            'scope'                        => 'sometimes|string|max:100',
            'internal_investigation_notes' => 'sometimes|string',
            'inspector_id'                 => 'sometimes|integer',
        ]);

        return response()->json(ProductRecall::create($data), 201);
    }

    public function show(ProductRecall $productRecall): JsonResponse
    {
        return response()->json($productRecall);
    }
}
```

- [ ] **Step 6: Add to routes/api.php**

```php
use App\Http\Controllers\ProductRecallController;
Route::apiResource('product-recalls', ProductRecallController::class)->only(['index', 'store', 'show']);
```

- [ ] **Step 7: Run — confirm it passes**

```bash
cd rwanda-fda/backend && ./vendor/bin/phpunit tests/Feature/ProductRecallTest.php
```

Expected: 2 tests — PASS.

- [ ] **Step 8: Run full test suite**

```bash
cd rwanda-fda/backend && ./vendor/bin/phpunit --no-coverage
```

Expected: all tests PASS.

- [ ] **Step 9: Commit**

```bash
git add rwanda-fda/backend/ && git commit -m "feat(rwanda-fda): product_recalls endpoint — Rwanda FDA backend complete"
```

---

### Task 7: Rwanda FDA — OntologyPublisher service

**Files:**
- Create: `rwanda-fda/backend/app/Services/OntologyPublisher.php`
- Create: `rwanda-fda/backend/tests/Feature/OntologyPublisherTest.php`
- Modify: `rwanda-fda/backend/app/Http/Controllers/ProductRecallController.php`

This service publishes all four objects and wires up the cross-org links after a recall is created.

- [ ] **Step 1: Write the failing test**

```php
<?php
// rwanda-fda/backend/tests/Feature/OntologyPublisherTest.php
namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Manufacturer;
use App\Models\Product;
use App\Models\ProductRecall;
use App\Services\OntologyPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OntologyPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_recall_sends_four_objects_and_four_links(): void
    {
        Http::fake([
            '*/ontology/publish' => Http::response(['id' => 'uuid-123', 'object_type' => 'Manufacturer'], 200),
            '*/ontology/link'    => Http::response(['id' => 'link-uuid'], 200),
            '*/ai/ingest'        => Http::response(['status' => 'ok'], 200),
        ]);

        $mfg = Manufacturer::create([
            'name' => 'PharmaCo Ltd', 'country' => 'Kenya',
            'registration_number' => 'MFG-441', 'license_status' => 'active',
        ]);
        $product = Product::create([
            'manufacturer_id' => $mfg->id, 'name' => 'Amoxicillin 500mg',
            'generic_name' => 'Amoxicillin', 'dosage_form' => 'Capsule',
            'strength' => '500mg', 'registration_number' => 'PRD-112',
        ]);
        $batch = Batch::create([
            'product_id' => $product->id, 'manufacturer_id' => $mfg->id,
            'batch_number' => 'LOT-4421',
            'manufacture_date' => '2025-01-01', 'expiry_date' => '2027-01-01',
        ]);
        $recall = ProductRecall::create([
            'batch_id' => $batch->id, 'manufacturer_id' => $mfg->id,
            'recall_number' => 'RCL-001', 'reason' => 'Substandard API content',
            'classification' => 'Class II', 'qc_summary' => 'API at 72%',
            'status' => 'active', 'date_issued' => '2026-01-15', 'scope' => 'National',
        ]);

        $publisher = new OntologyPublisher();
        $publisher->publishRecallGraph($recall->load(['batch.product', 'manufacturer']));

        Http::assertSentCount(8); // 4 publishes + 4 links
    }
}
```

- [ ] **Step 2: Run — confirm it fails**

```bash
cd rwanda-fda/backend && ./vendor/bin/phpunit tests/Feature/OntologyPublisherTest.php
```

Expected: FAIL — class not found.

- [ ] **Step 3: Write OntologyPublisher**

```php
<?php
// rwanda-fda/backend/app/Services/OntologyPublisher.php
namespace App\Services;

use App\Models\ProductRecall;
use Illuminate\Support\Facades\Http;

class OntologyPublisher
{
    private string $baseUrl;
    private string $nmraId;

    public function __construct()
    {
        $this->baseUrl = config('services.ontology.url');
        $this->nmraId  = config('services.ontology.nmra_id');
    }

    public function publishRecallGraph(ProductRecall $recall): void
    {
        $batch   = $recall->batch;
        $product = $batch->product;
        $mfg     = $recall->manufacturer;

        $mfgObj  = $this->publish('Manufacturer', (string) $mfg->id, [
            'name' => $mfg->name, 'country' => $mfg->country,
            'registration_number' => $mfg->registration_number,
            'license_status' => $mfg->license_status,
        ]);
        $prodObj = $this->publish('Product', (string) $product->id, [
            'name' => $product->name, 'generic_name' => $product->generic_name,
            'dosage_form' => $product->dosage_form, 'strength' => $product->strength,
            'registration_number' => $product->registration_number,
        ]);
        $batchObj = $this->publish('Batch', (string) $batch->id, [
            'batch_number'    => $batch->batch_number,
            'manufacture_date'=> $batch->manufacture_date,
            'expiry_date'     => $batch->expiry_date,
        ]);
        $recallObj = $this->publish('ProductRecall', (string) $recall->id, [
            'recall_number'  => $recall->recall_number,
            'reason'         => $recall->reason,
            'classification' => $recall->classification,
            'qc_summary'     => $recall->qc_summary,
            'status'         => $recall->status,
            'date_issued'    => $recall->date_issued,
            'scope'          => $recall->scope,
        ]);

        $this->link($mfgObj['id'],   $prodObj['id'],    'manufactures');
        $this->link($batchObj['id'], $prodObj['id'],    'batch_of');
        $this->link($recallObj['id'],$batchObj['id'],   'affects');
        $this->link($recallObj['id'],$mfgObj['id'],     'issued_against');

        $this->ingest([$mfgObj['id'], $prodObj['id'], $batchObj['id'], $recallObj['id']]);
    }

    private function publish(string $type, string $sourceId, array $properties): array
    {
        return Http::post("{$this->baseUrl}/ontology/publish", [
            'source_nmra' => $this->nmraId,
            'object_type' => $type,
            'source_id'   => $sourceId,
            'properties'  => $properties,
        ])->throw()->json();
    }

    private function link(string $fromId, string $toId, string $linkType): void
    {
        Http::post("{$this->baseUrl}/ontology/link", [
            'from_object_id'  => $fromId,
            'to_object_id'    => $toId,
            'link_type'       => $linkType,
            'created_by_nmra' => $this->nmraId,
        ])->throw();
    }

    private function ingest(array $objectIds): void
    {
        Http::post("{$this->baseUrl}/ai/ingest", ['object_ids' => $objectIds])->throw();
    }
}
```

- [ ] **Step 4: Wire publisher into ProductRecallController::store**

In `ProductRecallController.php`, update the `store` method:

```php
use App\Services\OntologyPublisher;

public function store(Request $request): JsonResponse
{
    $data = $request->validate([
        'batch_id'                     => 'required|exists:batches,id',
        'manufacturer_id'              => 'required|exists:manufacturers,id',
        'recall_number'                => 'required|string|max:100|unique:product_recalls',
        'reason'                       => 'required|string',
        'classification'               => 'required|string|in:Class I,Class II,Class III',
        'qc_summary'                   => 'required|string',
        'status'                       => 'sometimes|string|in:active,completed,closed',
        'date_issued'                  => 'required|date',
        'scope'                        => 'sometimes|string|max:100',
        'internal_investigation_notes' => 'sometimes|string',
        'inspector_id'                 => 'sometimes|integer',
    ]);

    $recall = ProductRecall::create($data);
    $recall->load(['batch.product', 'manufacturer']);

    (new OntologyPublisher())->publishRecallGraph($recall);

    return response()->json($recall, 201);
}
```

- [ ] **Step 5: Run — confirm it passes**

```bash
cd rwanda-fda/backend && ./vendor/bin/phpunit tests/Feature/OntologyPublisherTest.php
```

Expected: 1 test — PASS.

- [ ] **Step 6: Run full suite**

```bash
cd rwanda-fda/backend && ./vendor/bin/phpunit --no-coverage
```

Expected: all PASS.

- [ ] **Step 7: Commit**

```bash
git add rwanda-fda/backend/ && git commit -m "feat(rwanda-fda): OntologyPublisher — publishes recall graph and links to AI service"
```

---

### Task 8: Scaffold NAFDAC Laravel backend

**Files:** Full Laravel 11 project in `nafdac/backend/`

- [ ] **Step 1: Scaffold via Docker**

```bash
docker run --rm \
  -v "$(pwd)/nafdac/backend:/app" \
  -w /app \
  composer:latest \
  create-project laravel/laravel . --prefer-dist --no-interaction
```

- [ ] **Step 2: Enable API routes**

```bash
docker run --rm \
  -v "$(pwd)/nafdac/backend:/app" \
  -w /app \
  php:8.3-cli \
  php artisan install:api --no-interaction
```

- [ ] **Step 3: Configure phpunit.xml (identical to Rwanda FDA)**

Inside `<php>`:

```xml
<env name="APP_ENV" value="testing"/>
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

- [ ] **Step 4: Add ontology config to config/services.php**

```php
'ontology' => [
    'url'     => env('ONTOLOGY_SERVICE_URL', 'http://localhost:8000'),
    'nmra_id' => env('NMRA_ID', 'NAFDAC'),
],
```

- [ ] **Step 5: Remove default migrations**

```bash
rm nafdac/backend/database/migrations/*.php
```

- [ ] **Step 6: Commit**

```bash
git add nafdac/backend/ && git commit -m "chore(nafdac): laravel 11 scaffold"
```

---

### Task 9: NAFDAC — Manufacturers

**Files:**
- Create: `nafdac/backend/database/migrations/2026_06_04_000001_create_manufacturers_table.php`
- Create: `nafdac/backend/app/Models/Manufacturer.php`
- Create: `nafdac/backend/app/Http/Controllers/ManufacturerController.php`
- Modify: `nafdac/backend/routes/api.php`
- Create: `nafdac/backend/tests/Feature/ManufacturerTest.php`

Note: NAFDAC uses `company_name`, `origin_country`, `reg_no`, `authorization_status` instead of Rwanda FDA's `name`, `country`, `registration_number`, `license_status`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// nafdac/backend/tests/Feature/ManufacturerTest.php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManufacturerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_manufacturer(): void
    {
        $this->postJson('/api/manufacturers', [
            'company_name'        => 'PharmaCo Ltd',
            'origin_country'      => 'Nigeria',
            'reg_no'              => 'NAFDAC-MFG-001',
            'authorization_status'=> 'authorized',
        ])->assertStatus(201)
          ->assertJsonPath('company_name', 'PharmaCo Ltd')
          ->assertJsonPath('reg_no', 'NAFDAC-MFG-001');
    }

    public function test_can_list_manufacturers(): void
    {
        $this->postJson('/api/manufacturers', [
            'company_name' => 'PharmaCo Ltd', 'origin_country' => 'Nigeria',
            'reg_no' => 'NAFDAC-MFG-001', 'authorization_status' => 'authorized',
        ]);

        $this->getJson('/api/manufacturers')->assertStatus(200)->assertJsonCount(1);
    }
}
```

- [ ] **Step 2: Run — confirm it fails**

```bash
cd nafdac/backend && ./vendor/bin/phpunit tests/Feature/ManufacturerTest.php
```

Expected: FAIL.

- [ ] **Step 3: Write the migration**

```php
<?php
// nafdac/backend/database/migrations/2026_06_04_000001_create_manufacturers_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('origin_country', 100);
            $table->string('reg_no', 100)->unique();
            $table->string('authorization_status', 50)->default('authorized');
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('manufacturers'); }
};
```

- [ ] **Step 4: Write the model**

```php
<?php
// nafdac/backend/app/Models/Manufacturer.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{
    protected $fillable = ['company_name', 'origin_country', 'reg_no', 'authorization_status'];

    public function products(): HasMany       { return $this->hasMany(Product::class, 'supplier_id'); }
    public function batches(): HasMany        { return $this->hasMany(Batch::class, 'supplier_id'); }
    public function productRecalls(): HasMany { return $this->hasMany(ProductRecall::class, 'supplier_id'); }
}
```

- [ ] **Step 5: Write the controller**

```php
<?php
// nafdac/backend/app/Http/Controllers/ManufacturerController.php
namespace App\Http\Controllers;

use App\Models\Manufacturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManufacturerController extends Controller
{
    public function index(): JsonResponse { return response()->json(Manufacturer::all()); }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name'         => 'required|string|max:255',
            'origin_country'       => 'required|string|max:100',
            'reg_no'               => 'required|string|max:100|unique:manufacturers',
            'authorization_status' => 'sometimes|string|in:authorized,suspended,revoked',
        ]);

        return response()->json(Manufacturer::create($data), 201);
    }

    public function show(Manufacturer $manufacturer): JsonResponse
    {
        return response()->json($manufacturer);
    }
}
```

- [ ] **Step 6: Register routes**

```php
<?php
// nafdac/backend/routes/api.php
use App\Http\Controllers\ManufacturerController;
use Illuminate\Support\Facades\Route;

Route::apiResource('manufacturers', ManufacturerController::class)->only(['index', 'store', 'show']);
```

- [ ] **Step 7: Run — confirm it passes**

```bash
cd nafdac/backend && ./vendor/bin/phpunit tests/Feature/ManufacturerTest.php
```

Expected: 2 tests — PASS.

- [ ] **Step 8: Commit**

```bash
git add nafdac/backend/ && git commit -m "feat(nafdac): manufacturers endpoint (company_name, reg_no columns)"
```

---

### Task 10: NAFDAC — Products, Batches, Product Recalls

**Files:** Migrations, models, controllers, tests for products, batches, product_recalls in `nafdac/backend/`

- [ ] **Step 1: Write nafdac products migration**

```php
<?php
// nafdac/backend/database/migrations/2026_06_04_000002_create_products_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('manufacturers');
            $table->string('product_name');
            $table->string('inn_name');
            $table->string('formulation', 100);
            $table->string('potency', 100);
            $table->string('market_auth_number', 100)->unique();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('products'); }
};
```

- [ ] **Step 2: Write nafdac products model**

```php
<?php
// nafdac/backend/app/Models/Product.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['supplier_id', 'product_name', 'inn_name', 'formulation', 'potency', 'market_auth_number'];

    public function manufacturer(): BelongsTo { return $this->belongsTo(Manufacturer::class, 'supplier_id'); }
    public function batches(): HasMany        { return $this->hasMany(Batch::class); }
}
```

- [ ] **Step 3: Write nafdac products controller**

```php
<?php
// nafdac/backend/app/Http/Controllers/ProductController.php
namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(): JsonResponse { return response()->json(Product::all()); }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'        => 'required|exists:manufacturers,id',
            'product_name'       => 'required|string|max:255',
            'inn_name'           => 'required|string|max:255',
            'formulation'        => 'required|string|max:100',
            'potency'            => 'required|string|max:100',
            'market_auth_number' => 'required|string|max:100|unique:products',
        ]);

        return response()->json(Product::create($data), 201);
    }

    public function show(Product $product): JsonResponse { return response()->json($product); }
}
```

- [ ] **Step 4: Write nafdac batches migration**

```php
<?php
// nafdac/backend/database/migrations/2026_06_04_000003_create_batches_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('supplier_id')->constrained('manufacturers');
            $table->string('lot_number', 100);
            $table->date('production_date');
            $table->date('expiry_date');
            $table->integer('units_manufactured')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('batches'); }
};
```

- [ ] **Step 5: Write nafdac batches model**

```php
<?php
// nafdac/backend/app/Models/Batch.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $fillable = [
        'product_id', 'supplier_id', 'lot_number',
        'production_date', 'expiry_date', 'units_manufactured',
    ];

    protected $hidden = ['units_manufactured'];

    public function product(): BelongsTo      { return $this->belongsTo(Product::class); }
    public function manufacturer(): BelongsTo { return $this->belongsTo(Manufacturer::class, 'supplier_id'); }
    public function productRecalls(): HasMany  { return $this->hasMany(ProductRecall::class, 'lot_id'); }
}
```

- [ ] **Step 6: Write nafdac batches controller**

```php
<?php
// nafdac/backend/app/Http/Controllers/BatchController.php
namespace App\Http\Controllers;

use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function index(): JsonResponse { return response()->json(Batch::all()); }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'         => 'required|exists:products,id',
            'supplier_id'        => 'required|exists:manufacturers,id',
            'lot_number'         => 'required|string|max:100',
            'production_date'    => 'required|date',
            'expiry_date'        => 'required|date|after:production_date',
            'units_manufactured' => 'sometimes|integer|min:1',
        ]);

        return response()->json(Batch::create($data), 201);
    }

    public function show(Batch $batch): JsonResponse { return response()->json($batch); }
}
```

- [ ] **Step 7: Write nafdac product_recalls migration**

```php
<?php
// nafdac/backend/database/migrations/2026_06_04_000004_create_product_recalls_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_recalls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained('batches');
            $table->foreignId('supplier_id')->constrained('manufacturers');
            $table->string('alert_reference', 100)->unique();
            $table->text('recall_reason');
            $table->string('severity_grade', 20);
            $table->text('laboratory_findings');
            $table->string('recall_status', 20)->default('active');
            $table->date('issue_date');
            $table->string('affected_regions', 255)->default('National');
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('product_recalls'); }
};
```

- [ ] **Step 8: Write nafdac product_recalls model**

```php
<?php
// nafdac/backend/app/Models/ProductRecall.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRecall extends Model
{
    protected $fillable = [
        'lot_id', 'supplier_id', 'alert_reference', 'recall_reason',
        'severity_grade', 'laboratory_findings', 'recall_status',
        'issue_date', 'affected_regions',
    ];

    public function batch(): BelongsTo        { return $this->belongsTo(Batch::class, 'lot_id'); }
    public function manufacturer(): BelongsTo { return $this->belongsTo(Manufacturer::class, 'supplier_id'); }
}
```

- [ ] **Step 9: Write nafdac product_recalls controller**

```php
<?php
// nafdac/backend/app/Http/Controllers/ProductRecallController.php
namespace App\Http\Controllers;

use App\Models\ProductRecall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductRecallController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductRecall::query();
        if ($request->has('status')) {
            $query->where('recall_status', $request->input('status'));
        }
        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lot_id'              => 'required|exists:batches,id',
            'supplier_id'         => 'required|exists:manufacturers,id',
            'alert_reference'     => 'required|string|max:100|unique:product_recalls',
            'recall_reason'       => 'required|string',
            'severity_grade'      => 'required|string|in:Grade I,Grade II,Grade III',
            'laboratory_findings' => 'required|string',
            'recall_status'       => 'sometimes|string|in:active,completed,closed',
            'issue_date'          => 'required|date',
            'affected_regions'    => 'sometimes|string|max:255',
        ]);

        return response()->json(ProductRecall::create($data), 201);
    }

    public function show(ProductRecall $productRecall): JsonResponse
    {
        return response()->json($productRecall);
    }
}
```

- [ ] **Step 10: Register all NAFDAC routes**

```php
<?php
// nafdac/backend/routes/api.php
use App\Http\Controllers\ManufacturerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\ProductRecallController;
use Illuminate\Support\Facades\Route;

Route::apiResource('manufacturers', ManufacturerController::class)->only(['index', 'store', 'show']);
Route::apiResource('products',      ProductController::class)->only(['index', 'store', 'show']);
Route::apiResource('batches',       BatchController::class)->only(['index', 'store', 'show']);
Route::apiResource('product-recalls', ProductRecallController::class)->only(['index', 'store', 'show']);
```

- [ ] **Step 11: Write nafdac OntologyPublisher**

```php
<?php
// nafdac/backend/app/Services/OntologyPublisher.php
namespace App\Services;

use App\Models\ProductRecall;
use Illuminate\Support\Facades\Http;

class OntologyPublisher
{
    private string $baseUrl;
    private string $nmraId;

    public function __construct()
    {
        $this->baseUrl = config('services.ontology.url');
        $this->nmraId  = config('services.ontology.nmra_id');
    }

    public function publishRecallGraph(ProductRecall $recall): void
    {
        $batch   = $recall->batch;
        $product = $batch->product;
        $mfg     = $recall->manufacturer;

        // Map NAFDAC column names → canonical ontology field names
        $mfgObj = $this->publish('Manufacturer', (string) $mfg->id, [
            'name'                => $mfg->company_name,
            'country'             => $mfg->origin_country,
            'registration_number' => $mfg->reg_no,
            'license_status'      => $mfg->authorization_status,
        ]);
        $prodObj = $this->publish('Product', (string) $product->id, [
            'name'                => $product->product_name,
            'generic_name'        => $product->inn_name,
            'dosage_form'         => $product->formulation,
            'strength'            => $product->potency,
            'registration_number' => $product->market_auth_number,
        ]);
        $batchObj = $this->publish('Batch', (string) $batch->id, [
            'batch_number'    => $batch->lot_number,
            'manufacture_date'=> $batch->production_date,
            'expiry_date'     => $batch->expiry_date,
        ]);
        $recallObj = $this->publish('ProductRecall', (string) $recall->id, [
            'recall_number'  => $recall->alert_reference,
            'reason'         => $recall->recall_reason,
            'classification' => $recall->severity_grade,
            'qc_summary'     => $recall->laboratory_findings,
            'status'         => $recall->recall_status,
            'date_issued'    => $recall->issue_date,
            'scope'          => $recall->affected_regions,
        ]);

        $this->link($mfgObj['id'],    $prodObj['id'],   'manufactures');
        $this->link($batchObj['id'],  $prodObj['id'],   'batch_of');
        $this->link($recallObj['id'], $batchObj['id'],  'affects');
        $this->link($recallObj['id'], $mfgObj['id'],    'issued_against');

        $this->ingest([$mfgObj['id'], $prodObj['id'], $batchObj['id'], $recallObj['id']]);
    }

    private function publish(string $type, string $sourceId, array $properties): array
    {
        return Http::post("{$this->baseUrl}/ontology/publish", [
            'source_nmra' => $this->nmraId,
            'object_type' => $type,
            'source_id'   => $sourceId,
            'properties'  => $properties,
        ])->throw()->json();
    }

    private function link(string $fromId, string $toId, string $linkType): void
    {
        Http::post("{$this->baseUrl}/ontology/link", [
            'from_object_id'  => $fromId,
            'to_object_id'    => $toId,
            'link_type'       => $linkType,
            'created_by_nmra' => $this->nmraId,
        ])->throw();
    }

    private function ingest(array $objectIds): void
    {
        Http::post("{$this->baseUrl}/ai/ingest", ['object_ids' => $objectIds])->throw();
    }
}
```

- [ ] **Step 12: Wire publisher into NAFDAC ProductRecallController::store**

```php
use App\Services\OntologyPublisher;

public function store(Request $request): JsonResponse
{
    $data = $request->validate([
        'lot_id'              => 'required|exists:batches,id',
        'supplier_id'         => 'required|exists:manufacturers,id',
        'alert_reference'     => 'required|string|max:100|unique:product_recalls',
        'recall_reason'       => 'required|string',
        'severity_grade'      => 'required|string|in:Grade I,Grade II,Grade III',
        'laboratory_findings' => 'required|string',
        'recall_status'       => 'sometimes|string|in:active,completed,closed',
        'issue_date'          => 'required|date',
        'affected_regions'    => 'sometimes|string|max:255',
    ]);

    $recall = ProductRecall::create($data);
    $recall->load(['batch.product', 'manufacturer']);

    (new OntologyPublisher())->publishRecallGraph($recall);

    return response()->json($recall, 201);
}
```

- [ ] **Step 13: Run NAFDAC full test suite**

```bash
cd nafdac/backend && ./vendor/bin/phpunit --no-coverage
```

Expected: all tests PASS.

- [ ] **Step 14: Commit**

```bash
git add nafdac/backend/ && git commit -m "feat(nafdac): full backend — products, batches, recalls, OntologyPublisher with column mapping"
```

---

### Task 11: Python FastAPI — skeleton + DB models

**Files:**
- Create: `ai-ontology-service/main.py`
- Create: `ai-ontology-service/database.py`
- Create: `ai-ontology-service/models.py`
- Create: `ai-ontology-service/tests/conftest.py`
- Create: `ai-ontology-service/tests/test_health.py`

- [ ] **Step 1: Write the failing health test**

```python
# ai-ontology-service/tests/test_health.py
import pytest
from httpx import AsyncClient, ASGITransport

@pytest.mark.asyncio
async def test_health_returns_ok(client: AsyncClient):
    response = await client.get("/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}
```

- [ ] **Step 2: Write conftest.py**

```python
# ai-ontology-service/tests/conftest.py
import pytest
import pytest_asyncio
from httpx import AsyncClient, ASGITransport

@pytest_asyncio.fixture
async def client():
    from main import app
    async with AsyncClient(transport=ASGITransport(app=app), base_url="http://test") as c:
        yield c
```

- [ ] **Step 3: Run — confirm it fails**

```bash
cd ai-ontology-service && pip install -r requirements.txt && pytest tests/test_health.py -v
```

Expected: ImportError — no module named `main`.

- [ ] **Step 4: Write database.py**

```python
# ai-ontology-service/database.py
import os
from sqlalchemy.ext.asyncio import create_async_engine, async_sessionmaker, AsyncSession
from sqlalchemy.orm import DeclarativeBase

DATABASE_URL = os.getenv("DATABASE_URL", "postgresql+asyncpg://postgres:secret@localhost:5435/ontology")

engine = create_async_engine(DATABASE_URL, echo=False)
SessionLocal = async_sessionmaker(engine, expire_on_commit=False)

class Base(DeclarativeBase):
    pass

async def get_db() -> AsyncSession:
    async with SessionLocal() as session:
        yield session
```

- [ ] **Step 5: Write models.py**

```python
# ai-ontology-service/models.py
import uuid
from datetime import datetime
from sqlalchemy import String, Boolean, Text, DateTime, ForeignKey, ARRAY
from sqlalchemy.dialects.postgresql import UUID, JSONB
from sqlalchemy.orm import Mapped, mapped_column, relationship
from pgvector.sqlalchemy import Vector
from database import Base

def new_uuid():
    return str(uuid.uuid4())

class PublishedObject(Base):
    __tablename__ = "published_objects"

    id:          Mapped[str] = mapped_column(UUID(as_uuid=False), primary_key=True, default=new_uuid)
    source_nmra: Mapped[str] = mapped_column(String(50))
    object_type: Mapped[str] = mapped_column(String(100))
    source_id:   Mapped[str] = mapped_column(String(255))
    properties:  Mapped[dict] = mapped_column(JSONB)
    created_at:  Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow)
    updated_at:  Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow)

    links_from: Mapped[list["ObjectLink"]] = relationship("ObjectLink", foreign_keys="ObjectLink.from_object_id", back_populates="from_object")
    links_to:   Mapped[list["ObjectLink"]] = relationship("ObjectLink", foreign_keys="ObjectLink.to_object_id",   back_populates="to_object")
    markings:   Mapped[list["ObjectMarking"]] = relationship(back_populates="object")
    embeddings: Mapped[list["Embedding"]]     = relationship(back_populates="object")

class ObjectLink(Base):
    __tablename__ = "object_links"

    id:              Mapped[str] = mapped_column(UUID(as_uuid=False), primary_key=True, default=new_uuid)
    from_object_id:  Mapped[str] = mapped_column(ForeignKey("published_objects.id"))
    to_object_id:    Mapped[str] = mapped_column(ForeignKey("published_objects.id"))
    link_type:       Mapped[str] = mapped_column(String(100))
    created_by_nmra: Mapped[str] = mapped_column(String(50))
    created_at:      Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow)

    from_object: Mapped["PublishedObject"] = relationship("PublishedObject", foreign_keys=[from_object_id], back_populates="links_from")
    to_object:   Mapped["PublishedObject"] = relationship("PublishedObject", foreign_keys=[to_object_id],   back_populates="links_to")

class ObjectMarking(Base):
    __tablename__ = "object_markings"

    id:                  Mapped[str]  = mapped_column(UUID(as_uuid=False), primary_key=True, default=new_uuid)
    object_id:           Mapped[str]  = mapped_column(ForeignKey("published_objects.id"))
    nmra_id:             Mapped[str]  = mapped_column(String(50))
    can_read:            Mapped[bool] = mapped_column(Boolean, default=True)
    can_write:           Mapped[bool] = mapped_column(Boolean, default=False)
    property_exclusions: Mapped[list] = mapped_column(ARRAY(Text), default=list)

    object: Mapped["PublishedObject"] = relationship(back_populates="markings")

class Embedding(Base):
    __tablename__ = "embeddings"

    id:         Mapped[str]   = mapped_column(UUID(as_uuid=False), primary_key=True, default=new_uuid)
    object_id:  Mapped[str]   = mapped_column(ForeignKey("published_objects.id"))
    chunk_text: Mapped[str]   = mapped_column(Text)
    embedding:  Mapped[list]  = mapped_column(Vector(768), nullable=True)
    metadata:   Mapped[dict]  = mapped_column(JSONB, default=dict)

    object: Mapped["PublishedObject"] = relationship(back_populates="embeddings")
```

- [ ] **Step 6: Write main.py**

```python
# ai-ontology-service/main.py
from contextlib import asynccontextmanager
from fastapi import FastAPI
from ontology import router as ontology_router
from retrieve import router as retrieve_router
from generate import router as generate_router

@asynccontextmanager
async def lifespan(app: FastAPI):
    yield

app = FastAPI(title="AI Ontology Service", lifespan=lifespan)

app.include_router(ontology_router)
app.include_router(retrieve_router)
app.include_router(generate_router)

@app.get("/health")
async def health():
    return {"status": "ok"}
```

- [ ] **Step 7: Create stub routers so main.py imports succeed**

```python
# ai-ontology-service/ontology.py
from fastapi import APIRouter
router = APIRouter(prefix="/ontology", tags=["ontology"])
```

```python
# ai-ontology-service/retrieve.py
from fastapi import APIRouter
router = APIRouter(prefix="/ai", tags=["ai"])
```

```python
# ai-ontology-service/generate.py
from fastapi import APIRouter
router = APIRouter()
```

- [ ] **Step 8: Run — confirm it passes**

```bash
cd ai-ontology-service && pytest tests/test_health.py -v
```

Expected: 1 test — PASSED.

- [ ] **Step 9: Commit**

```bash
git add ai-ontology-service/ && git commit -m "feat(ai-service): fastapi skeleton with health check and db models"
```

---

### Task 12: Python FastAPI — /ontology/publish and /ontology/link

**Files:**
- Modify: `ai-ontology-service/ontology.py`
- Create: `ai-ontology-service/tests/test_ontology.py`

- [ ] **Step 1: Write the failing tests**

```python
# ai-ontology-service/tests/test_ontology.py
import pytest
from unittest.mock import AsyncMock, patch
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
    with patch("ontology.SessionLocal") as mock_session_class:
        mock_session = AsyncMock()
        mock_session_class.return_value.__aenter__.return_value = mock_session
        mock_session.execute.return_value.scalar_one_or_none.return_value = None
        mock_session.flush = AsyncMock()
        mock_session.refresh = AsyncMock()
        mock_session.commit = AsyncMock()

        response = await client.post("/ontology/publish", json=MANUFACTURER_PAYLOAD)

    assert response.status_code == 200
    assert response.json()["object_type"] == "Manufacturer"
    assert response.json()["source_nmra"] == "RWANDA_FDA"

@pytest.mark.asyncio
async def test_publish_returns_existing_on_duplicate(client: AsyncClient):
    with patch("ontology.SessionLocal") as mock_session_class:
        mock_session = AsyncMock()
        mock_session_class.return_value.__aenter__.return_value = mock_session
        existing = type("Obj", (), {
            "id": "existing-uuid", "source_nmra": "RWANDA_FDA",
            "object_type": "Manufacturer", "source_id": "1",
            "properties": MANUFACTURER_PAYLOAD["properties"],
        })()
        mock_session.execute.return_value.scalar_one_or_none.return_value = existing

        response = await client.post("/ontology/publish", json=MANUFACTURER_PAYLOAD)

    assert response.status_code == 200
    assert response.json()["id"] == "existing-uuid"
```

- [ ] **Step 2: Run — confirm it fails**

```bash
cd ai-ontology-service && pytest tests/test_ontology.py -v
```

Expected: FAIL — endpoint not found.

- [ ] **Step 3: Implement ontology.py**

```python
# ai-ontology-service/ontology.py
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
                "id": existing.id, "source_nmra": existing.source_nmra,
                "object_type": existing.object_type, "source_id": existing.source_id,
                "properties": existing.properties,
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
                object=obj, nmra_id=nmra, can_read=True,
                can_write=(nmra == req.source_nmra),
            ))

        await session.flush()
        await session.refresh(obj)
        await session.commit()

        return {
            "id": obj.id, "source_nmra": obj.source_nmra,
            "object_type": obj.object_type, "source_id": obj.source_id,
            "properties": obj.properties,
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
```

- [ ] **Step 4: Run — confirm it passes**

```bash
cd ai-ontology-service && pytest tests/test_ontology.py -v
```

Expected: 2 tests — PASSED.

- [ ] **Step 5: Commit**

```bash
git add ai-ontology-service/ && git commit -m "feat(ai-service): publish and link endpoints with access control"
```

---

### Task 13: Python FastAPI — /ai/ingest (embed) and /ai/query (RAG)

**Files:**
- Modify: `ai-ontology-service/retrieve.py`
- Modify: `ai-ontology-service/generate.py`
- Create: `ai-ontology-service/tests/test_retrieve.py`
- Create: `ai-ontology-service/tests/test_generate.py`

- [ ] **Step 1: Write the failing retrieve test**

```python
# ai-ontology-service/tests/test_retrieve.py
import pytest
from unittest.mock import AsyncMock, patch
from httpx import AsyncClient

@pytest.mark.asyncio
async def test_ingest_calls_ollama_embed(client: AsyncClient):
    with patch("retrieve.SessionLocal") as mock_db, \
         patch("retrieve.httpx.AsyncClient") as mock_http:

        mock_session = AsyncMock()
        mock_db.return_value.__aenter__.return_value = mock_session
        mock_session.get.return_value = type("Obj", (), {
            "id": "obj-1", "object_type": "ProductRecall",
            "properties": {"recall_number": "RCL-001", "status": "active"},
        })()
        mock_session.execute.return_value.scalar_one_or_none.return_value = None
        mock_session.add = AsyncMock()
        mock_session.commit = AsyncMock()

        mock_response = AsyncMock()
        mock_response.json.return_value = {"embedding": [0.1] * 768}
        mock_http.return_value.__aenter__.return_value.post.return_value = mock_response

        response = await client.post("/ai/ingest", json={"object_ids": ["obj-1"]})

    assert response.status_code == 200
    assert response.json()["ingested"] == 1
```

- [ ] **Step 2: Run — confirm it fails**

```bash
cd ai-ontology-service && pytest tests/test_retrieve.py -v
```

Expected: FAIL — endpoint not found.

- [ ] **Step 3: Implement retrieve.py**

```python
# ai-ontology-service/retrieve.py
import os
import json
import httpx
from fastapi import APIRouter
from pydantic import BaseModel
from sqlalchemy import select, text
from database import SessionLocal
from models import PublishedObject, Embedding, ObjectMarking

router = APIRouter(prefix="/ai", tags=["ai"])

OLLAMA_HOST   = os.getenv("OLLAMA_HOST", "http://localhost:11434")
EMBED_MODEL   = "nomic-embed-text"


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
                metadata={"object_type": obj.object_type, "source_nmra": obj.source_nmra},
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
            {"chunk_text": r.chunk_text, "object_id": str(r.object_id),
             "metadata": r.metadata, "score": float(r.score)}
            for r in rows
        ]
```

- [ ] **Step 4: Write the failing generate test**

```python
# ai-ontology-service/tests/test_generate.py
import pytest
from unittest.mock import AsyncMock, patch
from httpx import AsyncClient

@pytest.mark.asyncio
async def test_query_returns_cited_answer(client: AsyncClient):
    mock_chunks = [
        {"chunk_text": "ProductRecall [RWANDA_FDA:1] — recall_number: RCL-001 | status: active | classification: Class II | qc_summary: API at 72%",
         "object_id": "obj-recall-1", "metadata": {"object_type": "ProductRecall"}, "score": 0.95},
        {"chunk_text": "Manufacturer [RWANDA_FDA:1] — name: PharmaCo Ltd | country: Kenya",
         "object_id": "obj-mfg-1", "metadata": {"object_type": "Manufacturer"}, "score": 0.88},
    ]

    with patch("generate.semantic_search", return_value=mock_chunks), \
         patch("generate.httpx.AsyncClient") as mock_http:

        mock_stream = AsyncMock()
        mock_stream.__aiter__ = AsyncMock(return_value=iter([
            b'{"response": "Rwanda FDA issued recall RCL-001 [ProductRecall RWANDA_FDA:1].", "done": false}',
            b'{"response": "", "done": true}',
        ]))
        mock_http.return_value.__aenter__.return_value.stream.return_value.__aenter__.return_value = mock_stream

        response = await client.post("/ai/query", json={
            "question": "Are there any active recalls?",
            "nmra_id": "NAFDAC",
        })

    assert response.status_code == 200
    body = response.json()
    assert "answer" in body
    assert "sources" in body
    assert len(body["sources"]) == 2
```

- [ ] **Step 5: Run — confirm it fails**

```bash
cd ai-ontology-service && pytest tests/test_generate.py -v
```

Expected: FAIL.

- [ ] **Step 6: Implement generate.py**

```python
# ai-ontology-service/generate.py
import os
import json
import httpx
from fastapi import APIRouter
from pydantic import BaseModel
from retrieve import semantic_search

router = APIRouter()

OLLAMA_HOST = os.getenv("OLLAMA_HOST", "http://localhost:11434")
LLM_MODEL   = "llama3.1:8b"

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
        f"[{c['metadata'].get('object_type','Object')} {c['object_id']}]\n{c['chunk_text']}"
        for c in chunks
    )
    prompt = f"Context:\n{context}\n\nQuestion: {req.question}\n\nAnswer (cite every claim):"

    answer_parts = []
    async with httpx.AsyncClient(timeout=120) as client:
        async with client.stream("POST", f"{OLLAMA_HOST}/api/generate", json={
            "model": LLM_MODEL,
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
        "answer": "".join(answer_parts),
        "sources": [{"object_id": c["object_id"], "chunk_text": c["chunk_text"]} for c in chunks],
    }
```

- [ ] **Step 7: Run — confirm it passes**

```bash
cd ai-ontology-service && pytest tests/test_generate.py -v
```

Expected: 1 test — PASSED.

- [ ] **Step 8: Run full Python test suite**

```bash
cd ai-ontology-service && pytest tests/ -v
```

Expected: all tests PASS.

- [ ] **Step 9: Commit**

```bash
git add ai-ontology-service/ && git commit -m "feat(ai-service): ingest (embed) and query (RAG) endpoints complete"
```

---

### Task 14: Integration smoke test — full demo flow

Verifies the entire chain works end-to-end with Docker.

- [ ] **Step 1: Generate APP_KEYs for both Laravel services**

```bash
docker compose run --rm rwanda-fda-backend php artisan key:generate --show
docker compose run --rm nafdac-backend    php artisan key:generate --show
```

Copy each output and replace `base64:PLACEHOLDER` in `docker-compose.yml`.

- [ ] **Step 2: Start all services**

```bash
docker compose up --build -d
```

Expected: all 6 containers healthy. Verify:

```bash
docker compose ps
```

Expected: all services show `healthy` or `running`.

- [ ] **Step 3: Check health endpoints**

```bash
curl http://localhost:8000/health   # AI service
curl http://localhost:8001/up       # Rwanda FDA
curl http://localhost:8002/up       # NAFDAC
```

Expected: all return 200.

- [ ] **Step 4: Seed Rwanda FDA — create manufacturer, product, batch, then recall**

```bash
# Manufacturer
curl -s -X POST http://localhost:8001/api/manufacturers \
  -H "Content-Type: application/json" \
  -d '{"name":"PharmaCo Ltd","country":"Kenya","registration_number":"MFG-441","license_status":"active"}' | jq .

# Product (replace <mfg_id> with id from above)
curl -s -X POST http://localhost:8001/api/products \
  -H "Content-Type: application/json" \
  -d '{"manufacturer_id":<mfg_id>,"name":"Amoxicillin 500mg","generic_name":"Amoxicillin","dosage_form":"Capsule","strength":"500mg","registration_number":"PRD-112"}' | jq .

# Batch (replace <product_id> and <mfg_id>)
curl -s -X POST http://localhost:8001/api/batches \
  -H "Content-Type: application/json" \
  -d '{"product_id":<product_id>,"manufacturer_id":<mfg_id>,"batch_number":"LOT-4421","manufacture_date":"2025-01-01","expiry_date":"2027-01-01"}' | jq .

# Recall (replace <batch_id> and <mfg_id>) — this triggers OntologyPublisher
curl -s -X POST http://localhost:8001/api/product-recalls \
  -H "Content-Type: application/json" \
  -d '{"batch_id":<batch_id>,"manufacturer_id":<mfg_id>,"recall_number":"RCL-001","reason":"Substandard API content","classification":"Class II","qc_summary":"Active ingredient at 72% (spec: 95-105%)","status":"active","date_issued":"2026-01-15","scope":"National"}' | jq .
```

Expected: recall returns 201 with the recall data (no internal fields).

- [ ] **Step 5: Verify objects are in the ontology DB**

```bash
docker compose exec ontology-db psql -U postgres -d ontology \
  -c "SELECT source_nmra, object_type, source_id FROM published_objects;"
```

Expected: 4 rows — Manufacturer, Product, Batch, ProductRecall all from RWANDA_FDA.

- [ ] **Step 6: Verify embeddings were created**

```bash
docker compose exec ontology-db psql -U postgres -d ontology \
  -c "SELECT COUNT(*) FROM embeddings;"
```

Expected: 4.

- [ ] **Step 7: Run the demo query from NAFDAC**

```bash
curl -s -X POST http://localhost:8000/ai/query \
  -H "Content-Type: application/json" \
  -d '{"question":"Are there any active recalls I should know about before approving this batch import?","nmra_id":"NAFDAC"}' | jq .
```

Expected: JSON with `answer` containing citations like `[ProductRecall RWANDA_FDA:1]`, `[Manufacturer RWANDA_FDA:1]`, and `sources` array with the relevant chunks. NAFDAC sees the answer without having queried Rwanda FDA's database directly.

- [ ] **Step 8: Final commit**

```bash
git add . && git commit -m "docs: integration smoke test verified — demo flow end to end"
```

---

## Verification Summary

| Check | Command | Expected |
|---|---|---|
| Rwanda FDA tests | `cd rwanda-fda/backend && ./vendor/bin/phpunit` | All PASS |
| NAFDAC tests | `cd nafdac/backend && ./vendor/bin/phpunit` | All PASS |
| Python tests | `cd ai-ontology-service && pytest tests/` | All PASS |
| Health endpoints | `curl localhost:8000/health` | `{"status":"ok"}` |
| Demo query | `POST localhost:8000/ai/query` | Cited answer with Rwanda FDA recall |
