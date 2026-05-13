---
name: Ella POS Project Standards
description: Comprehensive conventions and best practices for building consistent, modern, and performant features in Ella POS.
---

# Ella POS Development Guidelines

Welcome to the Ella POS development standards! This document outlines the best practices, design patterns, and coding conventions established throughout the project to ensure consistency, high performance, and an excellent user experience. 

When creating or modifying features in this project, **always adhere to these rules**:

## 1. Directory Structure & Architecture

*   **`views/`**: Contains the frontend PHP pages (`.php`). These pages handle layout, styling, and client-side logic.
*   **`api/`**: Contains the backend endpoints (`.php`). These must *only* return JSON (or specific output forms like CSV) and contain no HTML.
*   **`config/`**: Contains core configurations like `database.php` and `config.php`.
*   **`includes/`**: Reusable PHP components like `auth.php`, `header.php`, `sidebar.php`, `footer.php`.
*   **`.agent/skills/`**: Stores Agent Skills (like this one) to maintain standard knowledge.

## 2. API Design & Backend Best Practices

APIs in Ella POS are designed to be lightweight, secure, and asynchronous.

*   **Header & Output**: Always set `header("Content-Type: application/json");` at the top of API files (unless exporting files like CSV).
*   **Access Control**: Always include authentication and authorization checks at the top of the file:
    ```php
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../includes/auth.php';
    
    requireLogin();
    
    // Example Role Check
    if ($_SESSION['role'] !== 'admin' && !hasPermission('specific_permission')) {
        echo json_encode(['success' => false, 'error' => 'Permission denied']);
        exit;
    }
    ```
*   **Database Access**: Use PDO via the `Database` class. *Always* use prepared statements to prevent SQL injection:
    ```php
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT * FROM table WHERE id = ?");
    $stmt->execute([$id]);
    ```
*   **Safety Over Deletion (Soft-Delete)**: For major entities (Users, Products, etc.), avoid hard `DELETE` queries. Instead, implement a soft-delete mechanism by setting a `status` to `'inactive'` or `'archived'`. This preserves historical data and prevents foreign key constraint violations.
*   **Granular Permissions**: Shift away from broad role checks (`admin`, `staff`) towards specific permission checks using `hasPermission('specific_action_name')` or `requirePermission('module_access')` to allow managed flexibility.
*   **Response Format**: Always return a consistent JSON structure:
    *   **Success**: `echo json_encode(['success' => true, 'data' => $data, 'message' => 'Optional']);`
    *   **Error**: `echo json_encode(['success' => false, 'error' => 'Error message']);`
*   **Error Handling**: Wrap logic in `try ... catch (Exception $e)` blocks. On error, optionally set HTTP response codes (`http_response_code(500)`) and return the error message in the standard JSON format.

## 3. UI/UX Design & Frontend Best Practices

Ella POS emphasizes a modern, "wow-factor" aesthetic that feels premium, fast, and highly responsive.

*   **Responsive Layouts**: 
    *   Use Bootstrap 5 grid (`container-fluid`, `row`, `col-*`) for standard structuring.
    *   **Critical Pattern for Tables**: For heavy data (like records), implement a dual-view approach. Create a `.desktop-table` for large screens and a `.mobile-cards` container for mobile screens. Toggle their display based on screen width (`@media (max-width: 991.98px)`).
*   **Styling & Micro-animations**: 
    *   Do not use generic unstyled HTML elements.
    *   Cards should have `border-0`, `shadow-sm`, and rounded corners (e.g., `border-radius: 12px`).
    *   Add hover effects for interactivity (e.g., `transform: translateY(-2px); box-shadow: ...; transition: all 0.3s ease;`).
    *   Use subtle gradients for active states or headers (e.g., `background: linear-gradient(135deg, #f0f4ff 0%, #e8f0fe 100%);`).
*   **Empty States & Loading**:
    *   Always design an "Empty State" when no data is available (centered, muted text, large faded FontAwesome icon).
    *   Show a `loading-overlay` (spinner + semi-transparent background) when performing async `fetch` operations to prevent duplicate submissions and indicate progress.
*   **Icons**: Use FontAwesome 6 icons (`fa-solid fa-*`) liberally to add visual cues to buttons, headers, and list items. Color-code icons for context (`text-primary`, `text-success`, `text-warning`, `text-danger`).
*   **Notifications**: Do not use standard JS `alert()`. Use the built-in toast notification system: 
    *   `EllaToast.success('Message')`
    *   `EllaToast.error('Message')`
    *   `EllaToast.warning('Message')`
*   **Information Management**: Avoid cluttering tables with long text fields (e.g., transaction notes, comments). Use a unified "View Comments" modal linked to a single "View" button in the table to display such detailed information.

## 4. Client-Side JavaScript Conventions

*   **Fetch API Options**: Use modern async/await syntax with the `fetch()` API.
    ```javascript
    async function loadData() {
        try {
            const res = await fetch(`../../api/module/endpoint.php?param=value`);
            const data = await res.json();
            if (data.success) {
                // Render UI
            } else {
                EllaToast.error(data.error);
            }
        } catch (err) {
            EllaToast.error('Network error');
        }
    }
    ```
*   **Dynamic UI Construction**: Do not reload the page. Rebuild the DOM dynamically by appending HTML strings or elements. Prevent XSS by escaping user-supplied data (use utility functions like `escapeHtml()`).
*   **URL State Management**: If the user filters data, update the browser URL seamlessly using `window.history.replaceState` so that link sharing and page refreshes maintain the context.
*   **Performance (Debouncing & Throttling)**: For rapid actions (like adding to cart, or typing in search), implement debouncing to prevent UI lag and excessive API calls.
*   **Currency Precision**: To avoid floating-point errors (e.g., `0.1 + 0.2 !== 0.3`), always round currency calculations to 2 decimal places using `Math.round(total * 100) / 100` before performing equality checks in checkout or payment logic.

## 5. Typical Page Structure (Views)

A standard view page (`.php`) should mirror this sequence:
1. `<?php` Config, DB, Auth includes & Session Checks. Load initial simple data if necessary. `?>`
2. Embedded `<style>` for custom UI tweaks, hover effects, and responsive overrides.
3. `<div class="container-fluid">` main wrapper.
4. **Header Section**: Page Title, description, and action buttons.
5. **Filters Section**: Card with forms, inputs, and a submit button.
6. **Data Presentation**: Cards, Tables, empty states, and loading overlays.
7. `<script>` tag: Variable initializations, `DOMContentLoaded` listeners, async fetch functions, DOM rendering logic.
8. Footer include.

By following these conventions, the Ella POS codebase remains maintainable, secure, visually stunning, and highly performant.

## 6. POS Logic & Operations Standards

*   **Receipt Handling**: The default receipt paper size should be set to A4 for standard records.
*   **Flexible Payments (Pay Later)**: Provide standardized due date options for credit-based transactions (e.g., 1 month, 45 days) to simplify management.
*   **Transaction Integrity**: Ensure that exact payments are correctly processed by applying the currency rounding rules mentioned in the JavaScript section above.
