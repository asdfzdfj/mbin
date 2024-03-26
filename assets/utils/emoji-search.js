import emojilib from 'emojilib';
import { matchSorter } from 'match-sorter';

export function buildEmojiData() {
    let emojis = Object.entries(emojilib)
        .map(([emoji, keywords]) => {
            return {
                keywords: keywords.filter((v) => !/\s/.test(v)),
                emoji,
            };
        });

    return emojis;
}

export function searchEmoji(dataset, query) {
    let matches = matchSorter(
        dataset,
        query,
        {
            keys: [item => item.keywords.map(
                (v) => v.replace(/_/g, ' '),
            )],
            threshold: matchSorter.rankings.WORD_STARTS_WITH,
        },
    );

    return matches;
}
