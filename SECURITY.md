# Security Policy

## Supported Versions

Use this section to tell people about which versions of your project are
currently being supported with security updates.

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

We take security vulnerabilities seriously. If you discover a security vulnerability within this project, please report it to us as described below.

### How to Report

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report them via email to: [your-email@example.com]

### What to Include

When reporting a vulnerability, please include:

1. **Description**: A clear description of the vulnerability
2. **Steps to Reproduce**: Detailed steps to reproduce the issue
3. **Impact**: Potential impact of the vulnerability
4. **Environment**: PHP version, Laravel version, and other relevant details
5. **Suggested Fix**: If you have ideas for how to fix the issue

### Response Timeline

- **Acknowledgment**: We will acknowledge receipt of your report within 48 hours
- **Initial Assessment**: We will provide an initial assessment within 5 business days
- **Resolution**: We will work to resolve the issue as quickly as possible

### Security Best Practices

When using this application, please follow these security best practices:

1. **Environment Configuration**
   - Never commit `.env` files to version control
   - Use strong database passwords
   - Keep your Laravel application key secure

2. **File Uploads**
   - Only upload CSV files as intended
   - The application validates file types and sizes
   - Temporary files are automatically cleaned up

3. **Data Handling**
   - Sensitive data is stored in temporary files, not in the database
   - Session data is limited to prevent payload overflow
   - Files are automatically cleaned up after use

4. **Access Control**
   - Ensure proper web server configuration
   - Use HTTPS in production
   - Implement proper authentication if needed

### Known Security Considerations

1. **Session Storage**: The application uses file-based session storage for large datasets to avoid database payload limits
2. **File Uploads**: CSV files are validated but ensure your server has proper file upload restrictions
3. **Temporary Files**: Files are stored in `storage/app/temp/` and should be cleaned up regularly

### Security Updates

Security updates will be released as needed. Please keep your installation updated to the latest version.

### Contact

For security-related questions or concerns, please contact:
- Email: [your-email@example.com]
- GitHub: [@your-username](https://github.com/your-username)

Thank you for helping keep this project secure!
