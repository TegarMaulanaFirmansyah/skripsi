# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release of sentiment analysis application
- Preprocessing module with Indonesian language support
- Auto labelling with AI learning capabilities
- SVM classification with TF-IDF vectorization
- Comprehensive evaluation module with confusion matrix visualization
- Responsive UI with interactive charts
- File management system with automatic cleanup
- Pagination for large datasets
- Export functionality for results and reports

### Changed
- Improved stemming algorithm to be more conservative
- Enhanced auto labelling with weighted keyword system
- Optimized session management to handle large datasets
- Updated UI with better visualizations

### Fixed
- Session payload overflow issues
- Regex syntax errors in preprocessing
- Memory management for large datasets
- File cleanup and temporary storage management

## [1.0.0] - 2025-01-06

### Added
- **Preprocessing Module**
  - Case folding and text cleansing
  - Indonesian slang normalization
  - Conservative stemming algorithm
  - Stopwords filtering
  - Tokenization and text processing

- **Labelling Module**
  - Auto labelling with keyword-based sentiment analysis
  - Manual correction with bulk update
  - AI learning from manual corrections
  - Confidence score calculation
  - Pagination for large datasets (100 per page)

- **Classification Module**
  - SVM implementation with TF-IDF
  - Cosine similarity for predictions
  - Model evaluation with metrics
  - Training and testing data upload
  - Results export functionality

- **Evaluation Module**
  - Method comparison and ranking
  - Interactive confusion matrix visualization
  - Detailed metrics analysis (precision, recall, F1-score)
  - Report generation and export
  - Canvas-based visualizations

- **UI/UX Features**
  - Responsive design for all devices
  - Interactive charts and visualizations
  - Real-time feedback and status messages
  - File upload with validation
  - Bulk operations and batch processing

- **Technical Features**
  - File-based temporary storage
  - Session management optimization
  - Automatic cleanup functionality
  - Error handling and validation
  - Security best practices

### Technical Details
- **Backend**: Laravel 10.x with PHP 8.1+
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Database**: MySQL with optimized queries
- **Algorithms**: SVM, TF-IDF, Cosine Similarity
- **Visualization**: HTML5 Canvas, CSS Grid/Flexbox

### Performance
- Handles datasets up to 1000+ samples
- Optimized memory usage with file-based storage
- Efficient pagination for large datasets
- Fast preprocessing and classification

### Security
- Input validation and sanitization
- File upload restrictions
- Session security
- Temporary file cleanup
- Environment-based configuration

## [0.9.0] - 2024-12-XX (Development)

### Added
- Basic preprocessing functionality
- Simple auto labelling
- Initial SVM implementation
- Basic UI framework

### Changed
- Improved text processing algorithms
- Enhanced user interface
- Better error handling

### Fixed
- Various bugs in preprocessing
- UI responsiveness issues
- Data validation problems

## [0.8.0] - 2024-11-XX (Alpha)

### Added
- Project initialization
- Basic Laravel setup
- Initial controller structure
- Basic views and routing

### Changed
- Project structure optimization
- Code organization improvements

### Fixed
- Initial setup issues
- Configuration problems

---

## Version History

- **v1.0.0**: Stable release with all core features
- **v0.9.0**: Feature-complete development version
- **v0.8.0**: Initial alpha release

## Future Roadmap

### Planned Features
- [ ] Additional classification algorithms (Naive Bayes, Random Forest)
- [ ] Real-time sentiment analysis API
- [ ] Advanced visualization options
- [ ] Multi-language support
- [ ] Model persistence and versioning
- [ ] Advanced preprocessing options
- [ ] Performance optimization
- [ ] Mobile application
- [ ] API documentation
- [ ] Unit test coverage

### Known Issues
- Large datasets may require server optimization
- Some edge cases in stemming algorithm
- Mobile UI could be further improved

### Deprecations
- None currently planned

---

For more information about changes, please refer to the [GitHub Releases](https://github.com/username/skripsi-sentiment-analysis/releases) page.