---
name: Database & File Migration Standards
description: Guidelines for safely adapting, matching, and updating database schemas and files when porting legacy functions or migrating features from deployment.
---

# Database & File Migration Guidelines

When moving files, porting legacy functions, or applying updates from a deployment environment to this project, it is critical that the **database schema perfectly matches the functional requirements** of the updated code. 

Follow these strict guidelines to ensure safe and functional integration:

## 1. Database Schema Verification & Matching
Before fully integrating a ported PHP file, you must audit its SQL queries against the current database schema:
*   **Identify Requirements**: Extract all `SELECT`, `INSERT`, `UPDATE`, and `DELETE` queries from the new/ported file.
*   **Cross-Reference Columns**: Verify that every column referenced in the queries exists in the current local/production database. 
*   **Identify Missing Schema**: If a feature requires new data points (e.g., adding `discount_type` or `capital_cost`), note these missing columns immediately.

## 2. Safe Database Updates (Migrations)
If the database needs to be updated to match the new functions, proceed with caution and follow safe update protocols:
*   **Never Drop Data**: Avoid `DROP TABLE` or `DROP COLUMN` unless explicitly instructed, as this can cause data loss in the production environment.
*   **Use ALTER TABLE**: Use targeted `ALTER TABLE` statements to safely add what's missing.
    ```sql
    -- Example of safely adding a column:
    ALTER TABLE `orders` ADD `discount_value` DECIMAL(10,2) NULL DEFAULT '0.00' AFTER `total_amount`;
    ```
*   **Data Types**: Ensure data types align with the project standard. Currency should be `DECIMAL(10,2)`. Flags/Booleans should be `TINYINT(1)` (0 or 1).
*   **Document Adjustments**: Keep a clear log or generate a `.sql` diff file of any structural database changes made so they can be securely replicated on the live server.

## 3. Adapting Legacy Files to Current Standards
When moving a legacy file or an older deployment feature into the current workspace, you must refactor its core connections and conventions to match the active project:
*   **Update Database Connection**: Replace old standard `mysqli` or deprecated database connections with the active PDO standard:
    ```php
    // REPLACE OLD:
    // include 'db_connect.php';
    // $sql = mysqli_query($conn, "...");

    // WITH CURRENT STANDARD:
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    $db = new Database();
    $conn = $db->getConnection();
    // Use prepared statements (important!)
    ```
*   **Dependency Injection**: Replace any legacy authentication or header includes with the current system's components:
    ```php
    require_once '../../includes/auth.php';
    requireLogin(); // Active system standard
    ```
*   **Refactor SQL Injection Risks**: If the legacy file uses direct variable interpolation in queries (`SELECT * FROM table WHERE id = $id`), it MUST be immediately refactored to use PDO prepared statements with parameter binding before it goes live.

## 4. API & UI Consistency Checks
*   **JSON Response Translation**: If porting a legacy API that previously returned raw text or HTML, refactor it to return the project's standard JSON format (`['success' => true, 'data' => ...]`).
*   **Frontend Data Binding**: Ensure the frontend JS making the `fetch` calls correctly maps to the newly updated Database columns returning from the API. 
*   **Variable Consistency**: Standardize variable naming. For example, if legacy code uses `c_name` for customer name, map it or update it to match the prevailing DB column, like `customer_name`.

## 5. The Migration Workflow summary
1. **Analyze**: Read the ported file and list its required DB tables/columns.
2. **Compare**: Check the current DB schema. Do the tables/columns exist?
3. **Patch DB**: Write and execute safe `ALTER TABLE` statements to bridge the gap.
4. **Refactor Code**: Update DB connections, authentication includes, and prepared statements to the current project standard.
5. **Test**: Run the function locally to ensure the new file cleanly interfaces with the updated database schema.
