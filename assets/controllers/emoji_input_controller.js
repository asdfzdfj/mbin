import { buildEmojiData, searchEmoji } from '../utils/emoji-search';
import { Controller } from '@hotwired/stimulus';
import { useDebounce } from 'stimulus-use';

// ref:
// https://phuoc.ng/collection/mirror-a-text-area/add-autocomplete-to-your-text-area/
// https://github.com/yuku/textcomplete

const CONTAINER_CLASS = 'emoji-input-suggestion';
const CONTAINER_ACTIVE_CLASS = 'active';
const OPTION_CLASS = CONTAINER_CLASS + '-item';
const OPTION_FOCUSED_CLASS = 'focused';

const SHORTCODE_PATTERN = /\B:([\w_+-]+)$/;

const CURSOR_MOVE_AMOUNT = {
    'PageUp': -4,
    'PageDown': 4,
    'ArrowUp': -1,
    'ArrowDown': 1,
};

function clamp(min, value, max) {
    return Math.min(Math.max(min, value), max);
}

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static debounces = ['queryCustomEmoji'];
    static targets = ['input', 'suggestion'];
    static values = {
        optionIndex: { type: Number, default: 0 },
        wideSuggestion: { type: Boolean, default: false },
    };

    initialize() {
        this.emojiData = buildEmojiData();
    }

    connect() {
        useDebounce(this, { wait: 200 });

        this.suggestionsElement = this.createSuggestionContainer(this.wideSuggestionValue);

        this.inputElement = this.element;
        this.inputElement.insertAdjacentElement('afterend', this.suggestionsElement);

        if (!this.inputElement || !this.suggestionsElement) {
            console.warn('some required element(s) is missing', [this.inputElement, this.suggestionsElement]);
        }
    }

    createSuggestionContainer(wide) {
        let suggestion = document.createElement('div');
        suggestion.classList.add(CONTAINER_CLASS);
        suggestion.classList.toggle('wide', wide);

        return suggestion;
    }

    setSuggestionOptions(optionsElements) {
        this.suggestionsElement.replaceChildren(...optionsElements);
        this.suggestionsElement.classList.toggle(CONTAINER_ACTIVE_CLASS, optionsElements.length > 0);
    }

    getSuggestionOption(index) {
        return this.suggestionsElement.children[index];
    }

    clearSuggestionOptions() {
        this.setSuggestionOptions([]);
    }

    resetSelectionState() {
        this.optionIndexValue = 0;
    }

    createOptionElements(choices, selectIndex) {
        let options = choices.map((match, index) => {
            let shortcode = match.keywords[0];
            let replacement = match.emoji ?? `:${shortcode}:`;

            let option = document.createElement('div');
            option.classList.add(OPTION_CLASS);
            option.classList.toggle(OPTION_FOCUSED_CLASS, index === selectIndex);

            let preview = document.createElement('span');
            if (match.emoji) {
                preview.innerText = match.emoji;
            } else if (match.image) {
                let icon = document.createElement('img');
                icon.classList.add('emoji');
                icon.src = match.image;
                preview.appendChild(icon);
            }

            let label = document.createElement('span');
            label.classList.add('shortcode');
            label.innerText = shortcode;

            option.dataset.emojiInputReplaceParam = replacement;

            option.addEventListener('click', () => {
                this.replaceCurrentWord(this.inputElement, replacement);
                this.clearSuggestionOptions();
            });

            option.appendChild(preview);
            option.appendChild(label);

            return option;
        });

        return options;
    }

    replaceCurrentWord(inputElement, replacement) {
        let cursorPos = this.getCursorLocation(inputElement);

        let inputValue = inputElement.value;
        let preValue = inputValue.substring(0, cursorPos);
        let postValue = inputValue.substring(cursorPos);

        let replacedPreValue = preValue.replace(SHORTCODE_PATTERN, replacement);
        let newValue = replacedPreValue + postValue;

        inputElement.value = newValue;
        inputElement.setSelectionRange(replacedPreValue.length, replacedPreValue.length);
        inputElement.focus();
    }

    getCursorLocation(inputElement) {
        return inputElement.selectionStart;
    }

    extractCompletionQuery(input) {
        let matches = input.match(SHORTCODE_PATTERN);

        return matches ? matches[1] : '';
    }

    interact() {
        let cursorPos = this.getCursorLocation(this.inputElement);

        let inputValue = this.inputElement.value;
        let preValue = inputValue.substring(0, cursorPos);

        console.log(preValue.match(SHORTCODE_PATTERN));

        let query = this.extractCompletionQuery(preValue);
        if (query) {
            let results = searchEmoji(this.emojiData, query);
            console.log('tracking shortcode query: %s', query);
            console.log('search result', results);

            this.optionIndexValue = 0;
            let suggestions = this.createOptionElements(results, this.optionIndexValue);
            this.setSuggestionOptions(suggestions);
        } else {
            this.clearSuggestionOptions();
            this.resetSelectionState();
        }
    }

    selectionInteract(event) {
        let key = event.key;
        if (!['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Enter', 'Tab', 'Escape'].includes(key)) {
            return;
        }

        let suggestionLength = this.suggestionsElement.children.length;
        if (suggestionLength === 0) {
            return;
        }

        console.log('tracking selection interaction: ', key);
        event.preventDefault();

        let index = this.optionIndexValue;
        switch (key) {
            case 'ArrowUp':
            case 'ArrowDown':
            case 'PageUp':
            case 'PageDown':
                this.optionIndexValue = clamp(0, index + CURSOR_MOVE_AMOUNT[key], suggestionLength - 1);
                break;
            case 'Enter':
            case 'Tab': {
                let replacement = this.getSuggestionOption(index).dataset.emojiInputReplaceParam;
                this.replaceCurrentWord(this.inputElement, replacement);
                this.clearSuggestionOptions();
                break;
            }
            case 'Escape':
                this.clearSuggestionOptions();
                break;
            default:
                break;
        }
    }

    optionIndexValueChanged(newIndex, oldIndex) {
        if (!this.suggestionsElement || this.suggestionsElement.children.length === 0) {
            return;
        }

        if (newIndex !== oldIndex) {
            let newOption = this.getSuggestionOption(newIndex);
            newOption.classList.add(OPTION_FOCUSED_CLASS);
            newOption.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            this.getSuggestionOption(oldIndex).classList.remove(OPTION_FOCUSED_CLASS);
        }
    }
}
