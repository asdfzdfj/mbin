import { Controller } from '@hotwired/stimulus';
import { useThrottle } from 'stimulus-use'
import { fetch, ok } from "../utils/http";
import router from "../utils/routing";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        loading: Boolean,
    };

    static targets = ['container'];
    static throttles = ['show'];

    connect() {
        useThrottle(this, {wait: 500});

        // workaround: give itself a container if it couldn't find one
        // I am not happy with this
        if (!this.hasContainerTarget && this.element.matches('span.preview')) {
            let container = this.createContainerTarget();
            this.element.insertAdjacentElement('beforeend', container);
            console.warn('unable to find container target, creating one for itself at', this.element.lastChild);
        }
    }

    createContainerTarget() {
        let div = document.createElement('div');
        div.classList.add('preview-target', 'hidden');
        div.dataset.previewTarget = 'container';

        return div;
    }

    async retry(event) {
        event.preventDefault();

        this.containerTarget.replaceChildren();
        this.containerTarget.classList.add('hidden');

        await this.show(event);
    }

    async show(event) {
        event.preventDefault();

        if (this.containerTarget.hasChildNodes()) {
            this.containerTarget.classList.toggle('hidden');
            this.containerTarget.replaceChildren();

            return;
        }

        try {
            this.loadingValue = true;

            let previewHtml = await this.fetchEmbed(event.params.url);

            this.containerTarget.innerHTML = previewHtml;
            this.containerTarget.classList.remove('hidden');
            if (event.params.ratio) {
                this.containerTarget
                    .querySelector('.preview')
                    .classList.add('ratio');
            }
            this.loadScripts(previewHtml);
        } catch (e) {
            console.error('preview failed: ', e);
            let failedHtml =
                `<div class="preview">
                    <a class="retry-failed" href="#"
                        data-action="preview#retry"
                        data-preview-url-param="${event.params.url}"
                        data-preview-ratio-param="${event.params.ratio}">
                            Failed to load. Click here to retry.
                    </a>
                </div>`
            this.containerTarget.innerHTML = failedHtml;
            this.containerTarget.classList.remove('hidden');
        } finally {
            this.loadingValue = false;
        }
    }

    async fetchEmbed(url) {
        if (!this.previewHtml) {
            let response = await fetch(router().generate('ajax_fetch_embed', {url: url}), {method: 'GET'});

            response = await ok(response);
            response = await response.json();

            this.previewHtml = response.html;
        }

        return this.previewHtml;
    }

    loadScripts(response) {
        let tmp = document.createElement("div");
        tmp.innerHTML = response;
        let el = tmp.getElementsByTagName('script');

        if (el.length) {
            let script = document.createElement("script");
            script.setAttribute("src", el[0].getAttribute('src'));
            script.setAttribute("async", "false");

            // let exists = [...document.head.querySelectorAll('script')]
            //     .filter(value => value.getAttribute('src') >= script.getAttribute('src'));
            //
            // if (exists.length) {
            //     return;
            // }

            let head = document.head;
            head.insertBefore(script, head.firstElementChild);
        }
    }

    loadingValueChanged(val) {
        const subject = this.element.closest('.subject');
        if (null !== subject) {
            const subjectController = this.application.getControllerForElementAndIdentifier(subject, 'subject');
            subjectController.loadingValue = val;
        }
    }
}
