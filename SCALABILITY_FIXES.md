# Scalability & Consistency Fixes

## Problem 1: JSON Inconsistency

### Current Schema (Problematic)
```php
memory_entities:
  - attributes (JSON) ← AI can use any key names
```

### Solution A: Strict Schema (Best for consistency)

Create new migration:

```php
// database/migrations/2026_02_13_000003_add_person_columns.php

Schema::table('memory_entities', function (Blueprint $table) {
    // Common person attributes as real columns
    $table->string('email')->nullable()->index();
    $table->string('phone')->nullable();
    $table->string('job_title')->nullable()->index();
    $table->string('company')->nullable()->index();
    $table->string('department')->nullable();
    $table->string('location')->nullable();
    $table->date('birthday')->nullable();
    $table->text('address')->nullable();

    // Indexes for common queries
    $table->index(['entity_type', 'company']);
    $table->index(['entity_type', 'job_title']);
});
```

Update Memory Entity Model:
```php
// app/Models/MemoryEntity.php

protected $fillable = [
    // ... existing fields ...
    'email',
    'phone',
    'job_title',
    'company',
    'department',
    'location',
    'birthday',
    'address',
    'attributes', // Keep for rare/custom fields
];

protected $casts = [
    // ... existing casts ...
    'birthday' => 'date',
];

// Add validation
public static function findOrCreateEntity(array $data): self
{
    // Normalize common attributes to columns
    $columnData = [
        'name' => $data['name'],
        'entity_type' => $data['entity_type'],
        'entity_subtype' => $data['entity_subtype'] ?? null,
        'description' => $data['description'] ?? null,
    ];

    // Extract known attributes to columns
    $knownAttributes = ['email', 'phone', 'job_title', 'company', 'department', 'location', 'birthday', 'address'];
    foreach ($knownAttributes as $attr) {
        if (!empty($data['attributes'][$attr])) {
            $columnData[$attr] = $data['attributes'][$attr];
            unset($data['attributes'][$attr]);
        }
    }

    // Remaining attributes go to JSON column
    $columnData['attributes'] = $data['attributes'] ?? [];

    // Rest of the method...
}
```

Update AI System Prompt:
```php
// config/ai.php - Add to system prompt

**CRITICAL: STRICT ATTRIBUTE NAMING:**
When storing person information, use EXACTLY these attribute names:
- email (NOT: mail, email_address, e-mail)
- phone (NOT: phone_number, tel, telephone, mobile)
- job_title (NOT: position, role, title, career_title)
- company (NOT: employer, organization, works_at)
- department (NOT: dept, division, team)
- location (NOT: office, city, workplace)
- birthday (NOT: birth_date, dob, date_of_birth)
- address (NOT: home_address, street_address)

WRONG: {"position": "Manager", "employer": "Acme"}
RIGHT: {"job_title": "Manager", "company": "Acme"}
```

### Solution B: Post-Processing Normalization

Add normalization service:

```php
// app/AI/Services/AttributeNormalizationService.php

class AttributeNormalizationService
{
    private array $attributeMap = [
        'job_title' => ['position', 'role', 'title', 'career_title', 'job', 'occupation'],
        'company' => ['employer', 'organization', 'works_at', 'workplace'],
        'email' => ['mail', 'email_address', 'e-mail', 'electronic_mail'],
        'phone' => ['phone_number', 'tel', 'telephone', 'mobile', 'cell'],
        // ... more mappings
    ];

    public function normalizeAttributes(array $attributes): array
    {
        $normalized = [];

        foreach ($attributes as $key => $value) {
            $canonicalKey = $this->findCanonicalName($key);
            $normalized[$canonicalKey] = $value;
        }

        return $normalized;
    }

    private function findCanonicalName(string $key): string
    {
        $key = strtolower($key);

        foreach ($this->attributeMap as $canonical => $aliases) {
            if ($key === $canonical || in_array($key, $aliases)) {
                return $canonical;
            }
        }

        return $key; // Keep original if no mapping found
    }
}

// Use in MemoryEntity boot:
static::creating(function ($entity) {
    if ($entity->attributes) {
        $normalizer = new AttributeNormalizationService();
        $entity->attributes = $normalizer->normalizeAttributes($entity->attributes);
    }
});
```

---

## Problem 2: Vector Search Scalability

### Current Implementation (doesn't scale)
```php
// Loads ALL embeddings - O(N) complexity
$embeddings = MemoryEmbedding::all();
foreach ($embeddings as $emb) {
    calculateSimilarity($query, $emb->embedding);
}
```

### Solution: Use Proper Vector Database

#### Option 1: PostgreSQL with pgvector (Recommended for self-hosted)

```bash
# Install pgvector extension
composer require pgvector/pgvector
```

Migration:
```php
// 2026_02_13_000004_add_vector_column.php

Schema::table('memory_embeddings', function (Blueprint $table) {
    $table->dropColumn('embedding'); // Remove JSON column
});

// Run raw SQL for vector column
DB::statement('ALTER TABLE memory_embeddings ADD COLUMN embedding vector(1536)');
DB::statement('CREATE INDEX ON memory_embeddings USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
```

Update search:
```php
// MemoryEmbedding.php

public static function findSimilar(array $queryEmbedding, int $limit = 10)
{
    // pgvector does approximate nearest neighbor search - O(log N)
    $results = DB::select("
        SELECT memory_id,
               1 - (embedding <=> ?) as similarity
        FROM memory_embeddings
        ORDER BY embedding <=> ?
        LIMIT ?
    ", [
        json_encode($queryEmbedding), // pgvector handles vector type
        json_encode($queryEmbedding),
        $limit
    ]);

    // Load memories
    $memoryIds = array_column($results, 'memory_id');
    $memories = Memory::whereIn('id', $memoryIds)->get()->keyBy('id');

    return array_map(function($result) use ($memories) {
        return [
            'memory' => $memories[$result->memory_id],
            'similarity' => $result->similarity
        ];
    }, $results);
}
```

**Performance at 1M records**: ~50-200ms ✅

#### Option 2: Pinecone (Managed Vector DB)

```bash
composer require pinecone-io/pinecone-php-client
```

```php
// app/AI/Services/PineconeService.php

class PineconeService
{
    private $client;

    public function __construct()
    {
        $this->client = new \Pinecone\Client(env('PINECONE_API_KEY'));
    }

    public function upsertEmbedding(int $memoryId, array $embedding)
    {
        $this->client->upsert([
            'id' => "memory_{$memoryId}",
            'values' => $embedding,
            'metadata' => ['memory_id' => $memoryId]
        ]);
    }

    public function search(array $queryEmbedding, int $limit = 10)
    {
        $results = $this->client->query([
            'vector' => $queryEmbedding,
            'topK' => $limit,
            'includeMetadata' => true
        ]);

        $memoryIds = array_column($results['matches'], 'metadata.memory_id');
        $memories = Memory::whereIn('id', $memoryIds)->get();

        return $memories;
    }
}
```

**Performance at 1M records**: ~100-300ms ✅
**Cost**: ~$70/month for 1M vectors (starter tier)

#### Option 3: Qdrant (Self-hosted Vector DB)

```bash
# Docker
docker run -p 6333:6333 qdrant/qdrant

# PHP Client
composer require qdrant/php-client
```

**Performance at 1M records**: ~50-150ms ✅
**Cost**: Free (self-hosted)

---

## Hybrid Solution: Phase-Based Approach

### Phase 1 (Current): 0-10K records
- Current implementation works fine
- No changes needed

### Phase 2: 10K-100K records
- Add strict schema (Solution 1A)
- Add attribute normalization
- Keep current vector search (acceptable performance)

### Phase 3: 100K-1M records
- Migrate to pgvector or Pinecone
- Add pagination (load max 100 results)
- Add archiving (move old memories to cold storage)

### Phase 4: 1M+ records
- Partition by date/user
- Use Elasticsearch for full-text search
- Implement data retention policies

---

## Immediate Actions Recommended

### 1. Fix JSON Consistency (High Priority)

Create strict schema migration:
```bash
php artisan make:migration add_person_columns_to_memory_entities
```

### 2. Add Monitoring

```php
// Check current scale
Memory::count(); // How many memories?
MemoryEntity::count(); // How many entities?

// Benchmark search performance
$start = microtime(true);
Memory::searchSemantic('test query', 10);
echo "Search took: " . (microtime(true) - $start) . " seconds\n";
```

### 3. Set Performance Thresholds

Add to config:
```php
// config/memory.php

return [
    'max_vector_search_records' => 50000, // Disable vector search above this
    'pagination_limit' => 100, // Max results per query
    'archive_after_days' => 365, // Archive memories older than 1 year
];
```

---

## Cost Projection at Scale

### Database Storage (MySQL)
- 1M memories: ~1 GB = **Free** (within any plan)
- 1M embeddings in JSON: ~6 GB = **Free**

### Vector Database (if needed)
- **pgvector** (PostgreSQL extension): **Free**
- **Pinecone**: $70/month for 1M vectors
- **Qdrant self-hosted**: **Free**
- **Qdrant cloud**: $25/month for 1M vectors

### OpenAI Embeddings (one-time generation)
- 1M memories × 200 tokens avg = 200M tokens
- 200M tokens × $0.02/1M = **$4 total** (one-time)

---

## Bottom Line

### ✅ Current System:
- **Great for**: 0-50K records
- **Performance**: Excellent
- **Issues**: JSON inconsistency, vector search won't scale

### ⚠️ At 100K records:
- **Need**: Strict schema for common attributes
- **Need**: Attribute normalization
- **Performance**: Acceptable (2-5s searches)

### ❌ At 1M records:
- **Must have**: Vector database (pgvector/Pinecone/Qdrant)
- **Must have**: Strict schema
- **Must have**: Pagination and archiving
- **Cost**: $0-70/month (depending on vector DB choice)

---

## My Recommendation

**Do this now** (before you have data inconsistency):
1. ✅ Create migration for strict schema (common person attributes as columns)
2. ✅ Update system prompt with EXACT attribute names
3. ✅ Add attribute normalization service

**Do later** (when you hit 50K+ records):
1. Migrate to pgvector (free) or Pinecone (paid but managed)
2. Add pagination
3. Implement archiving

**Your 5-year plan**:
- Years 1-2: Current system (0-50K records)
- Year 3: Add pgvector (50K-500K records)
- Years 4-5: Full optimization (500K-1M+ records)

Would you like me to implement the strict schema solution right now? It will prevent the JSON inconsistency problem before it starts!
