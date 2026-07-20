# Changelog

All notable changes to the Magendoo CustomerSegment module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [2.0.0] - 2026-07-20

A correctness-focused release that fixes the segmentation engine along its main axis. Several fixes
change stored data and behaviour, so this is a breaking release.

> **Upgrade step (required):** run `bin/magento setup:upgrade`. It applies two patches:
> a schema patch that removes stale duplicate indexes/foreign keys left by older installs, and a
> data patch that rewrites any segment whose conditions were stored in the legacy numeric-key shape
> into the canonical `conditions` tree. Re-run refresh on affected segments afterwards.

### Fixed
- **CRITICAL**: Admin-created segments ignored their conditions and matched every customer. Condition
  children were serialized under numeric position keys with no `conditions` key, so the rule tree
  loaded as an empty combine. Conditions now serialize under an ordered `conditions` list that
  round-trips through Magento's rule engine. Existing broken rows are migrated by `setup:upgrade`.
- **CRITICAL**: Whole condition families matched nobody or inverted their meaning — order
  payment/status/coupon/country, product purchased-negation and purchased-categories, cart subtotal
  `>`/`<`, and cart/negation operators. These now evaluate correctly, with negation applied at the
  customer level and explicit zero-row (no orders / empty cart) handling.
- **CRITICAL**: Partial REST updates (`PUT`) overwrote stored scalars with defaulted DTO values,
  resetting `customer_count`, `is_active`, `refresh_mode`, and wiping `conditions_serialized`.
  Updates now merge onto the loaded model.
- **MAJOR**: Membership matching was O(customers x conditions), issuing one query per customer per
  leaf. Conditions that can express themselves as a single query now resolve set-based.
- **MAJOR**: `refresh <id> --export` always crashed under `strict_types`; the CLI now casts the id
  and handles export correctly.
- **MAJOR**: CSV export crashed on PHP 8.4 (`fputcsv` deprecation). Fixed, and export now neutralizes
  leading formula characters to prevent spreadsheet formula injection.
- **MAJOR**: MView customer subscription refreshed the wrong segment (customer ids were treated as
  segment ids). The changelog/indexer wiring is now segment-scoped and no longer self-perpetuates.
- **MEDIUM**: Staleness checks compared stored UTC timestamps against session-timezone `NOW()`;
  comparisons are now UTC-consistent (`UTC_TIMESTAMP()` / UTC-aware parsing).
- **MEDIUM**: Segment refresh is now atomic (remove-all + mass-assign wrapped in one transaction).

### Added
- `Helper\Data::isEnabled()` now actually gates the module: realtime observers, the cron dispatcher,
  and the CLI no-op when `customersegment/general/enabled` is off.
- `SegmentManagementInterface::updateCustomerMembership(int $customerId)` — re-evaluates a single
  customer against active realtime segments (no full rescan). Realtime observers call only this.
- Activity log is now live: `magendoo_customer_segment_log` receives real rows on save and refresh
  (`Model\ResourceModel\Log::log()`).
- `Setup\Patch\Schema\DropLegacyDuplicateIndexes` and `Setup\Patch\Data\MigrateConditionsSerialized`.

### Changed
- **Behavior:** a segment with no conditions now matches **no** customers. Previously an empty (or
  admin-saved-but-broken) condition tree silently matched every customer. Define at least one
  condition to select customers.
- License is MIT across the module (`composer.json`, headers, `LICENSE`).
- `db_schema_whitelist.json` regenerated to match the explicit referenceIds in `db_schema.xml`
  (fixes duplicate indexes and foreign keys on clean installs).
- System config `enabled` is now a global (default-scope-only) operational toggle.
- Test suite migrated to PHPUnit 12 attributes.

### Removed
- Dead `Model\SqlBuilder` scaffold (replaced by real set-based matching).
- Non-functional customer-grid segment column stub (see Roadmap).
- `viewed_categories` product condition — it was a placeholder that always matched nobody (see Roadmap).

## [1.1.0] - 2026-04-03

### Added
- Product Interactions condition type (viewed categories, purchased products, purchased categories, wishlist items count) with `between` operator
- SqlBuilder for batch customer validation (N+1 performance fix)
- Segment indexer with mview support (`etc/indexer.xml`, `etc/mview.xml`)
- System configuration admin panel (`etc/adminhtml/system.xml`, `etc/config.xml`) for enable/disable, default refresh mode, cron schedule
- RefreshButton on segment edit page
- Matched Customers tab on segment edit page with pagination
- MatchedCustomersDataProvider for matched customers grid
- CustomerSegmentRelation model and ResourceModel for segment-customer relationships
- Product::class added to condition type allowlists (SegmentManagement + NewConditionHtml)
- Cart Price Rule integration now loads segment options from repository (was stub)
- Functional test suite (Playwright) — API, CLI, Admin UI, Integration tests
- Unit tests for Product condition and SqlBuilder

### Fixed
- **CRITICAL**: Segment model extended AbstractModel instead of AbstractExtensibleModel — getExtensionAttributes() crashed all REST API serialization
- **CRITICAL**: SegmentSearchResultsInterface used short class name in @return docblock — Magento Web API reflection failed with "Class SegmentInterface does not exist" on getList endpoint
- **CRITICAL**: Edit controller stored full Segment model object in DataPersistor (session) — non-serializable dependencies (FormFactory/ObjectManager/Closures) caused "Serialization of 'Closure' is not allowed" fatal error on every admin page load
- Conditions blocks now load segment from DB via request param instead of DataPersistor
- Removed DataPersistorInterface dependency from Conditions blocks

### Changed
- Segment model constructor now accepts ExtensionAttributesFactory and AttributeValueFactory (required by AbstractExtensibleModel)
- Version bumped to 1.1.0

## [1.0.1] - 2026-04-01

### Added
- **Comprehensive Unit Test Suite** - 106 tests with 198 assertions
  - SegmentManagement tests (31 tests) - CRUD, refresh, export, validation
  - Condition tests (75 tests) - Customer, Order, Cart, Combine conditions
  - Security-critical tests for CSV injection prevention
  - Condition type allowlist verification
  - Error handling and edge case coverage
- Testing documentation:
  - [TESTING.md](TESTING.md) - Testing patterns and best practices

### Fixed
- **Security**: CSV export now uses fputcsv() to prevent formula injection
- **Security**: Condition instantiation uses allowlist to prevent arbitrary class loading
- Table prefix support in database queries
- Type mismatches in API methods
- Deprecated class replacements (Zend_Db_Expr, Registry)

### Technical
- Reduced test code duplication by 30% through refactoring
- Established testing patterns for future development
- All production code issues resolved

## [1.0.0] - 2026-04-01

### Added
- Initial release of Magendoo CustomerSegment module
- Customer segmentation with dynamic rules engine
- Three condition types: Customer Attributes, Order History, Shopping Cart
- Admin grid for segment management with filtering and mass actions
- Create, edit, delete, and refresh segments
- Three refresh modes: Manual, Cron, Real-time
- REST API for all segment operations
- CLI command for segment refresh
- Integration with Cart Price Rules
- Event system for extensibility
- Database schema with foreign key constraints
- Multi-condition support with AND/OR logic
- Customer count caching
- Export segment customers (CSV/XML)
- Full ACL support for permissions
- Observer-based real-time updates
- Comprehensive documentation

### Features
- **Dynamic Segments**: Automatically assign customers based on rules
- **Visual Rule Builder**: Admin UI for building complex conditions
- **Batch Processing**: Efficient customer matching in batches of 1000
- **Scheduled Updates**: Cron-based segment refresh (default: daily at 2 AM)
- **Event-Driven**: Real-time updates on customer events
- **API Access**: Full REST API coverage
- **Extensible**: Plugin and event support for custom conditions

### Technical
- Magento 2.4.x compatibility
- PHP 8.1+ support
- Service Contracts pattern
- Dependency Injection throughout
- Unit and integration test support
- Playwright functional tests

---

## Roadmap (not yet implemented)

- Customer grid segment column / filtering
- Viewed-categories (product-view) condition
- GraphQL API support
- Segment-based email templates and CMS content
- Import/Export of segment definitions
