import { Controller } from '@hotwired/stimulus';

const COMMENT_DEPTH_VALUE_NAME = 'commentCollapseDepthValue';
const COMMENT_ELEMENT_TAG = 'blockquote';
const COLLAPSED_CLASS = 'collapsed';
const HIDDEN_CLASS = 'hidden'
const HIDDEN_EVENT = 'toggleHideSibling';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        depth: Number,
        collapsed: Number,
        hiddenBy: Number,
    };
    static targets = ['collapse', 'expand', 'count'];

    // main entrypoint, use this in action
    toggleCollapse(event) {
        for (
            var nextSibling = this.element.nextElementSibling, siblingCount = 0;
            (nextSibling && COMMENT_ELEMENT_TAG.toUpperCase() === nextSibling.tagName);
            nextSibling = nextSibling.nextElementSibling, siblingCount++
        ) {
            let siblingDepth = nextSibling.dataset[COMMENT_DEPTH_VALUE_NAME];
            if (!siblingDepth || siblingDepth <= this.depthValue) {
                break;
            }

            this.toggleHideSibling(nextSibling, this.depthValue);
        }

        this.toggleCollapseSelf(siblingCount);
    }

    // signals sibling comment element to hide itself
    toggleHideSibling(commentElement, collapserDepth) {
        commentElement.dispatchEvent(
            new CustomEvent(`${this.identifier}:${HIDDEN_EVENT}`, {
                detail: { collapserDepth }
            })
        );
    }

    // put itself into hidden state
    toggleHideSelf({ detail: { collapserDepth }}) {
        if (!this.hasHiddenByValue) {
            this.hiddenByValue = collapserDepth;
        } else if (this.hiddenByValue === collapserDepth) {
            this.hiddenByValue = undefined;
        }
    }

    // put itself into collapsed state
    toggleCollapseSelf(count) {
        if (!this.hasCollapsedValue) {
            this.collapsedValue = count;
        } else {
            this.collapsedValue = undefined;
        }
    }

    // using value changed callback to enforce proper state appearance

    // existence of hidden-by value means this comment is in hidden state
    // (basically display: none)
    hiddenByValueChanged() {
        if (this.hasHiddenByValue) {
            this.element.classList.add(HIDDEN_CLASS);
        } else {
            this.element.classList.remove(HIDDEN_CLASS);
        }
    }

    // existence of collapsed value means this comment is in collapsed state
    // (visually dimmed, content hidden, expand button shown)
    collapsedValueChanged() {
        if (this.hasCollapsedValue) {
            this.element.classList.add(COLLAPSED_CLASS);
            this.showExpandButton(this.collapsedValue);
        } else {
            this.element.classList.remove(COLLAPSED_CLASS);
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
