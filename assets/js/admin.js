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
            // Message response handling
            $(document).on('click', '.respond-to-message', this.showResponseForm.bind(this));
            $(document).on('click', '.cancel-response', this.hideResponseForm.bind(this));
            $(document).on('submit', '.message-response-form', this.submitResponse.bind(this));
            $(document).on('change', 'input[name="response_type"]', this.toggleResponseType.bind(this));
            
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
            
            // Answer editing
            $(document).on('click', '.edit-answer', this.showEditAnswerForm.bind(this));
            $(document).on('click', '.cancel-edit-answer', this.hideEditAnswerForm.bind(this));
            $(document).on('submit', '.edit-answer-form', this.submitEditAnswer.bind(this));
            $(document).on('change', 'input[name="edit_response_type"]', this.toggleEditResponseType.bind(this));
            
            // Toggle full answer
            $(document).on('click', '.toggle-full-answer', this.toggleFullAnswer.bind(this));
            
            // Twitter share buttons
            $(document).on('click', '.twitter-share-admin-btn', this.handleTwitterShare.bind(this));
        }
        
        showResponseForm(e) {
            e.preventDefault();
            const messageId = $(e.target).data('message-id');
            const responseRow = $('#response-row-' + messageId);
            
            $('.response-row').hide(); // Hide other response forms
            responseRow.show();
            
            // Focus on textarea
            responseRow.find('textarea[name="short_response"]').focus();
        }
        
        hideResponseForm(e) {
            e.preventDefault();
            $(e.target).closest('.response-row').hide();
        }
        
        toggleResponseType(e) {
            const responseType = $(e.target).val();
            const form = $(e.target).closest('.message-response-form');
            
            if (responseType === 'short') {
                form.find('.short-response-section').show();
                form.find('.post-response-section').hide();
            } else {
                form.find('.short-response-section').hide();
                form.find('.post-response-section').show();
            }
        }
        
        async submitResponse(e) {
            e.preventDefault();
            
            const form = $(e.target);
            const messageId = form.data('message-id');
            const responseType = form.find('input[name="response_type"]:checked').val();
            const shortResponse = form.find('textarea[name="short_response"]').val();
            const postId = form.find('select[name="post_id"]').val();
            const messagesDiv = form.find('.response-messages');
            
            // Validation
            if (responseType === 'short' && !shortResponse.trim()) {
                this.showMessage(messagesDiv, 'Please enter a response.', 'error');
                return;
            }
            
            if (responseType === 'post' && !postId) {
                this.showMessage(messagesDiv, 'Please select a post.', 'error');
                return;
            }
            
            // Show loading
            const submitButton = form.find('button[type="submit"]');
            const originalText = submitButton.text();
            submitButton.text(anonymousMessagesAdmin.strings.loading).prop('disabled', true);
            
            try {
                const response = await $.ajax({
                    url: anonymousMessagesAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'am_respond_to_message',
                        message_id: messageId,
                        response_type: responseType,
                        short_response: shortResponse,
                        post_id: postId,
                        nonce: anonymousMessagesAdmin.nonce
                    }
                });
                
                if (response.success) {
                    this.showMessage(messagesDiv, response.data.message, 'success');
                    
                    // Update message status to answered
                    const statusSelect = $('#message-' + messageId).find('.status-select');
                    statusSelect.val('answered').trigger('change');
                    
                    // Hide form after 2 seconds
                    setTimeout(() => {
                        form.closest('.response-row').hide();
                    }, 2000);
                } else {
                    this.showMessage(messagesDiv, response.data.message, 'error');
                }
                
            } catch (error) {
                this.showMessage(messagesDiv, anonymousMessagesAdmin.strings.error, 'error');
            }
            
            // Reset button
            submitButton.text(originalText).prop('disabled', false);
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
                this.showMessage(messagesDiv, 'Category name is required.', 'error');
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
                this.showMessage(messagesDiv, 'Category name is required.', 'error');
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
        
        showEditAnswerForm(e) {
            e.preventDefault();
            const messageId = $(e.target).data('message-id');
            const editRow = $('#edit-answer-row-' + messageId);
            
            $('.edit-answer-row').hide(); // Hide other edit forms
            editRow.show();
            
            // Focus on textarea
            editRow.find('textarea[name="edit_short_response"]').focus();
        }
        
        hideEditAnswerForm(e) {
            e.preventDefault();
            $(e.target).closest('.edit-answer-row').hide();
        }
        
        async submitEditAnswer(e) {
            e.preventDefault();
            
            const form = $(e.target);
            const responseId = form.data('response-id');
            const responseType = form.find('input[name="edit_response_type"]:checked').val();
            const shortResponse = form.find('textarea[name="edit_short_response"]').val();
            const postId = form.find('select[name="edit_post_id"]').val();
            const messagesDiv = form.find('.edit-response-messages');
            
            // Validation
            if (responseType === 'short' && !shortResponse.trim()) {
                this.showMessage(messagesDiv, 'Please enter a response.', 'error');
                return;
            }
            
            if (responseType === 'post' && !postId) {
                this.showMessage(messagesDiv, 'Please select a post.', 'error');
                return;
            }
            
            // Show loading
            const submitButton = form.find('button[type="submit"]');
            const originalText = submitButton.text();
            submitButton.text(anonymousMessagesAdmin.strings.loading).prop('disabled', true);
            
            try {
                const response = await $.ajax({
                    url: anonymousMessagesAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'am_update_response',
                        response_id: responseId,
                        response_type: responseType,
                        short_response: shortResponse,
                        post_id: postId,
                        nonce: anonymousMessagesAdmin.nonce
                    }
                });
                
                if (response.success) {
                    this.showMessage(messagesDiv, response.data.message, 'success');
                    
                    // Hide form after 2 seconds and reload page to show updated answer
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    this.showMessage(messagesDiv, response.data.message, 'error');
                }
                
            } catch (error) {
                this.showMessage(messagesDiv, anonymousMessagesAdmin.strings.error, 'error');
            }
            
            // Reset button
            submitButton.text(originalText).prop('disabled', false);
        }
        
        toggleEditResponseType(e) {
            const responseType = $(e.target).val();
            const form = $(e.target).closest('.edit-answer-form');
            
            if (responseType === 'short') {
                form.find('.edit-short-response-section').show();
                form.find('.edit-post-response-section').hide();
            } else {
                form.find('.edit-short-response-section').hide();
                form.find('.edit-post-response-section').show();
            }
        }
        
        toggleFullAnswer(e) {
            e.preventDefault();
            const $this = $(e.target);
            const $fullAnswer = $this.siblings('.full-answer');
            
            if ($fullAnswer.is(':visible')) {
                $fullAnswer.hide();
                $this.text('Show full answer');
            } else {
                $fullAnswer.show();
                $this.text('Hide full answer');
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