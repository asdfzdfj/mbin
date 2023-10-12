import { Controller } from '@hotwired/stimulus';

const CONTROLLER_ELEMENT_NAME = 'commentCollapseController';
const COMMENT_ELEMENT_TAG = 'blockquote'

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        depth: Number,
        collapsedDepth: Number,
    };

    connect() {
        this.element[CONTROLLER_ELEMENT_NAME] = this;
    }

    toggleCollapse(event) {
        for (
            var nextSibling = this.element.nextElementSibling,
                updated = 0;
            null !== nextSibling || COMMENT_ELEMENT_TAG !== nextSibling.tagName;
            nextSibling = nextSibling.nextElementSibling,
            updated++
        ) {
            let nextController = nextSibling[CONTROLLER_ELEMENT_NAME];
            if (!nextController || nextController.depthValue <= this.depthValue) {
                break;
            }

            nextController.toggleHideComment(this.depthValue);
        }

        this.toggleCollapseSelf(updated);
    }

    collapsedDepthValueChanged(current, old) {
        if (this.hasCollapsedDepthValue) {
            this.element.classList.add('hidden');
        } else {
            this.element.classList.remove('hidden');
        }
    }

    toggleCollapseSelf(updated) {
        console.log('collapsing self:', this.element);
        console.log('collapsed %s children:', updated);
        this.element.classList.toggle('collapsed');
    }

    toggleHideComment(collapserDepth) {
        if (!this.hasCollapsedDepthValue) {
            this.collapsedDepthValue = collapserDepth;
        }
        else if (this.hasCollapsedDepthValue && this.collapsedDepthValue === collapserDepth) {
            this.collapsedDepthValue = undefined;
        }
    }
}

