const COMMENT_DEPTH_VALUE_NAME = 'commentCollapseDepthValue';

export function getDepth(element) {
    let level = parseInt(element.dataset[COMMENT_DEPTH_VALUE_NAME]);
    return isNaN(level) ? 1 : level;
}

export function setCommentDepth(element, depth) {
    element.dataset[COMMENT_DEPTH_VALUE_NAME] = depth.toString();
}
