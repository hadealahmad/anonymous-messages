/**
 * Admin JavaScript for Anonymous Messages
 */

(function($) {
    'use strict';
    
    class AnonymousMessagesAdmin {
        constructor() {
            this.init();
        }
        
        init() {
            this.bindEvents();
        }
        
        bindEvents() {
            // Modal system
            $(document).on('click', '.respond-to-message', this.showResponseModal.bind(this));
            $(document).on('click', '.edit-answer', this.showEditModal.bind(this));
            $(document).on('click', '.am-modal-close, .am-modal-cancel', this.closeModal.bind(this));
            $(document).on('click', '.am-modal-backdrop', this.closeModal.bind(this));
            $(document).on('submit', '#am-response-form', this.handleFormSubmit.bind(this));
            $(document).on('submit', '#am-edit-form', this.handleFormSubmit.bind(this));
            $(document).on('change', 'input[name="response_type"]', this.toggleResponseType.bind(this));
            $(document).on('change', 'input[name="edit_response_type"]', this.toggleEditResponseType.bind(this));
            
            // Status updates
            $(document).on('change', '.status-select', this.updateMessageStatus.bind(this));
            
            // Category assignment
            $(document).on('change', '.category-assign-select', this.updateMessageCategory.bind(this));
            
            // Message deletion
            $(document).on('click', '.delete-message', this.deleteMessage.bind(this));
            
            // Category management
            $(document).on('submit', '#add-category-form', this.addCategory.bind(this));
            $(document).on('click', '.edit-category', this.showEditCategoryModal.bind(this));
            $(document).on('click', '.delete-category', this.deleteCategory.bind(this));
            $(document).on('click', '#save-category', this.saveCategory.bind(this));
            $(document).on('click', '.modal-close', this.closeModal.bind(this));
            
            // Toggle full answer
            $(document).on('click', '.toggle-full-answer', this.toggleFullAnswer.bind(this));
            
            // Twitter share buttons
            $(document).on('click', '.twitter-share-admin-btn', this.handleTwitterShare.bind(this));
            
            // Keyboard navigation for modals
            $(document).on('keydown', this.handleModalKeydown.bind(this));
        }
        
        showResponseModal(e) {
            e.preventDefault();
            const button = $(e.target);
            const messageId = button.data('message-id');
            const messageRow = button.closest('tr');
            
            // Get message data from the row
            const senderName = messageRow.find('.column-sender strong').text();
            const messageContent = messageRow.find('.column-message .message-content').clone();
            const messageDate = messageRow.find('.column-date').text();
            
            // Convert image previews to clickable links that open in new tabs
            messageContent.find('.attachment-preview img').each(function() {
                const $img = $(this);
                const src = $img.attr('src');
                const alt = $img.attr('alt');
                
                // Replace image with clickable link
                const $link = $('<a>', {
                    href: src,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    text: '🖼️ ' + alt + ' (انقر للفتح في تبويب جديد)',
                    style: 'color: #0073aa; text-decoration: none; display: inline-block; margin: 5px 0; padding: 5px 10px; border: 1px solid #ddd; border-radius: 3px; background: #f9f9f9;'
                });
                
                $img.closest('.attachment-item').replaceWith($link);
            });
            
            // Populate modal with message data
            const modal = $('#am-response-modal');
            modal.find('.am-message-text').html(messageContent.html());
            modal.find('.am-sender').text(senderName);
            modal.find('.am-date').text(messageDate);
            modal.find('input[name="message_id"]').val(messageId);
            
            // Reset form
            modal.find('#am-response-form')[0].reset();
            modal.find('input[name="response_type"][value="short"]').prop('checked', true);
            this.toggleResponseType({target: modal.find('input[name="response_type"][value="short"]')[0]});
            
            // Clear any previous messages
            modal.find('.am-response-messages').empty();
            
            // Show modal
            this.openModal(modal);
            
            // Focus on editor if available, otherwise textarea
            setTimeout(() => {
                if (typeof tinymce !== 'undefined' && tinymce.get('am-short-response')) {
                    tinymce.get('am-short-response').focus();
                } else {
                    modal.find('textarea[name="short_response"]').focus();
                }
            }, 300);
        }
        
        showEditModal(e) {
            e.preventDefault();
            const button = $(e.target);
            const messageId = button.data('message-id');
            const responseId = button.data('response-id');
            const messageRow = button.closest('tr');
            
            // Get message data from the row
            const senderName = messageRow.find('.column-sender strong').text();
            const messageContent = messageRow.find('.column-message .message-content').clone();
            const messageDate = messageRow.find('.column-date').text();
            
            // Convert image previews to clickable links that open in new tabs
            messageContent.find('.attachment-preview img').each(function() {
                const $img = $(this);
                const src = $img.attr('src');
                const alt = $img.attr('alt');
                
                // Replace image with clickable link
                const $link = $('<a>', {
                    href: src,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    text: '🖼️ ' + alt + ' (انقر للفتح في تبويب جديد)',
                    style: 'color: #0073aa; text-decoration: none; display: inline-block; margin: 5px 0; padding: 5px 10px; border: 1px solid #ddd; border-radius: 3px; background: #f9f9f9;'
                });
                
                $img.closest('.attachment-item').replaceWith($link);
            });
            
            // Get existing answer data
            const answerPreview = messageRow.find('.answer-preview');
            let existingAnswer = '';
            let responseType = 'short';
            let postId = '';
            
            if (answerPreview.find('.short-answer').length) {
                existingAnswer = answerPreview.find('.short-answer').text().trim();
                responseType = 'short';
            } else if (answerPreview.find('.post-answer').length) {
                responseType = 'post';
                // Extract post ID from the link if needed
                const postLink = answerPreview.find('.post-answer a');
                if (postLink.length) {
                    const href = postLink.attr('href');
                    const matches = href.match(/(\?|&)p=(\d+)/);
                    if (matches) {
                        postId = matches[2];
                    }
                }
            }
            
            // Populate modal with message data
            const modal = $('#am-edit-modal');
            modal.find('.am-message-text').html(messageContent.html());
            modal.find('.am-sender').text(senderName);
            modal.find('.am-date').text(messageDate);
            modal.find('input[name="message_id"]').val(messageId);
            modal.find('input[name="response_id"]').val(responseId);
            
            // Set response type
            modal.find(`input[name="edit_response_type"][value="${responseType}"]`).prop('checked', true);
            
            // Set existing content
            if (responseType === 'short') {
                // Set editor content
                if (typeof tinymce !== 'undefined' && tinymce.get('am-edit-short-response')) {
                    tinymce.get('am-edit-short-response').setContent(existingAnswer);
                } else {
                    modal.find('textarea[name="edit_short_response"]').val(existingAnswer);
                }
            } else {
                modal.find('select[name="edit_post_id"]').val(postId);
            }
            
            this.toggleEditResponseType({target: modal.find(`input[name="edit_response_type"][value="${responseType}"]`)[0]});
            
            // Clear any previous messages
            modal.find('.am-edit-messages').empty();
            
            // Show modal
            this.openModal(modal);
        }
        
        openModal(modal) {
            modal.show();
            $('body').addClass('modal-open');
            
            // Trap focus in modal
            const focusableElements = modal.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            const firstFocusable = focusableElements.first();
            const lastFocusable = focusableElements.last();
            
            firstFocusable.focus();
            
            modal.data('firstFocusable', firstFocusable);
            modal.data('lastFocusable', lastFocusable);
        }
        
        closeModal(e) {
            if (e) {
                e.preventDefault();
            }
            
            $('.am-modal').hide();
            $('body').removeClass('modal-open');
            
            // Reset any TinyMCE editors
            if (typeof tinymce !== 'undefined') {
                tinymce.get('am-short-response')?.setContent('');
                tinymce.get('am-edit-short-response')?.setContent('');
            }
        }
        
        handleModalKeydown(e) {
            const modal = $('.am-modal:visible');
            if (!modal.length) return;
            
            // Close on Escape
            if (e.keyCode === 27) {
                this.closeModal();
                return;
            }
            
            // Trap focus with Tab
            if (e.keyCode === 9) {
                const firstFocusable = modal.data('firstFocusable');
                const lastFocusable = modal.data('lastFocusable');
                
                if (e.shiftKey && document.activeElement === firstFocusable[0]) {
                    e.preventDefault();
                    lastFocusable.focus();
                } else if (!e.shiftKey && document.activeElement === lastFocusable[0]) {
                    e.preventDefault();
                    firstFocusable.focus();
                }
            }
        }
        
        toggleResponseType(e) {
            const responseType = $(e.target).val();
            const modal = $(e.target).closest('.am-modal');
            
            if (responseType === 'short') {
                modal.find('.am-short-response-section').show();
                modal.find('.am-post-response-section').hide();
            } else {
                modal.find('.am-short-response-section').hide();
                modal.find('.am-post-response-section').show();
            }
        }
        
        handleFormSubmit(e) {
            e.preventDefault();
            
            const form = $(e.target);
            const formId = form.attr('id');
            const responseType = form.find('input[name="response_type"]:checked, input[name="edit_response_type"]:checked').val();
            
            // Get content from TinyMCE editor and put it in the form
            if (responseType === 'short') {
                if (formId === 'am-response-form' && typeof tinymce !== 'undefined' && tinymce.get('am-short-response')) {
                    form.find('textarea[name="short_response"]').val(tinymce.get('am-short-response').getContent());
                } else if (formId === 'am-edit-form' && typeof tinymce !== 'undefined' && tinymce.get('am-edit-short-response')) {
                    form.find('textarea[name="edit_short_response"]').val(tinymce.get('am-edit-short-response').getContent());
                }
            }
            
            // Basic validation
            const messagesDiv = form.find('.am-response-messages, .am-edit-messages');
            
            if (responseType === 'short') {
                const content = formId === 'am-response-form' ? 
                    form.find('textarea[name="short_response"]').val() : 
                    form.find('textarea[name="edit_short_response"]').val();
                    
                if (!content.trim()) {
                    this.showMessage(messagesDiv, anonymousMessagesAdmin.strings.enterResponse, 'error');
                    return;
                }
            }
            
            if (responseType === 'post') {
                const postId = formId === 'am-response-form' ? 
                    form.find('select[name="post_id"]').val() : 
                    form.find('select[name="edit_post_id"]').val();
                    
                if (!postId) {
                    this.showMessage(messagesDiv, anonymousMessagesAdmin.strings.selectPost, 'error');
                    return;
                }
            }
            
            // Show loading state
            const submitButton = $('.am-modal:visible .am-modal-footer .button-primary');
            const originalText = submitButton.text();
            submitButton.text(anonymousMessagesAdmin.strings.submitting || 'Submitting...').prop('disabled', true);
            
            // Submit the form normally
            setTimeout(() => {
                form[0].submit();
            }, 100);
        }
        
        async updateMessageStatus(e) {
            const select = $(e.target);
            const messageId = select.data('message-id');
            const newStatus = select.val();
            const originalStatus = select.data('original-status') || select.find('option:selected').data('original');
            
            // Store original status if not already stored
            if (!originalStatus) {
                select.data('original-status', select.find('option[selected]').val() || 'pending');
            }
            
            try {
                const response = await $.ajax({
                    url: anonymousMessagesAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'am_update_message_status',
                        message_id: messageId,
                        status: newStatus,
                        nonce: anonymousMessagesAdmin.nonce
                    }
                });
                
                if (response.success) {
                    // Update visual indicators
                    const messageRow = $('#message-' + messageId);
                    messageRow.removeClass('featured-message');
                    
                    if (newStatus === 'featured') {
                        messageRow.addClass('featured-message');
                    }
                    
                    // Show success notification
                    this.showNotification(response.data.message, 'success');
                } else {
                    // Revert to original status
                    select.val(originalStatus);
                    this.showNotification(response.data.message, 'error');
                }
                
            } catch (error) {
                // Revert to original status
                select.val(originalStatus);
                this.showNotification(anonymousMessagesAdmin.strings.error, 'error');
            }
        }
        
        async deleteMessage(e) {
            e.preventDefault();
            
            if (!confirm(anonymousMessagesAdmin.strings.confirmDelete)) {
                return;
            }
            
            const button = $(e.target);
            const messageId = button.data('message-id');
            const messageRow = $('#message-' + messageId);
            
            try {
                const response = await $.ajax({
                    url: anonymousMessagesAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'am_delete_message',
                        message_id: messageId,
                        nonce: anonymousMessagesAdmin.nonce
                    }
                });
                
                if (response.success) {
                    messageRow.fadeOut(300, function() {
                        $(this).remove();
                    });
                    this.showNotification(response.data.message, 'success');
                } else {
                    this.showNotification(response.data.message, 'error');
                }
                
            } catch (error) {
                this.showNotification(anonymousMessagesAdmin.strings.error, 'error');
            }
        }
        
        async addCategory(e) {
            e.preventDefault();
            
            const form = $(e.target);
            const name = form.find('input[name="name"]').val().trim();
            const description = form.find('textarea[name="description"]').val().trim();
            const messagesDiv = form.find('.form-messages');
            
            if (!name) {
                this.showMessage(messagesDiv, anonymousMessagesAdmin.strings.categoryNameRequired || 'Category name is required.', 'error');
                return;
            }
            
            const submitButton = form.find('button[type="submit"]');
            const originalText = submitButton.text();
            submitButton.text(anonymousMessagesAdmin.strings.loading).prop('disabled', true);
            
            try {
                const response = await $.ajax({
                    url: anonymousMessagesAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'am_create_category',
                        name: name,
                        description: description,
                        nonce: anonymousMessagesAdmin.nonce
                    }
                });
                
                if (response.success) {
                    this.showMessage(messagesDiv, response.data.message, 'success');
                    form[0].reset();
                    
                    // Reload page to show new category
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    this.showMessage(messagesDiv, response.data.message, 'error');
                }
                
            } catch (error) {
                this.showMessage(messagesDiv, anonymousMessagesAdmin.strings.error, 'error');
            }
            
            submitButton.text(originalText).prop('disabled', false);
        }
        
        showEditCategoryModal(e) {
            e.preventDefault();
            
            const button = $(e.target);
            const categoryId = button.data('category-id');
            const categoryName = button.data('category-name');
            const categoryDescription = button.data('category-description');
            
            const modal = $('#edit-category-modal');
            modal.find('#edit_category_id').val(categoryId);
            modal.find('#edit_category_name').val(categoryName);
            modal.find('#edit_category_description').val(categoryDescription);
            
            modal.show();
        }
        
        async saveCategory(e) {
            e.preventDefault();
            
            const modal = $('#edit-category-modal');
            const form = modal.find('#edit-category-form');
            const categoryId = form.find('#edit_category_id').val();
            const name = form.find('#edit_category_name').val().trim();
            const description = form.find('#edit_category_description').val().trim();
            const messagesDiv = form.find('.form-messages');
            
            if (!name) {
                this.showMessage(messagesDiv, anonymousMessagesAdmin.strings.categoryNameRequired || 'Category name is required.', 'error');
                return;
            }
            
            const saveButton = $(e.target);
            const originalText = saveButton.text();
            saveButton.text(anonymousMessagesAdmin.strings.loading).prop('disabled', true);
            
            try {
                // Note: This would need an update_category action in the PHP
                const response = await $.ajax({
                    url: anonymousMessagesAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'am_update_category',
                        category_id: categoryId,
                        name: name,
                        description: description,
                        nonce: anonymousMessagesAdmin.nonce
                    }
                });
                
                if (response.success) {
                    this.showMessage(messagesDiv, response.data.message, 'success');
                    
                    // Close modal and reload
                    setTimeout(() => {
                        modal.hide();
                        window.location.reload();
                    }, 1500);
                } else {
                    this.showMessage(messagesDiv, response.data.message, 'error');
                }
                
            } catch (error) {
                this.showMessage(messagesDiv, anonymousMessagesAdmin.strings.error, 'error');
            }
            
            saveButton.text(originalText).prop('disabled', false);
        }
        
        async deleteCategory(e) {
            e.preventDefault();
            
            if (!confirm(anonymousMessagesAdmin.strings.confirmDeleteCategory)) {
                return;
            }
            
            const button = $(e.target);
            const categoryId = button.data('category-id');
            const categoryRow = $('#category-' + categoryId);
            
            try {
                const response = await $.ajax({
                    url: anonymousMessagesAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'am_delete_category',
                        category_id: categoryId,
                        nonce: anonymousMessagesAdmin.nonce
                    }
                });
                
                if (response.success) {
                    categoryRow.fadeOut(300, function() {
                        $(this).remove();
                    });
                    this.showNotification(response.data.message, 'success');
                } else {
                    this.showNotification(response.data.message, 'error');
                }
                
            } catch (error) {
                this.showNotification(anonymousMessagesAdmin.strings.error, 'error');
            }
        }
        
        closeModal(e) {
            e.preventDefault();
            $(e.target).closest('.category-modal').hide();
        }
        

        
        toggleEditResponseType(e) {
            const responseType = $(e.target).val();
            const modal = $(e.target).closest('.am-modal');
            
            if (responseType === 'short') {
                modal.find('.am-edit-short-response-section').show();
                modal.find('.am-edit-post-response-section').hide();
            } else {
                modal.find('.am-edit-short-response-section').hide();
                modal.find('.am-edit-post-response-section').show();
            }
        }
        
        toggleFullAnswer(e) {
            e.preventDefault();
            const $this = $(e.target);
            const $fullAnswer = $this.siblings('.full-answer');
            
            if ($fullAnswer.is(':visible')) {
                $fullAnswer.hide();
                $this.text(anonymousMessagesAdmin.strings.showFullAnswer);
            } else {
                $fullAnswer.show();
                $this.text(anonymousMessagesAdmin.strings.hideFullAnswer);
            }
        }

        
        showMessage(container, message, type) {
            const alertClass = type === 'success' ? 'notice-success' : 'notice-error';
            const html = `<div class="notice ${alertClass}"><p>${message}</p></div>`;
            
            container.html(html);
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(() => {
                    container.find('.notice').fadeOut();
                }, 3000);
            }
        }
        
        async updateMessageCategory(e) {
            const select = $(e.target);
            const messageId = select.data('message-id');
            const newCategoryId = select.val();
            const originalValue = select.data('original-value') || '';
            
            // Store original value if not already stored
            if (select.data('original-value') === undefined) {
                select.data('original-value', select.find('option:selected').data('original') || '');
            }
            
            try {
                const response = await $.ajax({
                    url: anonymousMessagesAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'am_update_message_category',
                        message_id: messageId,
                        category_id: newCategoryId,
                        nonce: anonymousMessagesAdmin.nonce
                    }
                });
                
                if (response.success) {
                    // Update the stored original value
                    select.data('original-value', newCategoryId);
                    
                    // Show success notification
                    this.showNotification(response.data.message, 'success');
                } else {
                    // Revert to original value
                    select.val(originalValue);
                    this.showNotification(response.data.message, 'error');
                }
                
            } catch (error) {
                // Revert to original value
                select.val(originalValue);
                this.showNotification(anonymousMessagesAdmin.strings.error, 'error');
            }
        }

        showNotification(message, type) {
            const alertClass = type === 'success' ? 'notice-success' : 'notice-error';
            const notification = $(`<div class="notice ${alertClass} is-dismissible"><p>${message}</p></div>`);
            
            $('.wrap h1').after(notification);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
        
        handleTwitterShare(e) {
            e.preventDefault();
            
            const button = $(e.target).closest('.twitter-share-admin-btn');
            const questionText = button.data('question-text');
            const answerText = button.data('answer-text');
            
            // Format the Twitter content
            const twitterContent = this.formatTwitterContent(questionText, answerText);
            
            // Generate deep link URL to frontend
            const deepLinkUrl = this.generateDeepLink(questionText);
            
            // Create Twitter share URL
            const twitterUrl = this.createTwitterShareUrl(twitterContent, deepLinkUrl);
            
            // Open Twitter share window
            this.openTwitterWindow(twitterUrl);
        }
        
        formatTwitterContent(question, answer) {
            // Get localized prefixes
            const questionPrefix = anonymousMessagesAdmin.strings.questionPrefix || 'q:';
            const answerPrefix = anonymousMessagesAdmin.strings.answerPrefix || 'a:';
            
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
            // Generate link to the frontend page with search parameter
            let frontendUrl = window.location.origin;
            
            // Try to determine the frontend URL - this may need to be configurable
            // For now, assume it's the home page
            const url = new URL(frontendUrl);
            url.searchParams.set('search', questionText.substring(0, 50)); // Limit to 50 chars
            
            return url.toString();
        }
        
        createTwitterShareUrl(text, url) {
            const hashtag = anonymousMessagesAdmin.twitterHashtag || 'QandA';
            
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
    }
    
    // Initialize when document is ready
    $(document).ready(function() {
        new AnonymousMessagesAdmin();
    });
    
})(jQuery);