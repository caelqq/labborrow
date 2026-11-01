let theme = 'light';
try {
    theme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
} catch (e) {}
document.documentElement.setAttribute('data-theme', theme);

document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('theme-toggle');

    function switchTheme() {
        theme = theme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', theme);
        try {
            localStorage.setItem('theme', theme);
        } catch (e) {}
    }

    button.addEventListener('click', () => {
        // Browsers without startViewTransition just switch with no wave
        if (!document.startViewTransition) {
            switchTheme();
            return;
        }
        const box = button.getBoundingClientRect();
        const x = box.left + box.width / 2;
        const y = box.top + box.height / 2;
        const radius = Math.hypot(
            Math.max(x, window.innerWidth - x),
            Math.max(y, window.innerHeight - y)
        );
        const transition = document.startViewTransition(switchTheme);
        transition.ready.then(() => {
            document.documentElement.animate(
                {
                    clipPath: [
                        `circle(0px at ${x}px ${y}px)`,
                        `circle(${radius}px at ${x}px ${y}px)`,
                    ],
                },
                {
                    duration: 700,
                    easing: 'ease-in-out',
                    pseudoElement: '::view-transition-new(root)',
                }
            );
        });
    });
});