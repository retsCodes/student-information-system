# Student Information System

A comprehensive web-based Student Information System built with PHP, MySQL, and Bootstrap. This system provides role-based access for administrators, cashiers, and students to manage academic information and payments.

## Features

### General Features
- **Role-based Authentication**: Admin, Cashier, and Student roles
- **Security Features**: CSRF protection, session timeout, login attempt lockdown
- **Theme Toggle**: Dark/Light mode support
- **Responsive Design**: Bootstrap-powered responsive UI
- **Password Security**: Bcrypt password hashing

### Admin Features
- **Dashboard**: Overview of system statistics and recent activities
- **User Management**: Create, view, and manage user accounts
- **Payment Management**: View and modify all student payments
- **Subject Management**: Create and manage academic subjects
- **Section Management**: Organize students into sections
- **Activity Logs**: Track all system activities
- **Backup System**: Data backup functionality

### Student Features
- **Dashboard**: Personal overview with payment status and subjects
- **Payment History**: View all payments with detailed information
- **Schedule View**: Current subjects and sections
- **Profile Management**: Update personal information and password

### Cashier Features
- **Dashboard**: Payment-focused overview and quick actions
- **Payment Processing**: Process student payments with receipt generation
- **Audit Log**: Track cashier-specific activities
- **Student Search**: Quick student lookup functionality

## Installation

### Prerequisites
- **XAMPP** (Apache, MySQL, PHP 8.0+)
- Web browser (Chrome, Firefox, Safari, Edge)

### Setup Instructions

1. **Clone/Download the Project**
   ```
   Place the project folder in: C:\xampp\htdocs\student's-information-system
   ```

2. **Start XAMPP Services**
   - Start Apache and MySQL services from XAMPP Control Panel

3. **Create Database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the `database_setup.sql` file or run the SQL commands manually

4. **Configure Database Connection**
   - Open `init.php`
   - Verify database configuration:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'student_info_tracker');
     ```

5. **Access the System**
   - Open: http://localhost/student's-information-system
   - Default admin login:
     - **Username**: ADMIN001
     - **Password**: admin123

## Database Schema

### Core Tables
- **users**: Main user accounts and authentication
- **students_info**: Student-specific information
- **employee_info**: Staff/employee information
- **subjects**: Academic subjects
- **sections**: Class sections and enrollment
- **payments**: Student payment records
- **activity_logs**: System activity tracking
- **login_attempts**: Security tracking

## File Structure

```
student's-information-system/
├── admin/
│   ├── dashboard.php
│   └── manage_users.php
├── cashier/
│   ├── dashboard.php
│   └── payments.php
├── student/
│   ├── dashboard.php
│   ├── payments.php
│   └── profile.php
├── database_setup.sql
├── init.php
├── theme.php
├── layout.php
├── login.php
├── logout.php
├── index.php
├── unauthorized.php
└── README.md
```

## Security Features

### Authentication
- **Session Management**: 30-minute timeout
- **Login Protection**: 5 failed attempts = 15-minute lockout
- **Password Hashing**: Bcrypt encryption
- **CSRF Protection**: Token validation on forms

### Access Control
- **Role-based Access**: Strict role separation
- **Session Validation**: Automatic logout on timeout
- **SQL Injection Protection**: Prepared statements

## User Roles & Permissions

### Administrator
- Full system access
- User management (create, edit, lock accounts)
- Payment oversight and modification
- Subject and section management
- System logs and backup access

### Cashier
- Payment processing and management
- Student payment history access
- Receipt generation
- Personal audit log access

### Student
- Personal information management
- Payment history viewing
- Schedule and subject information
- Profile updates (limited fields)

## Default Accounts

### Admin Account
- **User ID**: ADMIN001
- **Email**: admin@system.com
- **Password**: admin123
- **Role**: Administrator

## Configuration

### Session Settings
```php
define('SESSION_TIMEOUT', 1800);     // 30 minutes
define('MAX_LOGIN_ATTEMPTS', 5);     // 5 attempts
define('LOCKOUT_TIME', 900);         // 15 minutes
```

### Database Settings
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'student_info_tracker');
```

## Theme System

The system includes a dynamic theme toggle:
- **Light Mode**: Default Bootstrap theme
- **Dark Mode**: Custom dark theme with proper contrast
- **Persistent**: Theme preference saved in session
- **Accessible**: Fixed toggle button in bottom-right corner

## Development Notes

### Code Structure
- **Simple over Efficiency**: All CSS and PHP logic in the same files
- **College-level Complexity**: Easy-to-understand code patterns
- **Bootstrap Integration**: Consistent UI components
- **Error Handling**: User-friendly error messages

### Security Implementation
- No "required" HTML attributes - server-side validation only
- CSRF tokens on all forms
- SQL injection prevention via prepared statements
- Password complexity enforcement

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Verify XAMPP MySQL is running
   - Check database credentials in `init.php`
   - Ensure database exists

2. **Permission Denied**
   - Check file permissions
   - Verify Apache has read access to files

3. **Session Issues**
   - Clear browser cache and cookies
   - Check PHP session configuration

4. **Login Problems**
   - Verify default admin credentials
   - Check for account lockouts in `login_attempts` table

## Future Enhancements

- AJAX-powered search functionality
- Email notifications
- Report generation
- Advanced backup/restore features
- Mobile app integration
- Payment gateway integration

## Support

For technical support or questions:
1. Check the troubleshooting section
2. Review error logs in XAMPP
3. Verify database connectivity
4. Check PHP error logs

## License

This project is developed for educational purposes. Feel free to modify and use according to your institution's needs.
