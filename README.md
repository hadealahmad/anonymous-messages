# Anonymous Messages for WordPress

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)

A WordPress plugin that enables site visitors to send anonymous messages through a customizable Gutenberg block with advanced spam protection, user management, and comprehensive admin features.

## 🚀 Features

### 🎯 Core Features
- **Gutenberg Block Integration**: Easy-to-use block for the WordPress editor
- **Anonymous Messaging**: Visitors can send messages without revealing their identity

### 📱 Frontend
- **Instant Search**: Real-time search functionality
- **Live Filtering**: Combine search with category filtering
- **Responsive Design**: Mobile-friendly interface

### ⚙️ Dashboard
- **Message Management**: View, respond to, and manage all messages
- **Multiple Response Types**: Short text answers – Full WordPress posts
- **Status Management**: Message states: pending, answered, and featured
    - **Export**: Export messages with filtering options
    - **Email Notifications**: Get email alerts for new messages (sent to assigned user or site admin)
    - **Admin Bar Counter**: See pending messages at a glance (frontend & backend, with red badge)

### 👥 User Management
- **User-specific Inbox**: Messages from specific forms can be assigned to specific WordPress users

### 🛡️ Security
- **Google reCAPTCHA v3**: Invisible CAPTCHA for spam protection
- **Rate Limiting**: Configurable cooldown periods between messages (default: 60 seconds)
- **Honeypot Protection**: Hidden fields to catch automated bots
- **Input Sanitization**: Comprehensive data validation and cleaning
- **Nonce Verification**: CSRF protection for all admin actions

### 🏷️ Category System
- **Category Filtering**: Visitors can filter answered questions by category

### 🔧 Integration
- **Use existing post types as the designated post type for responses**
- **Theme Integration**: Special styling for Blocksy theme using theme formats directly

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
   - Enable/disable email notifications for the assigned user

### Block Attributes
- `showCategories`: Display category filter for answered questions
- `showAnsweredQuestions`: Show the answered questions section
- `questionsPerPage`: Number of questions per page
- `enableRecaptcha`: Enable reCAPTCHA protection
- `assignedUserId`: Assign messages to specific users
- `enableEmailNotifications`: Send an email notification to the assigned user
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

### Version 1.0.1
- better readme
- fixed: blocksy mobile version display issue

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

For more information, visit the [plugin homepage](https://github.com/hadealahmad/anonymous-messages)