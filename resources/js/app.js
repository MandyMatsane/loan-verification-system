

import Alpine from 'alpinejs';

window.Alpine = Alpine;

window.theme = () => ({
    theme: localStorage.getItem('theme') || 'light',
    get themeClass() {
        return this.theme === 'dark' ? 'dark' : '';
    },
    toggle() {
        this.theme = this.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('theme', this.theme);
    },
});

Alpine.start();
