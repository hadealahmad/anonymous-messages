# Anonymous Messages WordPress Plugin

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)

A powerful WordPress plugin that enables site visitors to send anonymous messages through a customizable Gutenberg block with advanced spam protection, user management, and comprehensive admin features.

## 🚀 Features

### 🎯 Core Functionality
- **Gutenberg Block Integration**: Easy-to-use block for the WordPress editor
- **Anonymous Messaging**: Visitors can send messages without revealing their identity
- **Random Sender Names**: Auto-generated anonymous names for message attribution
- **Real-time Submission**: AJAX-powered message submission with instant feedback

### 🛡️ Advanced Security
- **Google reCAPTCHA v3**: Invisible CAPTCHA for spam protection
- **Session-based Rate Limiting**: Configurable cooldown periods (default: 60 seconds)
- **Honeypot Protection**: Hidden fields to catch automated bots
- **Input Sanitization**: Comprehensive data validation and cleaning
- **Nonce Verification**: CSRF protection for all admin actions
- **Security Headers**: Additional security headers for enhanced protection

### 👥 User Management
- **User Assignment**: Messages can be assigned to specific WordPress users
- **Role-based Access Control**: Different permissions for different user roles
- **Admin-only Categories**: Categories can only be assigned by administrators
- **User Filtering**: Filter messages by assigned users

### 🏷️ Category System
- **Admin-controlled Categories**: Only administrators can create and assign categories
- **Post-submission Assignment**: Categories are assigned after message submission
- **Category Filtering**: Visitors can filter answered questions by category
- **CRUD Operations**: Full category management in admin interface

### 📱 Frontend Features
- **Instant Search**: Real-time search with 300ms debouncing
- **Live Filtering**: Combine search with category filtering
- **Responsive Design**: Mobile-friendly interface
- **Loading States**: Clear feedback during operations
- **Error Handling**: User-friendly error messages

### ⚙️ Admin Dashboard
- **Message Management**: View, respond to, and manage all messages
- **Multiple Response Types**: 
  - Short text answers
  - Full WordPress posts
  - Custom post type integration
- **Status Management**: Pending, answered, and featured message states
- **Export Functionality**: Export messages with filtering options
- **Real-time Updates**: AJAX-powered admin interface

### 🔧 Flexible Configuration
- **Post Type Options**: 
  - Use existing post types
  - Create custom post types
  - Disable post responses entirely
- **Customizable Settings**: Rate limiting, pagination, and display options
- **Theme Integration**: Special styling for popular themes (Blocksy included)

### 🌍 Internationalization
- **Translation Ready**: Full multilingual support
- **POT Files**: Translation template included
- **Arabic Translation**: Complete Arabic translation included
- **RTL Support**: Right-to-left language compatibility

## 📋 Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Google reCAPTCHA**: API keys required for spam protection

## 🛠️ Installation

### Method 1: Upload Plugin
1. Download the plugin files
2. Upload the `anonymous-messages` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure your settings in **Anonymous Messages > Settings**

### Method 2: WordPress Admin
1. Go to **Plugins > Add New** in your WordPress admin
2. Upload the plugin zip file
3. Activate the plugin
4. Configure your settings

## ⚙️ Configuration

### 1. Google reCAPTCHA Setup
1. Visit [Google reCAPTCHA](https://www.google.com/recaptcha/)
2. Register your site for reCAPTCHA v3
3. Copy your Site Key and Secret Key
4. Go to **Anonymous Messages > Settings**
5. Enter your reCAPTCHA credentials

### 2. Basic Settings
- **Rate Limiting**: Set the cooldown period between submissions
- **Messages Per Page**: Configure pagination for answered questions
- **Categories**: Enable/disable the category system
- **Featured Messages**: Allow marking messages as featured

### 3. Post Answer Configuration
Choose how you want to handle detailed responses:
- **Existing Post Type**: Use WordPress posts or pages
- **Custom Post Type**: Create a dedicated "Anonymous Answers" post type
- **Disabled**: Only allow short text responses

## 📝 Usage

### Adding the Block
1. Edit any page or post
2. Add a new block and search for "Anonymous Messages"
3. Configure the block settings:
   - Show/hide category filtering
   - Show/hide answered questions section
   - Set questions per page
   - Enable/disable reCAPTCHA
   - Assign to specific user (if configured)

### Block Attributes
- `showCategories`: Display category filter for answered questions
- `showAnsweredQuestions`: Show the answered questions section
- `questionsPerPage`: Number of questions per page
- `enableRecaptcha`: Enable reCAPTCHA protection
- `assignedUserId`: Assign messages to specific users
- `placeholder`: Custom placeholder text

### Managing Messages
1. Go to **Anonymous Messages > Messages**
2. View all submitted messages
3. Respond with short answers or create full posts
4. Assign categories and update status
5. Mark important messages as featured

### Category Management
1. Go to **Anonymous Messages > Categories**
2. Create new categories with names and descriptions
3. Assign categories to messages from the messages page
4. Categories help organize content for visitors

## 🎨 Customization

### CSS Styling
The plugin includes comprehensive CSS for styling:
- `assets/css/block-style.css` - Default styling
- `assets/css/block-style-blocksy.css` - Blocksy theme integration
- `assets/css/admin-style.css` - Admin interface styling

### Theme Integration
The plugin automatically detects popular themes and applies appropriate styling. Currently includes special integration for:
- Blocksy theme
- Default WordPress themes

### Hooks and Filters
The plugin provides various hooks for customization:
- `anonymous_messages_before_submission`
- `anonymous_messages_after_submission`
- `anonymous_messages_message_saved`
- `anonymous_messages_response_created`

## 🔧 Database Structure

### Tables Created
- `wp_anonymous_messages`: Core message storage
- `wp_anonymous_message_categories`: Category management
- `wp_anonymous_message_responses`: Message responses

### Database Versioning
The plugin includes automatic database migration system for updates.

## 🛡️ Security Features

- **Input Validation**: All inputs are sanitized and validated
- **SQL Injection Prevention**: Prepared statements for all database queries
- **XSS Protection**: Proper output escaping
- **CSRF Protection**: Nonce verification for all admin actions
- **Rate Limiting**: Server-side and client-side submission limits
- **Capability Checks**: Proper permission verification

## 🌐 API Endpoints

### AJAX Endpoints
- `submit_anonymous_message`: Handle message submissions
- `get_answered_questions`: Retrieve answered questions with filtering
- `am_respond_to_message`: Admin response handling
- `am_update_message_status`: Status updates
- `am_update_message_category`: Category assignment

## 📊 Performance

- **Optimized Queries**: Efficient database queries with proper indexing
- **AJAX Loading**: Minimal page reloads for better user experience
- **Debounced Search**: Optimized search with 300ms debouncing
- **Pagination**: Efficient handling of large datasets

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

### Development Setup
1. Clone the repository
2. Set up a local WordPress development environment
3. Install the plugin in development mode
4. Make your changes and test thoroughly

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Hadi Alahmad**
- Website: [hadealahmad.com](https://hadealahmad.com)
- GitHub: [@hadealahmad](https://github.com/hadealahmad)

## 🙏 Acknowledgments

- WordPress community for excellent documentation
- Google reCAPTCHA for spam protection
- All contributors and testers

## 📈 Changelog

### Version 1.0.0
- Initial release
- Core messaging functionality
- Gutenberg block integration
- Admin management system
- Security features implementation
- Multilingual support
- User assignment system
- Advanced category management

---

For more information, visit the [plugin homepage](https://github.com/hadealahmad/anonymous-messages) or check the [documentation](https://github.com/hadealahmad/anonymous-messages/wiki).