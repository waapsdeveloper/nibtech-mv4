/**
 * V2 Listing Keyboard Navigation JavaScript
 * Handles keyboard shortcuts and input navigation
 */

(function() {
    'use strict';

    /**
     * Move to next/previous input field
     * @param {HTMLElement} currentInput - Current input element
     * @param {string} prefix - ID prefix to match (e.g., 'add_', 'all_min_price_')
     * @param {boolean} moveUp - If true, move to previous; if false, move to next
     */
    window.moveToNextInput = function(currentInput, prefix, moveUp = false) {
        if (!currentInput || !prefix) return;

        const inputs = Array.from(document.querySelectorAll(`input[id^="${prefix}"]`))
            .filter(input => !input.disabled && input.offsetParent !== null); // Only visible, enabled inputs

        const currentIndex = inputs.indexOf(currentInput);
        if (currentIndex === -1) return;

        if (moveUp && currentIndex > 0) {
            inputs[currentIndex - 1].focus();
            inputs[currentIndex - 1].select();
        } else if (!moveUp && currentIndex < inputs.length - 1) {
            inputs[currentIndex + 1].focus();
            inputs[currentIndex + 1].select();
        }
    };

    /**
     * Initialize keyboard navigation for inputs
     */
    function initializeKeyboardNavigation() {
        // Handle Ctrl+Arrow keys for navigation
        document.addEventListener('keydown', function(e) {
            // Check if Ctrl key is pressed and target is an input
            if (!e.ctrlKey || !e.target || e.target.tagName !== 'INPUT') {
                return;
            }

            const input = e.target;
            const inputId = input.id || '';

            // Check if input has onkeydown attribute with moveToNextInput
            // This will be set in the Blade templates
            if (inputId.includes('add_') || 
                inputId.includes('all_min_handler_') || 
                inputId.includes('all_handler_') || 
                inputId.includes('all_min_price_') || 
                inputId.includes('all_price_')) {
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    const prefix = inputId.match(/^([^_]+_[^_]+_?[^_]*_?)/)?.[0] || inputId.split('_').slice(0, -1).join('_') + '_';
                    moveToNextInput(input, prefix, false);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    const prefix = inputId.match(/^([^_]+_[^_]+_?[^_]*_?)/)?.[0] || inputId.split('_').slice(0, -1).join('_') + '_';
                    moveToNextInput(input, prefix, true);
                }
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeKeyboardNavigation);
    } else {
        initializeKeyboardNavigation();
    }

})();

