# Contributing to Sentiment Analysis Project

Thank you for your interest in contributing to this sentiment analysis project! This document provides guidelines for contributing to the project.

## 🚀 Getting Started

### Prerequisites
- PHP 8.1+
- Composer
- MySQL
- Git

### Development Setup
1. Fork the repository
2. Clone your fork: `git clone https://github.com/your-username/skripsi-sentiment-analysis.git`
3. Install dependencies: `composer install`
4. Copy environment file: `cp .env.example .env`
5. Generate app key: `php artisan key:generate`
6. Configure database in `.env`
7. Run migrations: `php artisan migrate`

## 📝 How to Contribute

### Reporting Issues
- Use the issue template
- Provide clear description
- Include steps to reproduce
- Add screenshots if applicable

### Suggesting Features
- Check existing issues first
- Provide detailed description
- Explain the use case
- Consider implementation complexity

### Code Contributions

#### Branch Naming
- `feature/description` - New features
- `bugfix/description` - Bug fixes
- `docs/description` - Documentation updates
- `refactor/description` - Code refactoring

#### Commit Messages
Use clear, descriptive commit messages:
```
feat: add confusion matrix visualization
fix: resolve session payload error
docs: update README with installation steps
refactor: improve auto labeling algorithm
```

#### Code Style
- Follow PSR-12 coding standards
- Use meaningful variable names
- Add comments for complex logic
- Keep functions small and focused

#### Testing
- Test your changes thoroughly
- Ensure no breaking changes
- Test with different data sizes
- Verify UI responsiveness

## 🏗️ Project Structure

### Controllers
- `PreprocessingController.php` - Data preprocessing logic
- `LabellingController.php` - Auto and manual labelling
- `ClassificationController.php` - SVM classification
- `EvaluationController.php` - Model evaluation

### Views
- Blade templates in `resources/views/`
- Responsive design with CSS Grid/Flexbox
- Interactive JavaScript for visualizations

### Storage
- Temporary files in `storage/app/temp/`
- Uploaded files in respective directories
- Clean up temporary files after use

## 🧪 Testing Guidelines

### Manual Testing
1. Test with small datasets (10-50 samples)
2. Test with large datasets (1000+ samples)
3. Test edge cases (empty data, special characters)
4. Test UI responsiveness on different devices

### Data Testing
- Use sample CSV files
- Test different column formats
- Verify data integrity
- Check file upload limits

## 📋 Pull Request Process

1. **Create Feature Branch**
   ```bash
   git checkout -b feature/amazing-feature
   ```

2. **Make Changes**
   - Write clean, documented code
   - Test thoroughly
   - Update documentation if needed

3. **Commit Changes**
   ```bash
   git add .
   git commit -m "feat: add amazing feature"
   ```

4. **Push to Branch**
   ```bash
   git push origin feature/amazing-feature
   ```

5. **Create Pull Request**
   - Use descriptive title
   - Provide detailed description
   - Link related issues
   - Add screenshots if UI changes

## 🎯 Areas for Contribution

### High Priority
- Performance optimization
- Error handling improvements
- Mobile UI enhancements
- Additional evaluation metrics

### Medium Priority
- Code refactoring
- Documentation improvements
- Test coverage
- Accessibility features

### Low Priority
- UI/UX enhancements
- Additional visualizations
- Export formats
- Configuration options

## 🐛 Bug Reports

When reporting bugs, please include:

1. **Environment**
   - PHP version
   - Laravel version
   - Browser and version
   - Operating system

2. **Steps to Reproduce**
   - Clear, numbered steps
   - Sample data if applicable
   - Expected vs actual behavior

3. **Additional Context**
   - Screenshots
   - Error messages
   - Log files
   - Related issues

## 💡 Feature Requests

For feature requests, please provide:

1. **Problem Description**
   - What problem does this solve?
   - Who would benefit from this feature?

2. **Proposed Solution**
   - How should it work?
   - Any design considerations?

3. **Alternatives Considered**
   - Other approaches you've thought about
   - Why this solution is preferred

## 📚 Documentation

### Code Documentation
- Use PHPDoc comments
- Document complex algorithms
- Explain business logic
- Include examples

### User Documentation
- Update README for new features
- Add screenshots for UI changes
- Provide usage examples
- Document configuration options

## 🔒 Security

- Never commit sensitive data
- Use environment variables for configuration
- Validate all user inputs
- Sanitize file uploads
- Follow Laravel security best practices

## 📞 Getting Help

- Check existing issues and discussions
- Create a new issue for questions
- Use clear, descriptive titles
- Provide context and examples

## 🎉 Recognition

Contributors will be recognized in:
- README.md contributors section
- Release notes
- Project documentation

Thank you for contributing to this project! 🚀
