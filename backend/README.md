# Portfolio Backend System

A complete PHP/MySQL backend system for managing your portfolio website. Compatible with Hostinger and other shared hosting providers.

## Features

- **Dynamic Page Management** - Add, edit, delete pages with SEO optimization
- **Portfolio Management** - CRUD operations for portfolio projects with categories and tags
- **Services Management** - Manage service packages with pricing and features
- **Blog System** - Full blog with categories, tags, and SEO fields
- **Testimonials** - Client testimonials with ratings and photos
- **Forms & Leads** - Handle contact forms, newsletter signups, and lead generation
- **Media Library** - Upload and manage images, videos, and documents
- **User Management** - Admin and editor roles with permissions
- **Email Integration** - SMTP email notifications and automated responses
- **Security** - CSRF protection, XSS prevention, SQL injection protection
- **RESTful API** - Clean API endpoints for all functionality

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or MariaDB 10.2+
- Apache with mod_rewrite (or Nginx)
- Composer (for dependencies)

## Installation

### 1. Upload Files

Upload all backend files to your hosting account:
```
/backend/
├── api/
├── admin/
├── classes/
├── config/
├── database/
├── uploads/
└── ...
```

### 2. Configure Environment

1. Copy `.env.example` to `.env`
2. Edit `.env` with your database and email settings:

```env
DB_HOST=localhost
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASS=your_database_password

SMTP_HOST=smtp.hostinger.com
SMTP_USERNAME=noreply@yourdomain.com
SMTP_PASSWORD=your_email_password
```

### 3. Run Installation

Visit `yourdomain.com/backend/install.php` in your browser to:
- Check system requirements
- Create database tables
- Install dependencies
- Set up initial configuration

### 4. Access Admin Panel

1. Go to `yourdomain.com/backend/admin/login.php`
2. Login with default credentials:
   - Email: `admin@adilgfx.com`
   - Password: `admin123`
3. **⚠️ Change the default password immediately!**

## Hostinger Deployment

### Database Setup

1. Create a MySQL database in Hostinger control panel
2. Note your database credentials:
   - Host: usually `localhost`
   - Database name: `u123456789_portfolio`
   - Username: `u123456789_admin`
   - Password: (your chosen password)

### File Upload

1. Use File Manager or FTP to upload files
2. Place backend files in `/public_html/backend/`
3. Set permissions for uploads folder: `755`

### Email Configuration

For Hostinger SMTP:
```env
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=587
SMTP_USERNAME=noreply@yourdomain.com
SMTP_PASSWORD=your_email_password
```

## API Endpoints

### Authentication
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout
- `GET /api/auth/me` - Get current user info

### Portfolio
- `GET /api/portfolio` - Get all portfolio items
- `GET /api/portfolio/{id}` - Get single portfolio item
- `POST /api/portfolio` - Create portfolio item
- `PUT /api/portfolio/{id}` - Update portfolio item
- `DELETE /api/portfolio/{id}` - Delete portfolio item

### Services
- `GET /api/services` - Get all services
- `GET /api/services/{id}` - Get single service
- `POST /api/services` - Create service
- `PUT /api/services/{id}` - Update service

### Blog
- `GET /api/blog` - Get all blog posts
- `GET /api/blog/{slug}` - Get blog post by slug
- `POST /api/blog` - Create blog post
- `PUT /api/blog/{id}` - Update blog post

### Forms
- `POST /api/forms/submit` - Submit contact form
- `POST /api/forms/newsletter` - Newsletter signup
- `GET /api/forms/submissions` - Get form submissions (admin)

### Media
- `POST /api/media/upload` - Upload file
- `GET /api/media` - Get media library
- `PUT /api/media/{id}` - Update media info
- `DELETE /api/media/{id}` - Delete media file

## Frontend Integration

### JavaScript Example

```javascript
// Submit contact form
async function submitContactForm(formData) {
    const response = await fetch('/backend/api/forms/submit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            form_type: 'contact',
            ...formData
        })
    });
    
    return await response.json();
}

// Get portfolio items
async function getPortfolio() {
    const response = await fetch('/backend/api/portfolio');
    const data = await response.json();
    return data.success ? data.data : [];
}
```

### React Integration

```jsx
// Portfolio component
import { useState, useEffect } from 'react';

function Portfolio() {
    const [items, setItems] = useState([]);
    
    useEffect(() => {
        fetch('/backend/api/portfolio')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    setItems(data.data);
                }
            });
    }, []);
    
    return (
        <div>
            {items.map(item => (
                <div key={item.id}>
                    <h3>{item.title}</h3>
                    <p>{item.description}</p>
                </div>
            ))}
        </div>
    );
}
```

## Security Features

- **CSRF Protection** - All forms protected with CSRF tokens
- **XSS Prevention** - Input sanitization and output encoding
- **SQL Injection Protection** - Prepared statements throughout
- **File Upload Security** - Type validation and secure storage
- **Session Management** - Secure session handling
- **Role-based Access** - Admin and editor permissions

## File Structure

```
backend/
├── api/                    # API endpoints
│   ├── index.php          # Main router
│   ├── auth.php           # Authentication
│   ├── portfolio.php      # Portfolio API
│   ├── services.php       # Services API
│   ├── blog.php           # Blog API
│   ├── forms.php          # Forms API
│   └── media.php          # Media API
├── admin/                 # Admin panel
│   ├── index.php          # Dashboard
│   ├── login.php          # Login page
│   ├── portfolio.php      # Portfolio management
│   └── ...
├── classes/               # PHP classes
│   ├── Auth.php           # Authentication
│   ├── Portfolio.php      # Portfolio management
│   ├── Services.php       # Services management
│   ├── Blog.php           # Blog management
│   ├── Forms.php          # Forms handling
│   ├── Media.php          # Media management
│   └── Email.php          # Email functionality
├── config/                # Configuration
│   ├── config.php         # App configuration
│   └── database.php       # Database connection
├── database/              # Database files
│   └── schema.sql         # Database schema
├── uploads/               # File uploads
└── vendor/                # Composer dependencies
```

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check database credentials in `.env`
   - Ensure database exists and user has permissions

2. **File Upload Errors**
   - Check uploads folder permissions (755)
   - Verify PHP upload limits

3. **Email Not Sending**
   - Verify SMTP credentials
   - Check hosting provider email restrictions

4. **API Endpoints Not Working**
   - Ensure mod_rewrite is enabled
   - Check `.htaccess` file exists

### Debug Mode

Enable debug mode in `.env`:
```env
APP_ENV=development
```

This will show detailed error messages.

## Support

For support and customization:
- Email: hello@adilgfx.com
- Documentation: Check inline code comments
- Issues: Review error logs in hosting control panel

## License

This backend system is proprietary software created for the Adil GFX portfolio website.