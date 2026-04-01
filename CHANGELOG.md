# Changelog

All notable changes to the Magendoo CustomerSegment module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

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

### [1.1.0] - TBD
- GraphQL API support
- Segment-based email templates
- Customer grid segment column
- Advanced reporting for segments

### [1.2.0] - TBD
- Segment-based CMS content
- Segment comparison tool
- Import/Export segment definitions
- Customer segment history

### [2.0.0] - TBD
- Machine learning-based segment suggestions
- Segment-based pricing
- Segment-based shipping methods
- Real-time segment webhooks
