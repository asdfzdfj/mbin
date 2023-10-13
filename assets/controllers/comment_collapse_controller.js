import { Controller } from '@hotwired/stimulus';

const CONTROLLER_ELEMENT_NAME = 'commentCollapseController';
const COMMENT_ELEMENT_TAG = 'blockquote';
const COLLAPSED_CLASS = 'collapsed';
const HIDDEN_CLASS = 'hidden';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        depth: Number,
        collapsedDepth: Number,
    };
    static targets = ['collapse', 'expand', 'count'];

    connect() {
        // ugly hack to expose this controller instance for parent comment
        // to use when collapsing comments
        this.element[CONTROLLER_ELEMENT_NAME] = this;
    }

    // main entrypoint, use this in action
    toggleCollapse(event) {
        for (
            var nextSibling = this.element.nextElementSibling, siblingCount = 0;
            (nextSibling && COMMENT_ELEMENT_TAG.toUpperCase() === nextSibling.tagName);
            nextSibling = nextSibling.nextElementSibling, siblingCount++
        ) {
            let nextController = nextSibling[CONTROLLER_ELEMENT_NAME];
            if (!nextController || nextController.depthValue <= this.depthValue) {
                break;
            }

            nextController.toggleHideComment(this.depthValue);
        }

        this.toggleCollapseSelf(siblingCount);
    }

    // this function is only meant to be called from parent comment controller
    // to collapse child comment
    toggleHideComment(collapserDepth) {
        if (!this.hasCollapsedDepthValue) {
            this.collapsedDepthValue = collapserDepth;
        } else if (this.collapsedDepthValue === collapserDepth) {
            this.collapsedDepthValue = undefined;
        }
    }

    collapsedDepthValueChanged() {
        if (this.hasCollapsedDepthValue) {
            this.element.classList.add(HIDDEN_CLASS);
        } else {
            this.element.classList.remove(HIDDEN_CLASS);
        }
    }

    toggleCollapseSelf(count) {
        this.element.classList.toggle(COLLAPSED_CLASS);

        if (this.element.classList.contains(COLLAPSED_CLASS)) {
            this.showExpandButton(count);
        } else {
            this.showCollapsedButton();
        }
    }

    showCollapsedButton() {
        this.expandTarget.classList.add(HIDDEN_CLASS);
        this.collapseTarget.classList.remove(HIDDEN_CLASS);
    }
    showExpandButton(count) {
        this.collapseTarget.classList.add(HIDDEN_CLASS);
        this.expandTarget.classList.remove(HIDDEN_CLASS);
        if (count > 0) {
            this.countTarget.innerText = `(${count}) `;
        }
    }
}
