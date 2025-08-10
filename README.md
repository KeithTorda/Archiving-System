# Atok Elementary School Digital Archiving System

A modern, secure, and responsive digital archiving system built with PHP, Bootstrap 5, and MySQL for Atok Elementary School.

## 🚀 Features

### 🔐 Security
- Session-based authentication with bcrypt password hashing
- Role-based access control (Admin, School Head, Registrar)
- CSRF protection on all forms
- Input sanitization and validation
- Prepared statements to prevent SQL injection
- Secure file upload handling

### 📁 Modules
- **Student Records**: Upload and manage report cards, Form 137, enrollment forms
- **Personnel Records**: Manage staff files (201 File, appointments, evaluations)
- **School Forms**: Organize DepEd forms (SF1, SF2, SF5, SF10)
- **User Management**: Manage users and view access logs
- **Archive & Backup**: Export records and create system backups

### 🎨 Modern UI
- Bootstrap 5 responsive design
- Modern sidebar layout with collapsible navigation
- Clean, professional interface
- Mobile-friendly design
- Interactive data tables with search and filtering

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP (recommended for local development)

## 🛠️ Installation

### 1. Database Setup
1. Open phpMyAdmin in your browser
2. Create a new database named `atok_archiving_system`
3. Import the database schema from `database/schema.sql`

### 2. Configuration
1. Open `includes/config.php`
2. Update database credentials if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'atok_archiving_system');
   ```
3. Update the site URL:
   ```php
   define('SITE_URL', 'http://localhost/archivingsystem');
   ```

### 3. File Permissions
Ensure the upload directory is writable:
```bash
chmod 755 uploads/
chmod 755 uploads/students/
chmod 755 uploads/personnel/
chmod 755 uploads/school-forms/
```

### 4. Default Login
- **Username**: admin
- **Password**: password
- **Role**: Administrator

⚠️ **Important**: Change the default password after first login!

## 📁 File Structure

```
archivingsystem/
├── includes/           # Core PHP files
│   ├── config.php     # Configuration
│   ├── database.php   # Database connection
│   ├── auth.php       # Authentication
│   └── functions.php  # Utility functions
├── pages/             # Application pages
│   ├── login.php      # Login page
│   ├── dashboard.php  # Main dashboard
│   ├── students.php   # Student records
│   ├── personnel.php  # Personnel records
│   └── ...
├── uploads/           # File storage
│   ├── students/      # Student records
│   ├── personnel/     # Personnel records
│   └── school-forms/  # School forms
├── database/          # Database files
│   └── schema.sql     # Database schema
└── assets/            # Static assets
    ├── css/           # Stylesheets
    ├── js/            # JavaScript
    └── images/        # Images
```

## 👥 User Roles

### Administrator
- Full system access
- User management
- System backup and restore
- All CRUD operations

### School Head
- View-only access to all records
- Generate reports
- View activity logs

### Registrar/Encoder
- Upload student and personnel records
- View records
- Basic search and filter

## 🔒 Security Features

### Authentication
- Secure session management
- Password hashing with bcrypt
- Session timeout protection
- CSRF token validation

### File Upload Security
- File type validation
- File size limits
- Secure file naming
- Directory traversal protection

### Database Security
- Prepared statements
- Input sanitization
- SQL injection prevention
- Access control

## 📊 Features Overview

### Student Records Module
- Upload report cards, Form 137, enrollment forms
- Search by name, LRN, grade level, school year
- Organized file storage by student and year
- Download tracking and logging

### Personnel Records Module
- Manage staff documents (201 File, appointments, evaluations)
- Filter by position, status, years of service
- Secure document storage

### School Forms Module
- Upload and categorize DepEd forms
- Organize by school year and grade level
- Version control and tracking

### User Management
- Create and manage user accounts
- Role assignment and permissions
- Activity logging and monitoring
- Access control

### Archive & Backup
- Export records by year
- Create system backups
- Download logs and statistics
- Data integrity verification

## 🎨 UI Components

### Bootstrap 5 Features
- Responsive grid system
- Modern card components
- Interactive tables with search
- Modal dialogs for forms
- Alert notifications
- Progress indicators

### Custom Styling
- Modern gradient backgrounds
- Smooth animations and transitions
- Professional color scheme
- Mobile-first design
- Accessibility features

## 🔧 Customization

### Colors
Update CSS variables in any page:
```css
:root {
    --primary-color: #0d6efd;
    --secondary-color: #6c757d;
    --success-color: #198754;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #0dcaf0;
}
```

### File Upload Limits
Modify in `includes/config.php`:
```php
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);
```

## 📝 Logging

The system logs all activities:
- User logins/logouts
- File uploads/downloads
- Record modifications
- System backups
- Access attempts

Logs are stored in the `activity_logs` and `download_logs` tables.

## 🚀 Performance

### Optimization Features
- Database indexing for fast queries
- Pagination for large datasets
- Efficient file handling
- Minimal CSS/JS loading
- CDN resources for Bootstrap

### Caching
- Session-based caching
- Database query optimization
- File system caching

## 🔧 Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check database credentials in `config.php`
   - Ensure MySQL service is running
   - Verify database exists

2. **File Upload Errors**
   - Check file permissions on upload directory
   - Verify file size limits in PHP configuration
   - Ensure allowed file types

3. **Session Issues**
   - Check PHP session configuration
   - Verify session storage permissions
   - Clear browser cookies

4. **Permission Denied**
   - Set proper file permissions (755 for directories, 644 for files)
   - Check web server user permissions
   - Verify .htaccess configuration

## 📞 Support

For technical support or feature requests, please contact the system administrator.

## 📄 License

This system is developed for Atok Elementary School. All rights reserved.

---

**Version**: 1.0.0  
**Last Updated**: December 2024  
**Developed By**: Digital Archiving System Team 