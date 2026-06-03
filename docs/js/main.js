/**
 * Main JavaScript for Akindutire Authorization Documentation
 */

// Tab switching functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tab functionality
    initializeTabs();
});

/**
 * Initialize tab switching for code examples
 */
function initializeTabs() {
    const tabButtons = document.querySelectorAll('.tab-btn');

    if (tabButtons.length === 0) {
        return; // No tabs on this page
    }

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');

            // Remove active class from all buttons and content
            tabButtons.forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            // Add active class to clicked button and corresponding content
            this.classList.add('active');
            const activeContent = document.getElementById(targetTab);
            if (activeContent) {
                activeContent.classList.add('active');
            }
        });
    });
}

/**
 * Smooth scroll for anchor links
 */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const href = this.getAttribute('href');

        // Skip if it's just "#"
        if (href === '#') {
            return;
        }

        e.preventDefault();
        const target = document.querySelector(href);

        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

/**
 * Mobile navigation toggle (for future enhancement)
 */
function initializeMobileNav() {
    const navToggle = document.querySelector('.nav-toggle');
    const navLinks = document.querySelector('.nav-links');

    if (navToggle && navLinks) {
        navToggle.addEventListener('click', function() {
            navLinks.classList.toggle('active');
        });
    }
}

// Initialize mobile navigation when needed
if (window.innerWidth <= 768) {
    initializeMobileNav();
}

/**
 * Copy code to clipboard functionality
 */
document.querySelectorAll('pre code').forEach(block => {
    // Create copy button
    const button = document.createElement('button');
    button.className = 'copy-button';
    button.textContent = 'Copy';
    button.style.cssText = `
        position: absolute;
        top: 8px;
        right: 8px;
        padding: 4px 8px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 4px;
        color: white;
        cursor: pointer;
        font-size: 12px;
        opacity: 0;
        transition: opacity 0.2s;
    `;

    // Make pre element relative for absolute positioning
    const pre = block.parentElement;
    pre.style.position = 'relative';

    // Show button on hover
    pre.addEventListener('mouseenter', () => {
        button.style.opacity = '1';
    });

    pre.addEventListener('mouseleave', () => {
        button.style.opacity = '0';
    });

    // Copy functionality
    button.addEventListener('click', async () => {
        const code = block.textContent;

        try {
            await navigator.clipboard.writeText(code);
            button.textContent = 'Copied!';
            setTimeout(() => {
                button.textContent = 'Copy';
            }, 2000);
        } catch (err) {
            console.error('Failed to copy code:', err);
            button.textContent = 'Failed';
            setTimeout(() => {
                button.textContent = 'Copy';
            }, 2000);
        }
    });

    pre.appendChild(button);
});

/**
 * Highlight current section in navigation
 */
function highlightCurrentSection() {
    const sections = document.querySelectorAll('h2[id]');
    const navLinks = document.querySelectorAll('.nav-links a');

    if (sections.length === 0) {
        return;
    }

    window.addEventListener('scroll', () => {
        let current = '';

        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;

            if (window.pageYOffset >= sectionTop - 60) {
                current = section.getAttribute('id');
            }
        });

        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === '#' + current) {
                link.classList.add('active');
            }
        });
    });
}

// Initialize section highlighting if on documentation page
if (document.querySelector('.doc-content')) {
    highlightCurrentSection();
}
