/**
 * Frontend JavaScript for Anonymous Messages Block
 */

(function($) {
    'use strict';
    
    class AnonymousMessagesBlock {
        constructor(blockId, attributes) {
            this.blockId = blockId;
            this.attributes = attributes;
            this.block = document.getElementById(blockId);
            
            if (!this.block) {
                console.error('Anonymous Messages Block not found:', blockId);
                return;
            }
            
            this.form = this.block.querySelector('.message-form');
            this.messageInput = this.block.querySelector('.message-input');
            // Category selection removed - categories are now assigned by admin only
            this.categoryFilter = this.block.querySelector('.category-filter');
            this.searchInput = this.block.querySelector('.questions-search');
            this.submitButton = this.block.querySelector('.submit-button');
            this.questionsContainer = this.block.querySelector('.questions-list');
            this.loadMoreButton = this.block.querySelector('.load-more-button');
            this.questionsLoading = this.block.querySelector('.questions-loading');
            this.noQuestionsMessage = this.block.querySelector('.no-questions-message');
            
            // Image upload elements
            this.imageInput = this.block.querySelector('.image-input');
            this.imageUploadButton = this.block.querySelector('.image-upload-button');
            this.imagePreviewContainer = this.block.querySelector('.image-preview-container');
            this.imageUploadError = this.block.querySelector('.image-upload-error');
            this.selectedImages = [];
            
            this.currentPage = 1;
            this.isLoading = false;
            this.rateLimitActive = false;
            this.recaptchaToken = null;
            this.recaptchaWidgetId = undefined;
            this.searchTimeout = null;
            
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.initRateLimit();
            
            if (this.attributes.enableRecaptcha && window.grecaptcha && anonymousMessages.recaptchaSiteKey) {
                this.initRecaptcha();
            }
            
            if (this.attributes.showAnsweredQuestions) {
                // Check for deep link search parameter
                this.checkDeepLinkSearch();
                this.loadQuestions();
            }
        }
        
        checkDeepLinkSearch() {
            const urlParams = new URLSearchParams(window.location.search);
            const searchParam = urlParams.get('search');
            
            if (searchParam && this.searchInput) {
                this.searchInput.value = searchParam;
                
                // Check if we should scroll to this block
                const hash = window.location.hash;
                if (hash === '#' + this.blockId) {
                    setTimeout(() => {
                        this.block.scrollIntoView({ behavior: 'smooth' });
                    }, 500);
                }
            }
        }
        
        bindEvents() {
            // Form submission
            if (this.form) {
                this.form.addEventListener('submit', (e) => this.handleSubmit(e));
            }
            
            // Image upload events
            if (this.imageInput && this.imageUploadButton) {
                this.imageUploadButton.addEventListener('click', () => {
                    this.imageInput.click();
                });
                
                this.imageInput.addEventListener('change', (e) => {
                    this.handleImageSelection(e);
                });
            }
            
            // Category filter change
            if (this.categoryFilter) {
                this.categoryFilter.addEventListener('change', () => {
                    this.currentPage = 1;
                    this.loadQuestions(true);
                });
            }
            
            // Search input with debouncing
            if (this.searchInput) {
                this.searchInput.addEventListener('input', (e) => {
                    // Clear previous timeout
                    if (this.searchTimeout) {
                        clearTimeout(this.searchTimeout);
                    }
                    
                    // Set new timeout for debounced search
                    this.searchTimeout = setTimeout(() => {
                        this.currentPage = 1;
                        this.loadQuestions(true);
                    }, 300); // 300ms debounce delay
                });
            }
            
            // Load more button
            if (this.loadMoreButton) {
                this.loadMoreButton.addEventListener('click', () => this.loadMoreQuestions());
            }
            
            // Twitter share buttons (delegated event)
            this.block.addEventListener('click', (e) => {
                const button = e.target.closest('.twitter-share-btn');
                if (button) {
                    this.handleTwitterShare(e);
                }
            });
        }
        
        async handleSubmit(e) {
            e.preventDefault();
            
            // Check loading state and rate limiting (if enabled)
            const shouldCheckRateLimit = anonymousMessages.enableRateLimiting && 
                (!anonymousMessages.isUserLoggedIn || anonymousMessages.rateLimitLoggedInUsers);
            
            if (this.isLoading || (shouldCheckRateLimit && this.rateLimitActive)) {
                return;
            }
            
            const message = this.messageInput.value.trim();
            if (!message) {
                this.showError(anonymousMessages.strings.error);
                return;
            }
            
            this.setLoading(true);
            
            try {
                // Get reCAPTCHA token if enabled
                if (this.attributes.enableRecaptcha && window.grecaptcha && anonymousMessages.recaptchaSiteKey) {
                    this.recaptchaToken = await this.getRecaptchaToken();
                    if (!this.recaptchaToken) {
                        this.showError(anonymousMessages.strings.recaptchaError);
                        this.setLoading(false);
                        return;
                    }
                }
                
                // Submit message
                const response = await this.submitMessage(message);
                
                if (response.success) {
                    this.showSuccess(response.data.message || anonymousMessages.strings.success);
                    this.form.reset();
                    // Clear image selection
                    this.selectedImages = [];
                    this.showImagePreview();
                    this.hideImageError();
                    this.startRateLimit();
                    
                    // Reload questions if showing answered questions
                    if (this.attributes.showAnsweredQuestions) {
                        this.currentPage = 1;
                        this.loadQuestions(true);
                    }
                } else {
                    this.showError(response.data.message || anonymousMessages.strings.error);
                }
                
            } catch (error) {
                console.error('Submission error:', error);
                this.showError(anonymousMessages.strings.error);
            }
            
            this.setLoading(false);
        }
        
        async submitMessage(message) {
            const formData = new FormData();
            formData.append('action', 'submit_anonymous_message');
            formData.append('message', message);
            formData.append('nonce', anonymousMessages.nonce);
            
            // Category ID no longer sent from frontend - admin assigns categories
            
            if (this.attributes.assignedUserId) {
                formData.append('assigned_user_id', this.attributes.assignedUserId);
            }
            
            // Always send notification flag
            formData.append('send_notification', this.attributes.enableEmailNotifications);
            
            // Add image uploads if any
            if (this.selectedImages && this.selectedImages.length > 0) {
                this.selectedImages.forEach((file, index) => {
                    formData.append('images[]', file);
                });
            }
            
            if (this.recaptchaToken) {
                formData.append('recaptcha_token', this.recaptchaToken);
            }
            
            const response = await fetch(anonymousMessages.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            return await response.json();
        }
        
        async getRecaptchaToken() {
            try {
                // Use standard reCAPTCHA v3 method
                return await window.grecaptcha.execute(anonymousMessages.recaptchaSiteKey, {
                    action: 'submit_message'
                });
            } catch (error) {
                console.error('reCAPTCHA error:', error);
                return null;
            }
        }
        
        async loadQuestions(reset = false) {
            if (this.isLoading) return;
            
            this.isLoading = true;
            this.showQuestionsLoading(true);
            
            if (reset) {
                this.questionsContainer.innerHTML = '';
                this.currentPage = 1;
            }
            
            try {
                const categoryId = this.categoryFilter ? this.categoryFilter.value : '';
                const searchQuery = this.searchInput ? this.searchInput.value.trim() : '';
                const response = await this.fetchQuestions(categoryId, this.currentPage, searchQuery);
                
                if (response.success) {
                    const questions = response.data.questions;
                    const hasMore = response.data.has_more;
                    
                    if (questions.length === 0 && this.currentPage === 1) {
                        this.showNoQuestions(true);
                        this.showLoadMoreButton(false);
                    } else {
                        this.showNoQuestions(false);
                        this.renderQuestions(questions, reset);
                        this.showLoadMoreButton(hasMore);
                    }
                } else {
                    console.error('Failed to load questions:', response.data.message);
                }
                
            } catch (error) {
                console.error('Error loading questions:', error);
            }
            
            this.showQuestionsLoading(false);
            this.isLoading = false;
        }
        
        async fetchQuestions(categoryId, page, searchQuery = '') {
            const formData = new FormData();
            formData.append('action', 'get_answered_questions');
            formData.append('page', page);
            formData.append('per_page', this.attributes.questionsPerPage);
            formData.append('nonce', anonymousMessages.nonce);
            
            if (categoryId) {
                formData.append('category_id', categoryId);
            }
            
            if (searchQuery) {
                formData.append('search', searchQuery);
            }
            
            if (this.attributes.assignedUserId) {
                formData.append('assigned_user_id', this.attributes.assignedUserId);
                // Only send notification flag if a user is assigned
                formData.append('send_notification', this.attributes.enableEmailNotifications);
            }
            
            const response = await fetch(anonymousMessages.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            return await response.json();
        }
        
        renderQuestions(questions, reset) {
            if (reset) {
                this.questionsContainer.innerHTML = '';
            }
            
            questions.forEach(question => {
                const questionElement = this.createQuestionElement(question);
                this.questionsContainer.appendChild(questionElement);
            });
        }
        
        createQuestionElement(question) {
            const div = document.createElement('div');
            div.className = 'question-item' + (question.status === 'featured' ? ' featured' : '');
            
            let responseContent = '';
            if (question.response_type === 'short' && question.short_response) {
                // Allow HTML content in responses while maintaining security
                responseContent = `<div class="response-content">${this.sanitizeHtml(question.short_response)}</div>`;
            } else if (question.response_type === 'post' && question.post_title && question.post_url) {
                responseContent = `
                    <p class="response-content">
                        <a href="${this.escapeHtml(question.post_url)}" class="response-post-link" target="_blank">
                            ${this.escapeHtml(question.post_title)}
                        </a>
                    </p>
                `;
            }
            
            const categoryBadge = question.category_name ? 
                `<span class="question-category">${this.escapeHtml(question.category_name)}</span>` : '';
            
            // Use pre-formatted dates from server (WordPress localized format)
            const receivedDate = question.created_at_formatted || this.formatDate(question.created_at);
            const answeredDate = question.answered_at_formatted || (question.answered_at ? this.formatDate(question.answered_at) : null);
            
            // Generate Twitter share button
            const twitterShareButton = this.createTwitterShareButton(question);
            
            div.innerHTML = `
                <div class="question-header">
                    <div class="question-header-info">
                        <span class="question-received-date">${anonymousMessages.strings.asked}: ${receivedDate}</span>
                        ${categoryBadge}
                    </div>
                    ${twitterShareButton}
                </div>
                <div class="question-section">
                    <div class="question-text-wrapper">
                        <p class="question-text">${this.escapeHtml(question.message)}</p>
                    </div>
                </div>
                <div class="question-response">
                    <span class="response-label">${anonymousMessages.strings.answer}</span>
                    <div class="response-content-wrapper">
                        ${responseContent}
                        ${answeredDate ? `<div class="response-date"><span class="question-answered-date">${anonymousMessages.strings.answered}: ${answeredDate}</span></div>` : ''}
                    </div>
                </div>
            `;
            
            return div;
        }
        
        loadMoreQuestions() {
            this.currentPage++;
            this.loadQuestions(false);
        }
        
        setLoading(loading) {
            this.isLoading = loading;
            
            if (this.submitButton) {
                // Check if rate limiting should be applied
                const shouldCheckRateLimit = anonymousMessages.enableRateLimiting && 
                    (!anonymousMessages.isUserLoggedIn || anonymousMessages.rateLimitLoggedInUsers);
                
                this.submitButton.disabled = loading || (shouldCheckRateLimit && this.rateLimitActive);
                this.submitButton.classList.toggle('loading', loading);
            }
        }
        
        showSuccess(message) {
            this.hideMessages();
            const successElement = this.block.querySelector('.success-message');
            if (successElement) {
                successElement.textContent = message;
                successElement.style.display = 'block';
                setTimeout(() => {
                    successElement.style.display = 'none';
                }, 5000);
            }
        }
        
        showError(message) {
            this.hideMessages();
            const errorElement = this.block.querySelector('.error-message');
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.style.display = 'block';
            }
        }
        
        hideMessages() {
            const successElement = this.block.querySelector('.success-message');
            const errorElement = this.block.querySelector('.error-message');
            
            if (successElement) successElement.style.display = 'none';
            if (errorElement) errorElement.style.display = 'none';
        }
        
        showQuestionsLoading(show) {
            if (this.questionsLoading) {
                this.questionsLoading.style.display = show ? 'block' : 'none';
            }
        }
        
        showLoadMoreButton(show) {
            if (this.loadMoreButton) {
                this.loadMoreButton.style.display = show ? 'block' : 'none';
            }
        }
        
        showNoQuestions(show) {
            if (this.noQuestionsMessage) {
                this.noQuestionsMessage.style.display = show ? 'block' : 'none';
            }
        }
        
        initRateLimit() {
            // Check if rate limiting is enabled
            if (!anonymousMessages.enableRateLimiting) {
                return;
            }
            
            // Check if user is logged in and rate limiting is disabled for logged-in users
            if (anonymousMessages.isUserLoggedIn && !anonymousMessages.rateLimitLoggedInUsers) {
                return;
            }
            
            const lastSubmission = localStorage.getItem('anonymous_messages_last_submission');
            if (lastSubmission) {
                const timeDiff = Date.now() - parseInt(lastSubmission);
                const remainingTime = (anonymousMessages.rateLimitSeconds * 1000) - timeDiff;
                
                if (remainingTime > 0) {
                    this.startRateLimit(Math.ceil(remainingTime / 1000));
                }
            }
        }
        
        startRateLimit(seconds = null) {
            // Check if rate limiting is enabled
            if (!anonymousMessages.enableRateLimiting) {
                return;
            }
            
            // Check if user is logged in and rate limiting is disabled for logged-in users
            if (anonymousMessages.isUserLoggedIn && !anonymousMessages.rateLimitLoggedInUsers) {
                return;
            }
            
            const duration = seconds || anonymousMessages.rateLimitSeconds;
            this.rateLimitActive = true;
            
            localStorage.setItem('anonymous_messages_last_submission', Date.now().toString());
            
            const timerElement = this.block.querySelector('.rate-limit-timer');
            const timerSeconds = this.block.querySelector('.timer-seconds');
            
            if (timerElement) {
                timerElement.style.display = 'block';
                this.submitButton.disabled = true;
                
                let remaining = duration;
                const updateTimer = () => {
                    if (timerSeconds) {
                        timerSeconds.textContent = remaining;
                    }
                    
                    if (remaining <= 0) {
                        this.rateLimitActive = false;
                        timerElement.style.display = 'none';
                        this.submitButton.disabled = false;
                        return;
                    }
                    
                    remaining--;
                    setTimeout(updateTimer, 1000);
                };
                
                updateTimer();
            }
        }
        
        initRecaptcha() {
            if (window.grecaptcha && anonymousMessages.recaptchaSiteKey) {
                window.grecaptcha.ready(() => {
                    // Find the reCAPTCHA container
                    const recaptchaContainer = this.block.querySelector('.recaptcha-container');
                    if (recaptchaContainer) {
                        // Hide global reCAPTCHA badge since we'll manage it locally
                        this.hideGlobalRecaptchaBadge();
                        
                        // Create a specific container div if it doesn't exist
                        let targetContainer = recaptchaContainer.querySelector('div');
                        if (!targetContainer) {
                            targetContainer = document.createElement('div');
                            recaptchaContainer.appendChild(targetContainer);
                        }
                        
                        // Render reCAPTCHA with proper badge positioning
                        try {
                            this.recaptchaWidgetId = window.grecaptcha.render(targetContainer, {
                                'sitekey': anonymousMessages.recaptchaSiteKey,
                                'badge': 'bottomright', // Use bottomright within the container
                                'size': 'invisible',
                                'callback': (token) => {
                                    // Token will be handled in submitMessage
                                }
                            });
                            
                            // Make sure the badge is visible only within this block
                            this.positionRecaptchaBadge();
                            
                            console.log('reCAPTCHA initialized with widget ID:', this.recaptchaWidgetId);
                        } catch (error) {
                            console.error('Failed to initialize reCAPTCHA:', error);
                        }
                    }
                });
            }
        }
        
        hideGlobalRecaptchaBadge() {
            // Hide the global reCAPTCHA badge that appears by default
            const style = document.createElement('style');
            style.textContent = `
                .grecaptcha-badge {
                    display: none !important;
                }
                .anonymous-messages-block .grecaptcha-badge {
                    display: block !important;
                    position: relative !important;
                    bottom: auto !important;
                    right: auto !important;
                    margin: 10px 0;
                }
            `;
            
            // Only add the style if it doesn't already exist
            if (!document.querySelector('#recaptcha-badge-styles')) {
                style.id = 'recaptcha-badge-styles';
                document.head.appendChild(style);
            }
        }
        
        positionRecaptchaBadge() {
            // Wait for reCAPTCHA badge to be created
            setTimeout(() => {
                const badges = document.querySelectorAll('.grecaptcha-badge');
                badges.forEach(badge => {
                    // Check if this badge is within our block
                    if (this.block.contains(badge) || this.isRecaptchaBadgeForThisBlock(badge)) {
                        badge.style.position = 'relative';
                        badge.style.bottom = 'auto';
                        badge.style.right = 'auto';
                        badge.style.margin = '10px 0';
                        badge.style.display = 'block';
                        
                        // Move badge to our reCAPTCHA container if it's not already there
                        const recaptchaContainer = this.block.querySelector('.recaptcha-container');
                        if (recaptchaContainer && !recaptchaContainer.contains(badge)) {
                            recaptchaContainer.appendChild(badge);
                        }
                    }
                });
            }, 500);
        }
        
        isRecaptchaBadgeForThisBlock(badge) {
            // Check if this badge was created for this specific block
            // by checking if our widget ID corresponds to this badge
            return this.recaptchaWidgetId !== undefined && badge.dataset && 
                   badge.dataset.sitekey === anonymousMessages.recaptchaSiteKey;
        }
        
        cleanup() {
            // Clean up reCAPTCHA widget if it exists
            if (this.recaptchaWidgetId !== undefined && window.grecaptcha) {
                try {
                    window.grecaptcha.reset(this.recaptchaWidgetId);
                } catch (error) {
                    console.log('reCAPTCHA widget cleanup:', error);
                }
                this.recaptchaWidgetId = undefined;
            }
            
            // Clear any timeouts
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = null;
            }
            
            // Mark block as uninitialized
            if (this.block) {
                this.block.dataset.initialized = 'false';
            }
        }
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        sanitizeHtml(html) {
            // Create a temporary div to parse and sanitize HTML
            const div = document.createElement('div');
            div.innerHTML = html;
            
            // Remove any script tags and event handlers for security
            const scripts = div.querySelectorAll('script');
            scripts.forEach(script => script.remove());
            
            // Remove any elements with event handlers
            const elementsWithEvents = div.querySelectorAll('*');
            elementsWithEvents.forEach(element => {
                const attributes = element.attributes;
                for (let i = attributes.length - 1; i >= 0; i--) {
                    const attr = attributes[i];
                    if (attr.name.startsWith('on') || attr.name.startsWith('javascript:')) {
                        element.removeAttribute(attr.name);
                    }
                }
            });
            
            return div.innerHTML;
        }
        
        formatDate(dateString) {
            if (!dateString) return '';
            
            try {
                const date = new Date(dateString);
                
                // Check if date is valid
                if (isNaN(date.getTime())) {
                    return dateString; // Return original string if invalid
                }
                
                // Use WordPress date format if available
                if (anonymousMessages && anonymousMessages.dateFormat && anonymousMessages.timeFormat) {
                    return this.formatDateWithWordPressFormat(date, anonymousMessages.dateFormat + ' ' + anonymousMessages.timeFormat);
                }
                
                // Fallback to browser locale formatting
                const options = {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                };
                
                return date.toLocaleDateString(navigator.language || 'en-US', options);
            } catch (error) {
                console.error('Error formatting date:', error);
                return dateString; // Return original string on error
            }
        }
        
        formatDateWithWordPressFormat(date, format) {
            // WordPress date format mapping
            const formatMap = {
                'd': () => String(date.getDate()).padStart(2, '0'),
                'D': () => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][date.getDay()],
                'j': () => String(date.getDate()),
                'l': () => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][date.getDay()],
                'N': () => String(date.getDay() || 7), // Monday = 1, Sunday = 7
                'S': () => {
                    const day = date.getDate();
                    if (day >= 11 && day <= 13) return 'th';
                    switch (day % 10) {
                        case 1: return 'st';
                        case 2: return 'nd';
                        case 3: return 'rd';
                        default: return 'th';
                    }
                },
                'w': () => String(date.getDay()),
                'z': () => {
                    const start = new Date(date.getFullYear(), 0, 0);
                    const diff = date - start;
                    const oneDay = 1000 * 60 * 60 * 24;
                    return Math.floor(diff / oneDay);
                },
                'W': () => {
                    // ISO-8601 week number
                    const d = new Date(date);
                    d.setHours(0, 0, 0, 0);
                    d.setDate(d.getDate() + 4 - (d.getDay() || 7));
                    const yearStart = new Date(d.getFullYear(), 0, 1);
                    const weekNo = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
                    return String(weekNo);
                },
                'F': () => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'][date.getMonth()],
                'm': () => String(date.getMonth() + 1).padStart(2, '0'),
                'M': () => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][date.getMonth()],
                'n': () => String(date.getMonth() + 1),
                't': () => String(new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate()),
                'L': () => ((date.getFullYear() % 4 === 0 && date.getFullYear() % 100 !== 0) || date.getFullYear() % 400 === 0) ? '1' : '0',
                'o': () => String(date.getFullYear()),
                'Y': () => String(date.getFullYear()),
                'y': () => String(date.getFullYear()).slice(-2),
                'a': () => date.getHours() >= 12 ? 'pm' : 'am',
                'A': () => date.getHours() >= 12 ? 'PM' : 'AM',
                'B': () => {
                    // Swatch Internet Time
                    const utc = date.getTime() + (date.getTimezoneOffset() * 60000);
                    const swatch = Math.floor(((utc % 86400000) / 86400000) * 1000);
                    return String(swatch);
                },
                'g': () => String(date.getHours() % 12 || 12),
                'G': () => String(date.getHours()),
                'h': () => String(date.getHours() % 12 || 12).padStart(2, '0'),
                'H': () => String(date.getHours()).padStart(2, '0'),
                'i': () => String(date.getMinutes()).padStart(2, '0'),
                's': () => String(date.getSeconds()).padStart(2, '0'),
                'u': () => String(date.getMilliseconds()).padStart(3, '0'),
                'v': () => String(date.getMilliseconds()).padStart(3, '0'),
                'e': () => Intl.DateTimeFormat().resolvedOptions().timeZone,
                'I': () => {
                    // Daylight saving time
                    const jan = new Date(date.getFullYear(), 0, 1);
                    const jul = new Date(date.getFullYear(), 6, 1);
                    return (jan.getTimezoneOffset() !== jul.getTimezoneOffset()) ? '1' : '0';
                },
                'O': () => {
                    const offset = date.getTimezoneOffset();
                    const sign = offset > 0 ? '-' : '+';
                    const hours = Math.floor(Math.abs(offset) / 60);
                    const minutes = Math.abs(offset) % 60;
                    return sign + String(hours).padStart(2, '0') + String(minutes).padStart(2, '0');
                },
                'P': () => {
                    const offset = date.getTimezoneOffset();
                    const sign = offset > 0 ? '-' : '+';
                    const hours = Math.floor(Math.abs(offset) / 60);
                    const minutes = Math.abs(offset) % 60;
                    return sign + String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
                },
                'T': () => Intl.DateTimeFormat().resolvedOptions().timeZone,
                'Z': () => String(date.getTimezoneOffset() * 60),
                'c': () => date.toISOString(),
                'r': () => date.toUTCString(),
                'U': () => String(Math.floor(date.getTime() / 1000))
            };
            
            let result = format;
            
            // Replace format characters with their values
            for (const [char, formatter] of Object.entries(formatMap)) {
                const regex = new RegExp(char.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
                result = result.replace(regex, formatter());
            }
            
            return result;
        }
        
        createTwitterShareButton(question) {
            const questionText = (question.message || '').replace(/"/g, '&quot;');
            const answerText = (this.getAnswerText(question) || '').replace(/"/g, '&quot;');
            
            return `
                <button type="button" 
                        class="twitter-share-btn button" 
                        data-question-id="${question.id || ''}"
                        data-question-text="${questionText}"
                        data-answer-text="${answerText}"
                        title="${anonymousMessages.strings.shareOnTwitter}"
                        aria-label="${anonymousMessages.strings.shareOnTwitter}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                    </svg>
                    <span class="twitter-share-text">${anonymousMessages.strings.share}</span>
                </button>
            `;
        }
        
        getAnswerText(question) {
            if (question.response_type === 'short' && question.short_response) {
                // Strip HTML tags for Twitter sharing
                const div = document.createElement('div');
                div.innerHTML = question.short_response;
                return div.textContent || div.innerText || '';
            } else if (question.response_type === 'post' && question.post_title) {
                return question.post_title;
            }
            return '';
        }
        
        handleTwitterShare(e) {
            e.preventDefault();
            
            const button = e.target.closest('.twitter-share-btn');
            const questionId = button.dataset.questionId;
            const questionText = button.dataset.questionText;
            const answerText = button.dataset.answerText;
            
            // Format the Twitter content
            const twitterContent = this.formatTwitterContent(questionText, answerText);
            
            // Generate deep link URL
            const deepLinkUrl = this.generateDeepLink(questionText);
            
            // Create Twitter share URL
            const twitterUrl = this.createTwitterShareUrl(twitterContent, deepLinkUrl);
            
            // Open Twitter share window
            this.openTwitterWindow(twitterUrl);
        }
        
        formatTwitterContent(question, answer) {
            // Get localized prefixes
            const questionPrefix = anonymousMessages.strings.questionPrefix || 'q:';
            const answerPrefix = anonymousMessages.strings.answerPrefix || 'a:';
            
            // Twitter character limit (280 chars), leaving space for link and some padding
            const maxChars = 200;
            
            // Format question and answer
            const formattedQuestion = `${questionPrefix} ${question}`;
            const formattedAnswer = `${answerPrefix} ${answer}`;
            
            // Calculate available space for answer after question
            const questionLength = formattedQuestion.length;
            const availableForAnswer = maxChars - questionLength - 3; // 3 chars for " | "
            
            let finalAnswer = formattedAnswer;
            if (finalAnswer.length > availableForAnswer) {
                // Trim answer to fit, keeping the prefix
                const trimmedAnswer = answer.substring(0, availableForAnswer - answerPrefix.length - 4) + '...';
                finalAnswer = `${answerPrefix} ${trimmedAnswer}`;
            }
            
            return `${formattedQuestion} | ${finalAnswer}`;
        }
        
        generateDeepLink(questionText) {
            const currentUrl = new URL(window.location.href);
            
            // Remove existing search parameters and add question search
            currentUrl.searchParams.delete('search');
            currentUrl.searchParams.set('search', questionText.substring(0, 50)); // Limit to 50 chars
            currentUrl.hash = this.blockId; // Scroll to this block
            
            return currentUrl.toString();
        }
        
        createTwitterShareUrl(text, url) {
            const hashtag = anonymousMessages.twitterHashtag || 'QandA';
            
            const params = new URLSearchParams({
                text: text,
                url: url,
                hashtags: hashtag
            });
            
            return `https://twitter.com/intent/tweet?${params.toString()}`;
        }
        
        openTwitterWindow(url) {
            const width = 600;
            const height = 400;
            const left = (window.innerWidth - width) / 2;
            const top = (window.innerHeight - height) / 2;
            
            window.open(
                url,
                'twitterShare',
                `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
            );
        }
        
        /**
         * Handle image file selection
         */
        handleImageSelection(event) {
            const files = Array.from(event.target.files);
            const maxFiles = parseInt(this.imageInput.dataset.maxFiles);
            const maxSize = parseInt(this.imageInput.dataset.maxSize);
            
            // Clear previous errors
            this.hideImageError();
            
            // Check file count
            if (files.length > maxFiles) {
                this.showImageError(anonymousMessages.strings.maxFilesError.replace('%d', maxFiles));
                this.imageInput.value = '';
                return;
            }
            
            // Validate each file
            const validFiles = [];
            for (const file of files) {
                if (file.size > maxSize) {
                    const maxSizeMB = (maxSize / (1024 * 1024)).toFixed(1);
                    this.showImageError(anonymousMessages.strings.fileTooLargeError.replace('%s', file.name).replace('%s', maxSizeMB));
                    this.imageInput.value = '';
                    return;
                }
                
                if (!file.type.startsWith('image/')) {
                    this.showImageError(anonymousMessages.strings.invalidImageType.replace('%s', file.name));
                    this.imageInput.value = '';
                    return;
                }
                
                validFiles.push(file);
            }
            
            this.selectedImages = validFiles;
            this.showImagePreview();
        }
        
        /**
         * Show image preview
         */
        showImagePreview() {
            if (!this.imagePreviewContainer) return;
            
            this.imagePreviewContainer.innerHTML = '';
            
            if (this.selectedImages.length === 0) {
                this.imagePreviewContainer.style.display = 'none';
                return;
            }
            
            this.imagePreviewContainer.style.display = 'block';
            
            this.selectedImages.forEach((file, index) => {
                const preview = document.createElement('div');
                preview.className = 'image-preview-item';
                
                const img = document.createElement('img');
                img.className = 'preview-image';
                
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-image';
                removeBtn.innerHTML = '×';
                removeBtn.title = anonymousMessages.strings.removeImage;
                removeBtn.addEventListener('click', () => this.removeImage(index));
                
                const fileName = document.createElement('div');
                fileName.className = 'image-name';
                fileName.textContent = file.name;
                
                // Load image preview
                const reader = new FileReader();
                reader.onload = (e) => {
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
                
                preview.appendChild(img);
                preview.appendChild(removeBtn);
                preview.appendChild(fileName);
                this.imagePreviewContainer.appendChild(preview);
            });
        }
        
        /**
         * Remove image from selection
         */
        removeImage(index) {
            this.selectedImages.splice(index, 1);
            this.updateImageInput();
            this.showImagePreview();
        }
        
        /**
         * Update file input with selected images
         */
        updateImageInput() {
            if (!this.imageInput) return;
            
            const dt = new DataTransfer();
            this.selectedImages.forEach(file => {
                dt.items.add(file);
            });
            this.imageInput.files = dt.files;
        }
        
        /**
         * Show image upload error
         */
        showImageError(message) {
            if (this.imageUploadError) {
                this.imageUploadError.textContent = message;
                this.imageUploadError.style.display = 'block';
            }
        }
        
        /**
         * Hide image upload error
         */
        hideImageError() {
            if (this.imageUploadError) {
                this.imageUploadError.style.display = 'none';
            }
        }
    }
    
    // Make the class globally available
    window.AnonymousMessagesBlock = AnonymousMessagesBlock;
    
    // Store block instances for cleanup
    window.AnonymousMessagesBlockInstances = window.AnonymousMessagesBlockInstances || [];
    
    // Auto-initialize blocks when DOM is ready
    function initializeBlocks() {
        // Check if anonymousMessages object is available
        if (typeof anonymousMessages === 'undefined') {
            return;
        }
        
        const blocks = document.querySelectorAll('.anonymous-messages-block');
        
        // Check if any blocks need reCAPTCHA before initializing
        let hasRecaptchaBlocks = false;
        
        blocks.forEach(block => {
            const blockId = block.id;
            
            // Skip if already initialized
            if (block.dataset.initialized === 'true') {
                return;
            }
            
            // Extract attributes from data attributes
            const attributes = {
                showCategories: block.dataset.showCategories === 'true',
                showAnsweredQuestions: block.dataset.showAnsweredQuestions === 'true',
                questionsPerPage: parseInt(block.dataset.questionsPerPage) || 10,
                enableRecaptcha: block.dataset.enableRecaptcha === 'true',
                assignedUserId: parseInt(block.dataset.assignedUserId) || 0,
                enableEmailNotifications: block.dataset.enableEmailNotifications !== 'false' // Default to true if not set
            };
            
            // Track if any block needs reCAPTCHA
            if (attributes.enableRecaptcha && anonymousMessages.recaptchaSiteKey) {
                hasRecaptchaBlocks = true;
            }
            
            // Initialize the block
            try {
                const blockInstance = new AnonymousMessagesBlock(blockId, attributes);
                // Store instance for cleanup
                window.AnonymousMessagesBlockInstances.push(blockInstance);
            } catch (error) {
                console.error('Error initializing block:', blockId, error);
            }
            
            // Mark as initialized
            block.dataset.initialized = 'true';
        });
        
        // If no blocks need reCAPTCHA, ensure global badge is hidden
        if (!hasRecaptchaBlocks && window.grecaptcha) {
            hideGlobalRecaptchaBadgeCompletely();
        }
    }
    
    // Global function to completely hide reCAPTCHA badge when not needed
    function hideGlobalRecaptchaBadgeCompletely() {
        const style = document.createElement('style');
        style.textContent = `
            .grecaptcha-badge {
                display: none !important;
            }
        `;
        
        if (!document.querySelector('#recaptcha-global-hide-styles')) {
            style.id = 'recaptcha-global-hide-styles';
            document.head.appendChild(style);
        }
    }
    
    // Global cleanup function
    function cleanupAllInstances() {
        if (window.AnonymousMessagesBlockInstances) {
            window.AnonymousMessagesBlockInstances.forEach(instance => {
                if (instance && typeof instance.cleanup === 'function') {
                    instance.cleanup();
                }
            });
            window.AnonymousMessagesBlockInstances = [];
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeBlocks);
    } else {
        initializeBlocks();
    }
    
    // Also initialize on AJAX content load (for dynamic content)
    $(document).on('ajaxComplete', initializeBlocks);
    
    // Widget-specific initialization
    $(document).ready(function() {
        // Additional initialization after page load for widget contexts
        setTimeout(initializeBlocks, 500);
        
        // Watch for dynamically added content (widgets, AJAX, etc.)
        if (window.MutationObserver) {
            const observer = new MutationObserver(function(mutations) {
                let needsInitialization = false;
                
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                        // Check if any added nodes contain our block
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1) { // Element node
                                if (node.classList && node.classList.contains('anonymous-messages-block')) {
                                    needsInitialization = true;
                                } else if (node.querySelector && node.querySelector('.anonymous-messages-block')) {
                                    needsInitialization = true;
                                }
                            }
                        });
                    }
                });
                
                if (needsInitialization) {
                    // Delay to ensure content is fully rendered
                    setTimeout(initializeBlocks, 100);
                }
            });
            
            // Start observing
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    });
    
    // Cleanup on page unload to prevent reCAPTCHA badges from lingering
    window.addEventListener('beforeunload', cleanupAllInstances);
    window.addEventListener('pagehide', cleanupAllInstances);
    
})(jQuery);