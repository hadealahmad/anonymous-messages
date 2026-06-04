# Anonymous Messages Plugin Documentation Directory

Welcome to the technical documentation for the **Anonymous Messages** WordPress plugin. This documentation provides a comprehensive analysis of the plugin's architecture, security controls, standards alignment, and optimization areas.

## Document Index

1. **[System Architecture](file:///run/media/hadi/SSD2/Coding/AskAnonWP/documentation/architecture.md)**
   *   High-level data flow diagrams (Mermaid).
   *   Functional components and class responsibilities.
   *   Custom Post Type database models and taxonomy schemas.
   *   Block Integration, InnerBlocks layout, and frontend AJAX handlers.

---

## Technical Overview

The **Anonymous Messages** plugin is a Gutenberg-native application allowing website visitors to submit anonymous inquiries, questions, or feedback directly from the frontend. Administrators and designated users can manage, moderate, and answer these submissions from the WordPress dashboard.

### Code codebase Entry Points
*   **Main Plugin bootstrap:** [anonymous-messages.php](file:///run/media/hadi/SSD2/Coding/AskAnonWP/anonymous-messages.php)
*   **Database abstraction layer:** [includes/class-database.php](file:///run/media/hadi/SSD2/Coding/AskAnonWP/includes/class-database.php)
*   **Admin Dashboard Controllers:** [includes/class-admin.php](file:///run/media/hadi/SSD2/Coding/AskAnonWP/includes/class-admin.php)
*   **Gutenberg Block Registration:** [includes/class-gutenberg-block.php](file:///run/media/hadi/SSD2/Coding/AskAnonWP/includes/class-gutenberg-block.php)
*   **AJAX Processing & Validation:** [includes/class-ajax-handler.php](file:///run/media/hadi/SSD2/Coding/AskAnonWP/includes/class-ajax-handler.php)
*   **Security Policies:** [includes/class-security.php](file:///run/media/hadi/SSD2/Coding/AskAnonWP/includes/class-security.php)
*   **Data Migration Pipeline:** [includes/class-migration.php](file:///run/media/hadi/SSD2/Coding/AskAnonWP/includes/class-migration.php)
