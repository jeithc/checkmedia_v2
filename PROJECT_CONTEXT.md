# CheckMedia V2 - Project Context

## Overview
CheckMedia V2 is an Out-of-Home (OOH) advertising audit system. It allows administrators to manage advertising spaces ("Espacios"), commercial bookings ("Pautas"), and assign auditors to verify the condition and correct display of these ads in the field.

## Technology Stack
- **Language**: PHP 8.4
- **Framework**: Laravel (v12.0-dev/latest)
- **Admin Panel**: Orchid Platform (v14.52)
- **Frontend/Interactivity**: Livewire (v3.7)
- **Database**: MySQL/MariaDB
- **Styling**: Bootstrap (via Orchid) / Tailwind (if configured)

## Key Domain Concepts

### 1. Advertising Space (Espacio Publicitario)
- **Model**: `App\Models\AdvertisingSpace`
- **Description**: Physical location (Billboard, Mupi, Bus Stop) where ads are displayed.
- **Key Attributes**: `external_code` (Unique ID), `provider`, `city`, `location`, `category`.

### 2. Audit (Auditoría)
- **Model**: `App\Models\Audit`
- **Description**: A verification record created by a user (auditor) for a specific space in a specific week.
- **Key Attributes**: `year`, `week`, `general_status` ('good', 'bad', 'acceptable'), `observation`, `photos`.
- **Logic**: Audits are unique per Space + Year + Week.

### 3. Audit Criteria (Criterios de Auditoría)
- **Model**: `App\Models\AuditCriterion`
- **Description**: Configurable items to check during an audit (e.g., "Illumination", "Cleaning", "Structure").
- **Key Attributes**: `name`, `key`, `type` (boolean, scale, text), `is_active`.

### 4. Commercial Booking (Pauta Comercial)
- **Model**: `App\Models\CommercialBooking`
- **Description**: The expected ad content for a space during a specific period.
- **Key Attributes**: `client_name`, `product_name`, `contract_code`.

## Architecture & Directory Structure

### Orchid Admin Panel (`app/Orchid`)
- **Screens** (`app/Orchid/Screens`): Controllers for Admin pages.
    - Defined with `query`, `command`, and `layout` methods.
- **Layouts** (`app/Orchid/Layouts`): View definitions (Tables, Forms, Listeners).
- **PlatformProvider** (`app/Orchid/PlatformProvider.php`): Menu and Permission registration.

### Livewire Components (`app/Livewire`)
- Used for complex interactive forms, like the `AuditForm` used by mobile auditors.
- **Path**: `app/Livewire/AuditForm.php` (The main auditing interface).

### Database
- **Migrations**: `database/migrations`
- **Core Tables**: `advertising_spaces`, `audits`, `audit_values`, `audit_criteria`.

## Development Guidelines
1.  **Orchid for Admin**: Use Orchid Screens/Layouts for back-office CRUD.
2.  **Livewire for Front**: Use Livewire for the auditor-facing mobile/web views.
3.  **Strict Typing**: Use PHP strict types where possible.
4.  **Permissions**: Register permissions in `PlatformProvider` and check them in Screens/Controllers.

## Recent Changes & Focus
- **Audit Criteria Management**: Currently implementing CRUD for `AuditCriterion` in the Admin Panel.
- **Audit Screen**: Recently updated `AuditForm` for better validation and photo handling.
