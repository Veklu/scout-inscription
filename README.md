[Lire en francais](README.fr.md)

# Scout Inscription

A WordPress plugin for managing online registrations for a Scouts du Canada group. Features a multi-step registration form, medical records, payment tracking, QR code verification, and full compliance with Quebec's Loi 25 (privacy law).

## Features

- **5-step registration form** — Child info, medical form, risk acceptance, consents, confirmation
- **Family portal** — Parents can view and manage their registrations
- **Payment tracking** — Track deposits and full payments per registration
- **QR code verification** — HMAC-SHA256 signed QR codes for secure identity verification
- **PDF generation** — Medical forms, risk acceptance, and summary documents
- **Email notifications** — Confirmation emails with QR codes and daily digest for admins
- **MFA support** — Multi-factor authentication for admin access to sensitive data
- **Data encryption** — At-rest encryption for sensitive personal information
- **Access logging** — Full audit trail for data access (Loi 25 compliance)
- **CSV export** — Export registrations with role-based field access

## Installation

1. Compress the `scout-inscription` folder as a `.zip`
2. WordPress: Plugins > Add New > Upload Plugin
3. Activate the plugin

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[scout_inscription]` | Displays the registration form |
| `[scout_famille]` | Family portal for viewing registrations |
| `[scout_verify]` | QR code verification page |

## Roles & Capabilities

The plugin creates two custom roles:
- **Animateur scout** — Can view registrations for their unit
- **Tresorier scout** — Can manage payments and export financial data

## Internationalization

The plugin supports French (default) and English. To switch language, set your WordPress locale via Settings > General > Site Language.

## License

[MIT](LICENSE)
