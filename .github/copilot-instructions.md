# AI Coding Agent Instructions for Contractor Dashboard

## Project Overview
This is a PHP-based contractor warranty management system with key features:
- User management with role-based access (ADM, SOP, CON, TEC)
- Warranty record creation and validation
- Model validation for HVAC equipment
- Audit logging
- Session management and security controls

## Architecture & Design Patterns
- MVC-like structure with Presenters, Services, and Repositories
- Front controller pattern in `public/index.php`
- Bootstrap script in `includes/bootstrap.php` for initialization
- Repository pattern for data access
- Service layer for business logic
- Presenter pattern for view/model interaction

## Key Patterns & Conventions
1. **Naming Conventions**:
   - PHP/SQL/JSON: snake_case
   - URLs & CSS: kebab-case 
   - JavaScript: camelCase
   - UI text in English, code comments in Spanish

2. **Database Schema**:
   - Tables use utf8mb4_unicode_ci collation
   - Strict foreign key constraints
   - Auto-uppercase triggers for addresses and names
   - Audit logs for tracking changes
   - Generated user IDs with role prefixes (A/S/C/T)

3. **Security Practices**:
   - CSRF protection on forms
   - Rate limiting for login attempts
   - Secure session handling
   - Password hashing (bcrypt)
   - XSS prevention via sanitize_string()

4. **File Structure**:
```
/app
  /Mail         - Email handling
  /Models       - Domain models
  /Presenters   - View/model coordination
  /Repositories - Data access
  /Services     - Business logic
  /Validators   - Input validation
/config         - Configuration files (.ini)
/database       - Migrations and schema
/includes       - Core bootstrap & utilities  
/public         - Web root
  /assets       - Static files
  /views        - PHP templates
/storage        - File storage (outside webroot)
```

## Development Workflow
1. **Configuration**:
   - Copy `config/app.example.ini` to `config/app.local.ini`
   - Configure database and app settings
   - Ensure storage directories have write permissions

2. **Database**:
   - Run migrations in order from `database/migrations/`
   - Check trigger creation for uppercase transformations
   - Verify model validation tables are populated

3. **Maintenance Mode**:
   - Create/remove `public/maintenance.flag` to toggle

## Common File Locations
- Auth Logic: `app/Services/auth_service.php`
- Session Management: `app/Repositories/session_repository.php`
- Error Handling: `includes/error_handler.php`
- Frontend Entry: `public/views/sign_in.php`

## Key Integration Points
1. **Database**:
   - Uses MySQL 5.7 with PDO
   - Connection managed in `includes/db.php`
   - Migrations in `database/migrations/`

2. **Authentication**:
   - Session tokens stored in cookies
   - Remember-me functionality
   - Rate limiting per IP address
   - Multi-role support (ADM/SOP/CON/TEC)

3. **File Storage**:
   - Certificates: `/storage/certificates/`
   - File Transfer: `/storage/file-transfer/`
   - Uploads: `/storage/uploads/`
   - Logs: `/storage/logs/`
   - Temp Files: `/storage/temp/`

## Error Handling
- Custom error handler in `includes/error_handler.php`
- Notices/Warnings logged but don't halt execution
- Fatal errors trigger 500 response
- All errors logged to `storage/logs/error.log`