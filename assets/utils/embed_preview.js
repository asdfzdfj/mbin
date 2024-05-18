export function prepareEmbed(html) {
    const scratch = document.createElement('template');
    scratch.innerHTML = html;

    const preview = scratch.content.querySelector('.preview');

    const wrappedImage = scratch.content.querySelector('.preview > a.embed-link > img');
    if (wrappedImage) {
        wrappedImage.parentElement.dataset.controller = 'embed-lightbox';

        return scratch.innerHTML;
    }

    const unwrappedImage = scratch.content.querySelector('.preview > img');
    if (1 === preview.children.length && unwrappedImage === preview.firstElementChild) {
        const wrappedLinkImage = wrapImage(unwrappedImage);

        preview.innerHTML = wrappedLinkImage.outerHTML;

        return scratch.innerHTML;
    }

    return html;
}

/** @param {HTMLImageElement} image */
function wrapImage(image) {
    const wrapped = document.createElement('a');
    wrapped.href = image.src;
    wrapped.classList.add('embed-link');
    wrapped.dataset.controller = 'embed-lightbox';
    wrapped.appendChild(image);

    return wrapped;
}
