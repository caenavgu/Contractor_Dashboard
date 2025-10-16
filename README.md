# Contractor App Skeleton (MVP-ready)

Minimal project skeleton aligned to your conventions.

## Conventions
- UI in **English**, comments in **Spanish**.
- PHP/SQL/JSON: **snake_case**.
- URLs & CSS (classes/ids/vars): **kebab-case**.
- JavaScript (vars/functions): **camelCase**.

## Structure
- `/public/` webroot only.
- `/storage/` outside web access (certificates, file-transfer, uploads, logs, temp) — **no subfolders** inside those three for now.

## Maintenance
- Toggle maintenance by creating/removing the flag: `public/maintenance.flag` (empty file).
