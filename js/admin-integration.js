/**
 * OJG Herbal Admin Panel Integration
 * This script integrates frontend forms with the admin panel while maintaining existing webhook functionality
 */

class AdminPanelIntegration {
    constructor() {
        this.adminApiUrl = '/backend/api/form-handler.php';
        this.initialized = false;
        this.debug = true; // Set to false in production
    }

    /**
     * Initialize the admin panel integration
     */
    async initialize() {
        if (this.initialized) return;
        
        this.log('🔧 Initializing Admin Panel Integration...');
        
        // Override existing form submission methods
        this.overrideWebhookManager();
        this.overrideFormSubmissions();
        
        this.initialized = true;
        this.log('✅ Admin Panel Integration initialized successfully');
    }

    /**
     * Override WebhookManager to also send data to admin panel
     */
    overrideWebhookManager() {
        if (!window.WebhookManager) {
            this.log('⚠️ WebhookManager not found, will override when available');
            return;
        }

        const originalSubmitForm = window.WebhookManager.submitForm;
        const self = this;

        window.WebhookManager.submitForm = async function(endpoint, data, options = {}) {
            self.log(`📤 Intercepting WebhookManager.submitForm for endpoint: ${endpoint}`);
            
            try {
                // Parse the data to determine form type
                const parsedData = typeof data === 'string' ? JSON.parse(data) : data;
                const formType = self.determineFormType(endpoint, parsedData);
                
                // Send to admin panel first
                if (formType) {
                    await self.sendToAdminPanel(formType, parsedData);
                }
                
                // Then send to original webhook
                return await originalSubmitForm.call(this, endpoint, data, options);
                
            } catch (error) {
                self.log(`❌ Error in WebhookManager override: ${error.message}`);
                // Still try to send to original webhook even if admin panel fails
                return await originalSubmitForm.call(this, endpoint, data, options);
            }
        };

        this.log('✅ WebhookManager.submitForm overridden successfully');
    }

    /**
     * Override common form submission patterns
     */
    overrideFormSubmissions() {
        // Override fetch calls to webhook endpoints
        const originalFetch = window.fetch;
        const self = this;

        window.fetch = async function(url, options = {}) {
            // Check if this is a webhook call we should intercept
            if (self.isWebhookUrl(url) && options.method === 'POST' && options.body) {
                self.log(`📤 Intercepting fetch call to: ${url}`);
                
                try {
                    const data = typeof options.body === 'string' ? JSON.parse(options.body) : options.body;
                    const formType = self.determineFormTypeFromUrl(url, data);
                    
                    if (formType) {
                        await self.sendToAdminPanel(formType, data);
                    }
                } catch (error) {
                    self.log(`⚠️ Error intercepting fetch: ${error.message}`);
                }
            }
            
            // Always call original fetch
            return await originalFetch.call(this, url, options);
        };

        this.log('✅ Fetch override installed successfully');
    }

    /**
     * Determine form type from endpoint
     */
    determineFormType(endpoint, data) {
        const endpointMap = {
            'pcosLeads': 'assessment',
            'acneLeads': 'assessment',
            'weightLeads': 'assessment',
            'salesLeads': 'sales',
            'contactForm': 'contact'
        };

        if (endpointMap[endpoint]) {
            return endpointMap[endpoint];
        }

        // Try to determine from data structure
        if (data.assessment_data || data.questions || data.symptoms) {
            return 'assessment';
        }
        
        if (data.transaction_id || data.amount || data.payment_status) {
            return 'sales';
        }
        
        if (data.message && data.email && !data.assessment_data) {
            return 'contact';
        }

        return null;
    }

    /**
     * Determine form type from URL
     */
    determineFormTypeFromUrl(url, data) {
        if (url.includes('pcos-leads') || url.includes('acne-leads') || url.includes('weight-leads')) {
            return 'assessment';
        }
        
        if (url.includes('sales-leads') || url.includes('sales')) {
            return 'sales';
        }
        
        if (url.includes('contact')) {
            return 'contact';
        }

        return this.determineFormType('', data);
    }

    /**
     * Check if URL is a webhook URL we should intercept
     */
    isWebhookUrl(url) {
        const webhookPatterns = [
            'n8n.ai20.city',
            'webhook',
            'pcos-leads',
            'acne-leads',
            'weight-leads',
            'sales-leads',
            'contact-form'
        ];

        return webhookPatterns.some(pattern => url.includes(pattern));
    }

    /**
     * Send data to admin panel
     */
    async sendToAdminPanel(formType, data) {
        try {
            this.log(`📊 Sending ${formType} data to admin panel...`);
            
            // Prepare data for admin panel
            const adminData = {
                form_type: formType,
                ...data,
                timestamp: new Date().toISOString(),
                source: 'frontend_integration'
            };

            const response = await fetch(this.adminApiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(adminData)
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            
            if (result.success) {
                this.log(`✅ Successfully saved ${formType} to admin panel:`, result.data);
                
                // Store the admin panel ID for reference
                if (result.data.assessment_id) {
                    this.storeAssessmentId(result.data.assessment_id);
                }
                
                return result;
            } else {
                throw new Error(result.message || 'Unknown error');
            }

        } catch (error) {
            this.log(`❌ Failed to save ${formType} to admin panel: ${error.message}`);
            // Don't throw error to avoid breaking the original flow
            return null;
        }
    }

    /**
     * Store assessment ID for later use
     */
    storeAssessmentId(assessmentId) {
        try {
            localStorage.setItem('ojg_assessment_id', assessmentId);
            
            // Also update any existing assessment ID fields on the page
            const assessmentInputs = document.querySelectorAll('input[x-model="assessmentId"], input[name="assessment_id"]');
            assessmentInputs.forEach(input => {
                if (!input.value) {
                    input.value = assessmentId;
                    // Trigger change event for Alpine.js
                    input.dispatchEvent(new Event('input'));
                }
            });
            
            this.log(`💾 Stored assessment ID: ${assessmentId}`);
        } catch (error) {
            this.log(`⚠️ Failed to store assessment ID: ${error.message}`);
        }
    }

    /**
     * Get stored assessment ID
     */
    getStoredAssessmentId() {
        try {
            return localStorage.getItem('ojg_assessment_id');
        } catch (error) {
            return null;
        }
    }

    /**
     * Manual form submission method for direct integration
     */
    async submitForm(formType, data) {
        return await this.sendToAdminPanel(formType, data);
    }

    /**
     * Enhanced assessment submission
     */
    async submitAssessment(assessmentData) {
        const enhancedData = {
            ...assessmentData,
            assessment_type: this.detectAssessmentType(),
            page_url: window.location.href,
            referrer: document.referrer,
            user_agent: navigator.userAgent,
            timestamp: new Date().toISOString()
        };

        return await this.submitForm('assessment', enhancedData);
    }

    /**
     * Enhanced sales submission
     */
    async submitSale(salesData) {
        const enhancedData = {
            ...salesData,
            assessment_id: salesData.assessment_id || this.getStoredAssessmentId(),
            page_url: window.location.href,
            referrer: document.referrer,
            user_agent: navigator.userAgent,
            timestamp: new Date().toISOString()
        };

        return await this.submitForm('sales', enhancedData);
    }

    /**
     * Enhanced contact submission
     */
    async submitContact(contactData) {
        const enhancedData = {
            ...contactData,
            page_url: window.location.href,
            referrer: document.referrer,
            user_agent: navigator.userAgent,
            timestamp: new Date().toISOString()
        };

        return await this.submitForm('contact', enhancedData);
    }

    /**
     * Detect assessment type from current page
     */
    detectAssessmentType() {
        const url = window.location.href.toLowerCase();
        
        if (url.includes('pcos')) return 'pcos';
        if (url.includes('acne')) return 'acne';
        if (url.includes('weight')) return 'weight';
        
        return 'general';
    }

    /**
     * Logging utility
     */
    log(message, data = null) {
        if (!this.debug) return;
        
        const timestamp = new Date().toISOString();
        console.log(`[AdminIntegration ${timestamp}] ${message}`, data || '');
    }

    /**
     * Get admin panel statistics (for dashboard widgets)
     */
    async getStats() {
        try {
            const response = await fetch('/backend/api/stats.php');
            if (response.ok) {
                return await response.json();
            }
        } catch (error) {
            this.log(`❌ Failed to get stats: ${error.message}`);
        }
        return null;
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', async () => {
    // Create global instance
    window.AdminPanelIntegration = new AdminPanelIntegration();
    
    // Initialize after a short delay to ensure other scripts are loaded
    setTimeout(async () => {
        await window.AdminPanelIntegration.initialize();
    }, 1000);
});

// Also initialize if WebhookManager becomes available later
document.addEventListener('WebhookManagerReady', async () => {
    if (window.AdminPanelIntegration && !window.AdminPanelIntegration.initialized) {
        await window.AdminPanelIntegration.initialize();
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AdminPanelIntegration;
}