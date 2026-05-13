# Ella POS Project Versioning

## [v1.1.0] - 2026-04-16
### Added
- **Soft-Delete (Archiving)**: Implemented archiving system for User Management to preserve historical data.
- **Granular Permissions**: Added specific permissions for Sales History, Expenses, Buyers, Wallet, and Product History.
- **Enhanced "Pay Later"**: Added predefined due date options (1 Month, 45 Days) to the POS checkout.
- **A4 Receipt Support**: Defaulted receipt paper size to A4 for improved record keeping.
- **View Comments Modal**: Consolidated transaction and settlement notes into a unified interactive modal in the Accounts Receivable ledger.

### Fixed
- **POS Rounding Bug**: Fixed a floating-point error where exact payments were sometimes flagged as insufficient.
- **User API**: Resolved 500 error in User Archiving API and cleared active sessions upon archiving.

### Performance
- Optimized POS checkout by standardizing currency rounding logic.

---
*Created by Antigravity AI coding assistant.*
