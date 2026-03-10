export class NfcScanner {
    constructor(inputId) {
        this.input = document.getElementById(inputId);
        if (!this.input) {
            console.error(`NfcScanner: Input with ID "${inputId}" not found.`);
            return;
        }

        this.init();
    }

    init() {
        // Ensure initial focus
        this.input.focus();

        // Keep focus as much as possible
        document.addEventListener('click', () => {
             // Only refocus if the click wasn't on another input or interactive element
             if (document.activeElement !== this.input && 
                 document.activeElement.tagName !== 'INPUT' && 
                 document.activeElement.tagName !== 'TEXTAREA' &&
                 document.activeElement.tagName !== 'A' &&
                 document.activeElement.tagName !== 'BUTTON') {
                this.input.focus();
             }
        });

        this.input.addEventListener('blur', () => {
            // Re-focus after a short delay, unless another element stole it intentionally
            setTimeout(() => {
                if (document.activeElement === document.body) {
                    this.input.focus();
                }
            }, 10);
        });

        // Listen for "Enter" key to detect end of scan
        this.input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault(); // Prevent form submission if inside a form, we handle it manually or let the event bubble
                this.handleScan();
            }
        });
    }

    handleScan() {
        const value = this.input.value.trim();
        if (value) {
            // Dispatch custom event
            const event = new CustomEvent('nfc:scan', {
                detail: { id: value },
                bubbles: true,
                cancelable: true
            });
            this.input.dispatchEvent(event);
            
            // Clear input for next scan
            this.input.value = '';
        }
    }
}
