import { Controller } from '@hotwired/stimulus';
import GLightbox from 'glightbox';

// ref: https://stackoverflow.com/a/53116778
function randomId(prefix) {
    const rand = Date.now().toString(36) + Math.random().toString(36).substring(2, 10);

    if (!prefix) {
        prefix = '';
    }

    return `${prefix}${rand}`;
}

export default class extends Controller {
    connect() {
        if (!this.element.id) {
            this.element.id = randomId(`${this.identifier}-thumb-`);
        }

        if (!this.element.dataset.type) {
            this.element.dataset.type = 'image';
        }

        this.lightbox = GLightbox({
            selector: `#${this.element.id}`,
            openEffect: 'none',
            closeEffect: 'none',
            slideEffect: 'none',
        });
    }

    disconnect() {
        this.lightbox.destroy();
    }
}
