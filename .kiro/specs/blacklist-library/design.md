# Design Document: 黑名单库功能

## Overview

黑名单库功能为商品管理系统提供敏感词过滤和处理能力。系统支持管理多个黑名单库（如品牌词、违禁词、敏感词），每个库可包含数万至十几万条词条。通过高效的 Aho-Corasick 多模式匹配算法，系统可在毫秒级完成大规模词条匹配，并支持在商品同步、刊登等流程中自动应用黑名单规则。

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Frontend (React)                          │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────┐  │
│  │ 黑名单库管理 │  │ 词条管理    │  │ 扫描结果 & 批量处理     │  │
│  └─────────────┘  └─────────────┘  └─────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Backend (NestJS)                            │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                   BlacklistModule                            ││
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  ││
│  │  │ Controller  │  │ Service     │  │ MatchingEngine      │  ││
│  │  │ - CRUD API  │  │ - 业务逻辑  │  │ - Aho-Corasick      │  ││
│  │  │ - Scan API  │  │ - 批量处理  │  │ - 内存缓存          │  ││
│  │  │ - Process   │  │ - 异步任务  │  │ - 增量更新          │  ││
│  │  └─────────────┘  └─────────────┘  └─────────────────────┘  ││
│  └─────────────────────────────────────────────────────────────┘│
│                              │                                   │
│  ┌───────────────────────────┼───────────────────────────────┐  │
│  │                    Integration Layer                       │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐    │  │
│  │  │ SyncModule  │  │ ListingMod  │  │ ProductPoolMod  │    │  │
│  │  │ 同步时检测  │  │ 刊登时检测  │  │ 入库时检测      │    │  │
│  │  └─────────────┘  └─────────────┘  └─────────────────┘    │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Data Layer                                  │
│  ┌─────────────────────┐  ┌─────────────────────────────────┐   │
│  │    PostgreSQL       │  │      Memory Cache               │   │
│  │  - BlacklistLibrary │  │  - Aho-Corasick Automaton       │   │
│  │  - BlacklistEntry   │  │  - Entry Lookup Map             │   │
│  │  - ScanResult       │  │  - Library Status Cache         │   │
│  │  - ProcessLog       │  │                                 │   │
│  └─────────────────────┘  └─────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

## Components and Interfaces

### 1. BlacklistLibraryController

```typescript
@Controller('blacklist')
export class BlacklistLibraryController {
  // 黑名单库 CRUD
  @Post('libraries')
  createLibrary(dto: CreateLibraryDto): Promise<BlacklistLibrary>;
  
  @Get('libraries')
  listLibraries(query: ListLibraryQuery): Promise<PaginatedResult<BlacklistLibrary>>;
  
  @Get('libraries/:id')
  getLibrary(id: string): Promise<BlacklistLibrary>;
  
  @Patch('libraries/:id')
  updateLibrary(id: string, dto: UpdateLibraryDto): Promise<BlacklistLibrary>;
  
  @Delete('libraries/:id')
  deleteLibrary(id: string): Promise<void>;
  
  // 词条管理
  @Post('libraries/:id/entries')
  addEntry(libraryId: string, dto: CreateEntryDto): Promise<BlacklistEntry>;
  
  @Post('libraries/:id/entries/import')
  importEntries(libraryId: string, file: Express.Multer.File): Promise<ImportResult>;
  
  @Get('libraries/:id/entries')
  listEntries(libraryId: string, query: ListEntryQuery): Promise<PaginatedResult<BlacklistEntry>>;
  
  @Delete('entries/:id')
  deleteEntry(id: string): Promise<void>;
  
  @Post('entries/batch-delete')
  batchDeleteEntries(dto: BatchDeleteDto): Promise<BatchResult>;
  
  // 扫描和处理
  @Post('scan')
  scanProducts(dto: ScanProductsDto): Promise<ScanTask>;
  
  @Post('scan/pool')
  scanProductPool(dto: ScanPoolDto): Promise<ScanTask>;  // 主动扫描商品池
  
  @Post('scan/listing')
  scanListingProducts(dto: ScanListingDto): Promise<ScanTask>;  // 主动扫描刊登商品
  
  @Get('scan/:taskId')
  getScanResult(taskId: string): Promise<ScanResult>;
  
  @Get('scan/:taskId/matches')
  getScanMatches(taskId: string, query: MatchQuery): Promise<PaginatedResult<ScanMatch>>;
  
  @Post('process')
  processMatches(dto: ProcessMatchesDto): Promise<ProcessResult>;
  
  @Post('process/undo/:logId')
  undoProcess(logId: string): Promise<void>;  // 撤销处理
  
  // 匹配测试
  @Post('test-match')
  testMatch(dto: TestMatchDto): Promise<MatchResult[]>;
}
```

### 2. BlacklistService

```typescript
@Injectable()
export class BlacklistService {
  // 库管理
  createLibrary(dto: CreateLibraryDto): Promise<BlacklistLibrary>;
  updateLibrary(id: string, dto: UpdateLibraryDto): Promise<BlacklistLibrary>;
  deleteLibrary(id: string): Promise<void>;
  toggleLibraryStatus(id: string, enabled: boolean): Promise<void>;
  
  // 词条管理
  addEntry(libraryId: string, dto: CreateEntryDto): Promise<BlacklistEntry>;
  importEntries(libraryId: string, entries: EntryData[]): Promise<ImportResult>;
  deleteEntry(id: string): Promise<void>;
  batchDeleteEntries(ids: string[]): Promise<BatchResult>;
  
  // 扫描
  scanProducts(productIds: string[], libraryIds?: string[]): Promise<ScanTask>;
  scanSingleProduct(product: ProductData): Promise<MatchResult[]>;
  
  // 处理
  processMatches(matches: MatchResult[], action: ProcessAction): Promise<ProcessResult>;
  undoProcess(processLogId: string): Promise<void>;
}
```

### 3. BlacklistMatchingEngine

```typescript
@Injectable()
export class BlacklistMatchingEngine {
  // 初始化和缓存管理
  initialize(): Promise<void>;
  rebuildIndex(libraryId?: string): Promise<void>;
  
  // 匹配
  match(text: string, libraryIds?: string[]): MatchResult[];
  matchProduct(product: ProductData, libraryIds?: string[]): ProductMatchResult;
  
  // 替换
  replace(text: string, matches: MatchResult[]): string;
  
  // 缓存状态
  getCacheStats(): CacheStats;
  invalidateCache(libraryId: string): void;
}
```

## Data Models

### Database Schema (Prisma)

```prisma
// 黑名单库
model BlacklistLibrary {
  id          String              @id @default(uuid())
  name        String              @db.VarChar(100)
  description String?             @db.VarChar(500)
  type        BlacklistType       // brand, prohibited, sensitive, custom
  enabled     Boolean             @default(true)
  entryCount  Int                 @default(0) @map("entry_count")
  createdAt   DateTime            @default(now()) @map("created_at")
  updatedAt   DateTime            @updatedAt @map("updated_at")
  
  entries     BlacklistEntry[]
  scanResults BlacklistScanResult[]
  
  @@map("blacklist_libraries")
}

// 黑名单词条
model BlacklistEntry {
  id            String          @id @default(uuid())
  libraryId     String          @map("library_id")
  keyword       String          @db.VarChar(200)
  matchType     MatchType       @default(contains) @map("match_type")
  actionType    ActionType      @default(mark) @map("action_type")
  replacement   String?         @db.VarChar(200)
  caseSensitive Boolean         @default(false) @map("case_sensitive")
  enabled       Boolean         @default(true)
  createdAt     DateTime        @default(now()) @map("created_at")
  
  library       BlacklistLibrary @relation(fields: [libraryId], references: [id], onDelete: Cascade)
  
  @@unique([libraryId, keyword])
  @@index([libraryId])
  @@index([keyword])
  @@map("blacklist_entries")
}

// 扫描结果
model BlacklistScanResult {
  id            String          @id @default(uuid())
  taskId        String          @map("task_id")
  libraryId     String          @map("library_id")
  productId     String          @map("product_id")
  productSku    String          @map("product_sku") @db.VarChar(100)
  productType   String          @map("product_type") @db.VarChar(20) // pool, listing
  field         String          @db.VarChar(50) // title, description, bulletPoints
  matchedWord   String          @map("matched_word") @db.VarChar(200)
  entryId       String          @map("entry_id")
  position      Int             // 匹配位置
  context       String?         @db.VarChar(500) // 上下文片段
  processed     Boolean         @default(false)
  processedAt   DateTime?       @map("processed_at")
  createdAt     DateTime        @default(now()) @map("created_at")
  
  library       BlacklistLibrary @relation(fields: [libraryId], references: [id])
  
  @@index([taskId])
  @@index([productId])
  @@index([processed])
  @@map("blacklist_scan_results")
}

// 扫描任务
model BlacklistScanTask {
  id            String              @id @default(uuid())
  status        ScanTaskStatus      @default(pending)
  totalProducts Int                 @default(0) @map("total_products")
  scannedCount  Int                 @default(0) @map("scanned_count")
  matchedCount  Int                 @default(0) @map("matched_count")
  libraryIds    Json                @map("library_ids") // 扫描的库ID列表
  productIds    Json?               @map("product_ids") // 指定的商品ID列表
  productType   String              @map("product_type") @db.VarChar(20)
  errorMessage  String?             @map("error_message")
  startedAt     DateTime            @default(now()) @map("started_at")
  finishedAt    DateTime?           @map("finished_at")
  createdAt     DateTime            @default(now()) @map("created_at")
  
  @@index([status])
  @@map("blacklist_scan_tasks")
}

// 处理日志
model BlacklistProcessLog {
  id            String          @id @default(uuid())
  taskId        String?         @map("task_id")
  productId     String          @map("product_id")
  productSku    String          @map("product_sku") @db.VarChar(100)
  field         String          @db.VarChar(50)
  action        ActionType
  originalValue String          @map("original_value") @db.Text
  newValue      String?         @map("new_value") @db.Text
  matchedWords  Json            @map("matched_words") // 匹配到的词条列表
  createdAt     DateTime        @default(now()) @map("created_at")
  
  @@index([productId])
  @@index([taskId])
  @@map("blacklist_process_logs")
}

enum BlacklistType {
  brand       // 品牌词
  prohibited  // 违禁词
  sensitive   // 敏感词
  custom      // 自定义
}

enum MatchType {
  exact       // 精确匹配
  contains    // 包含匹配
  regex       // 正则匹配
  word        // 单词边界匹配
}

enum ActionType {
  mark        // 标记
  delete      // 删除词条
  replace     // 替换
  skip        // 跳过商品
}

enum ScanTaskStatus {
  pending
  running
  completed
  failed
  cancelled
}
```

### TypeScript Interfaces

```typescript
// DTOs
interface CreateLibraryDto {
  name: string;
  description?: string;
  type: BlacklistType;
}

interface CreateEntryDto {
  keyword: string;
  matchType?: MatchType;
  actionType?: ActionType;
  replacement?: string;
  caseSensitive?: boolean;
}

interface ScanProductsDto {
  productType: 'pool' | 'listing';
  productIds?: string[];
  libraryIds?: string[];
  fields?: string[];
}

interface ProcessMatchesDto {
  resultIds: string[];
  action: ActionType;
  replacement?: string;
}

// Results
interface MatchResult {
  entryId: string;
  libraryId: string;
  keyword: string;
  field: string;
  position: number;
  length: number;
  context: string;
  actionType: ActionType;
  replacement?: string;
}

interface ProductMatchResult {
  productId: string;
  productSku: string;
  matches: MatchResult[];
  hasMatch: boolean;
}

interface ImportResult {
  total: number;
  created: number;
  skipped: number;
  errors: string[];
}
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Library CRUD Data Integrity
*For any* blacklist library, creating, reading, updating, and deleting operations should preserve data integrity - created data should be retrievable, updates should persist, and deletes should cascade to entries.
**Validates: Requirements 1.1, 1.2, 1.3, 1.4**

### Property 2: Entry Deduplication
*For any* set of entries imported to a library, the resulting entry count should equal the number of unique keywords, with duplicates being skipped.
**Validates: Requirements 2.2, 2.3**

### Property 3: Scan Coverage Completeness
*For any* product with blacklist keywords in any scannable field (title, description, bulletPoints), scanning should detect all occurrences with correct field and position information.
**Validates: Requirements 3.1, 3.2, 3.3**

### Property 4: Replacement Correctness
*For any* text containing blacklist keywords, applying replacement should result in text where all matched keywords are replaced with their configured replacement values, and no other content is modified.
**Validates: Requirements 4.1, 4.2**

### Property 5: Process Reversibility
*For any* processed product, undoing the process should restore the original field values exactly as they were before processing.
**Validates: Requirements 4.5**

### Property 6: Library Status Affects Matching
*For any* disabled library, its entries should not be included in matching results, and enabling the library should include its entries in subsequent matches.
**Validates: Requirements 1.5, 5.3**

### Property 7: Matching Engine Consistency
*For any* set of entries in the matching engine, adding or removing entries should result in matching behavior that reflects the current entry set without requiring full rebuild.
**Validates: Requirements 6.1, 6.3**

## Error Handling

| Error Type | Condition | Response |
|------------|-----------|----------|
| LibraryNotFoundError | Library ID does not exist | 404 Not Found |
| EntryDuplicateError | Keyword already exists in library | 409 Conflict (skip in batch) |
| InvalidMatchTypeError | Invalid match type value | 400 Bad Request |
| ScanTaskNotFoundError | Scan task ID does not exist | 404 Not Found |
| ProcessLogNotFoundError | Process log for undo not found | 404 Not Found |
| ImportParseError | CSV/Excel file parse failure | 400 Bad Request with details |
| MatchingEngineError | Engine initialization failure | 500 Internal Server Error |

## Testing Strategy

### Unit Testing
- Test BlacklistService CRUD operations
- Test BlacklistMatchingEngine matching logic
- Test CSV/Excel import parsing
- Test replacement logic

### Property-Based Testing
Using `fast-check` library for property-based tests:

1. **Library CRUD Property Test**: Generate random library data, perform CRUD operations, verify data integrity
2. **Entry Deduplication Property Test**: Generate entries with duplicates, verify unique count
3. **Scan Coverage Property Test**: Generate products with known keywords, verify all are detected
4. **Replacement Property Test**: Generate text with keywords, verify replacement correctness
5. **Reversibility Property Test**: Process and undo, verify original restoration
6. **Status Affects Matching Property Test**: Toggle library status, verify matching behavior
7. **Engine Consistency Property Test**: Add/remove entries, verify matching reflects changes

### Performance Testing
- Benchmark matching with 100,000 entries
- Verify single product match completes in < 100ms
- Test bulk import of 100,000 entries

