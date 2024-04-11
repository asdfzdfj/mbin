export function subscribe(endpoint, topics, callback) {
    if (!endpoint) {
        return null;
    }

    const url = new URL(endpoint);

    topics.forEach((topic) => {
        url.searchParams.append('topic', topic);
    });

    const eventSource = new EventSource(url);
    eventSource.onmessage = callback;

    return eventSource;
}

export function buildTopics({ user, magazine, entryId, postId }) {
    const topics = ['count'];
    const pub = !(magazine || entryId || postId);

    if (user) {
        topics.push(`/api/users/${user}`);
    }

    if (magazine) {
        topics.push(`/api/magazines/${magazine}`);
    }

    if (entryId) {
        topics.push(`/api/entries/${entryId}`);
    }

    if (postId) {
        topics.push(`/api/posts/${postId}`);
    }

    if (pub) {
        topics.push('pub');
    }

    return topics;
}
