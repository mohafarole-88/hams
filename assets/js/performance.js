// Performance optimization script for HAMS
(function() {
    'use strict';
    
    // Page loading optimization
    document.addEventListener('DOMContentLoaded', function() {
        // Remove body visibility hidden after load
        document.body.style.visibility = 'visible';
        
        // Preload critical pages
        const criticalPages = ['index.php', 'aid_recipients.php', 'aid_delivery.php', 'supplies.php'];
        criticalPages.forEach(function(page) {
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = page;
            document.head.appendChild(link);
        });
    });
    
    // Navigation loading indicators
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href$=".php"]');
        if (link && !link.href.includes('#') && !link.target) {
            e.preventDefault();
            
            // Show loading state
            const originalText = link.innerHTML;
            link.innerHTML = '<span style="opacity:0.7">Loading...</span>';
            link.style.pointerEvents = 'none';
            
            // Navigate after short delay to show feedback
            setTimeout(function() {
                window.location.href = link.href;
            }, 100);
        }
    });
    
    // Form submission optimization
    document.addEventListener('submit', function(e) {
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        
        if (submitBtn && !submitBtn.disabled) {
            const originalText = submitBtn.innerHTML || submitBtn.value;
            
            if (submitBtn.tagName === 'BUTTON') {
                submitBtn.innerHTML = '<span style="display:inline-block;width:12px;height:12px;border:2px solid currentColor;border-radius:50%;border-top-color:transparent;animation:spin 0.8s linear infinite;margin-right:6px;"></span>Processing...';
            } else {
                submitBtn.value = 'Processing...';
            }
            
            submitBtn.disabled = true;
            
            // Re-enable after timeout as fallback
            setTimeout(function() {
                if (submitBtn.tagName === 'BUTTON') {
                    submitBtn.innerHTML = originalText;
                } else {
                    submitBtn.value = originalText;
                }
                submitBtn.disabled = false;
            }, 10000);
        }
    });
    
    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        body { visibility: hidden; }
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    `;
    document.head.appendChild(style);
})();
