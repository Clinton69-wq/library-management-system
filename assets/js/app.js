/**
 * ApexLib Application Interactive Utilities
 * OTP Digit Handler and Clipboard Copy
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize Bootstrap Tooltips if available
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }

    // 2. Multi-Segment OTP Input Auto-Advance & Paste Handler
    const otpInputs = document.querySelectorAll('.otp-digit');
    if (otpInputs.length > 0) {
        // Focus first input automatically
        otpInputs[0].focus();

        otpInputs.forEach((input, index) => {
            // Keyup handler for auto navigation
            input.addEventListener('keyup', (e) => {
                const key = e.key;
                if (key === 'Backspace' || key === 'Delete') {
                    if (index > 0 && input.value.length === 0) {
                        otpInputs[index - 1].focus();
                    }
                } else if (/^[0-9]$/.test(key)) {
                    if (index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                }
                updateFullOtpHiddenInput();
            });

            // Prevent non-numeric characters
            input.addEventListener('input', (e) => {
                input.value = input.value.replace(/[^0-9]/g, '');
                updateFullOtpHiddenInput();
            });

            // Handle Paste event on any input box
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = (e.clipboardData || window.clipboardData).getData('text').trim();
                const digits = pastedData.replace(/[^0-9]/g, '').slice(0, 6);
                
                if (digits.length > 0) {
                    digits.split('').forEach((char, i) => {
                        if (otpInputs[i]) {
                            otpInputs[i].value = char;
                        }
                    });
                    const targetIndex = Math.min(digits.length, otpInputs.length - 1);
                    otpInputs[targetIndex].focus();
                    updateFullOtpHiddenInput();
                }
            });
        });

        function updateFullOtpHiddenInput() {
            const hiddenInput = document.getElementById('full_otp_code');
            if (hiddenInput) {
                let code = '';
                otpInputs.forEach(inp => code += inp.value);
                hiddenInput.value = code;
            }
        }
    }

    // 3. Copy to Clipboard Utility
    window.copyToClipboard = function(text, elementId = null) {
        navigator.clipboard.writeText(text).then(() => {
            if (elementId) {
                const el = document.getElementById(elementId);
                if (el) {
                    const originalHtml = el.innerHTML;
                    el.innerHTML = '<i class="bi bi-check-lg text-success"></i> Copied!';
                    setTimeout(() => {
                        el.innerHTML = originalHtml;
                    }, 2000);
                }
            } else {
                alert('Copied to clipboard: ' + text);
            }
        }).catch(err => {
            console.error('Clipboard copy failed:', err);
        });
    };
});
