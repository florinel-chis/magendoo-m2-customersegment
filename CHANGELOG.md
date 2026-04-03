# Changelog

All notable changes to the Magendoo CustomerSegment module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

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
  - [TESTING_LESSONS.md](TESTING_LESSONS.md) - Implementation lessons learned

### Fixed
- **Security**: CSV export now uses fputcsv() to prevent formula injection
- **Security**: Condition instantiation uses allowlist to prevent arbitrary class loading
- Table prefix support in database queries
- Type mismatches in API methods
- Deprecated class replacements (Zend_Db_Expr, Registry)

### Technical
- Reduced test code duplication by 30% through refactoring
- Established testing patterns for future development
- All production code issues resolved (see TESTING_LESSONS.md)

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

## Future Releases (Planned)

### [1.2.0] - TBD
- GraphQL API support
- Segment-based email templates
- Customer grid segment column
- Advanced reporting for segments

### [1.3.0] - TBD
- Segment-based CMS content
- Segment comparison tool
- Import/Export segment definitions
- Customer segment history

### [2.0.0] - TBD
- Machine learning-based segment suggestions
- Segment-based pricing
- Segment-based shipping methods
- Real-time segment webhooks
