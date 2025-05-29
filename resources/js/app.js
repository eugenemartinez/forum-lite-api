document.addEventListener('DOMContentLoaded', () => {
    const landingContainer = document.getElementById('landing-container');
    const apiIcon = document.getElementById('api-icon');
    const mainHeading = document.getElementById('main-heading');
    const subHeading = document.getElementById('sub-heading');
    const docsLinkParagraph = document.getElementById('docs-link-paragraph');
    const interactiveButton = document.getElementById('interactive-button');
    const interactiveText = document.getElementById('interactive-text');

    // Animate container on load
    if (landingContainer) {
        setTimeout(() => {
            landingContainer.classList.remove('scale-95', 'opacity-0');
            landingContainer.classList.add('scale-100', 'opacity-100');
        }, 50);
    }

    // Staggered animation for elements
    const elementsToAnimate = [
        { el: apiIcon, delay: 200, transform: true, rotate: true, scale: true },
        { el: mainHeading, delay: 400, transform: true },
        { el: subHeading, delay: 600, transform: true },
        { el: docsLinkParagraph, delay: 800, transform: true },
        { el: interactiveButton, delay: 1000, transform: true, scaleButton: true }
    ];

    elementsToAnimate.forEach(item => {
        if (item.el) {
            setTimeout(() => {
                item.el.classList.remove('opacity-0');
                if (item.transform) {
                    item.el.classList.remove('-translate-y-5', '-translate-y-3');
                    item.el.classList.add('translate-y-0');
                }
                if (item.rotate) {
                    item.el.classList.remove('-rotate-12');
                    item.el.classList.add('rotate-0');
                }
                if (item.scale) {
                    item.el.classList.remove('scale-50');
                    item.el.classList.add('scale-100');
                }
                if (item.scaleButton) {
                    item.el.classList.remove('scale-75');
                    item.el.classList.add('scale-100');
                }
            }, item.delay);
        }
    });

    // Button interaction
    if (interactiveButton && interactiveText) {
        interactiveButton.addEventListener('click', () => {
            if (interactiveText.classList.contains('opacity-0')) {
                interactiveText.classList.remove('opacity-0', 'h-0');
                interactiveText.classList.add('opacity-100', 'h-auto', 'py-2');
                interactiveButton.textContent = 'Awesome!';
            } else {
                interactiveText.classList.add('opacity-0');
                setTimeout(() => {
                    interactiveText.classList.remove('h-auto', 'py-2');
                    interactiveText.classList.add('h-0');
                }, 700); // Match transition duration
                interactiveButton.textContent = 'Discover More';
            }
        });
    }
});
