import {Controller} from '@hotwired/stimulus';
import Subscribe from '../utils/event-source';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        kbinUser: String,
        kbinMagazine: String,
        kbinEntryId: String,
        kbinPostId: String,
    }

    connect() {
        this.es(this.getTopics());

        window.onbeforeunload = function (event) {
            if (window.es !== undefined) {
                window.es.close();
            }
        };
    }

    es(topics) {
        if (window.es !== undefined) {
            window.es.close();
        }

        let self = this;
        let cb = function (e) {
            let data = JSON.parse(e.data);

            self.dispatch(data.op, {detail: data});

            self.dispatch('Notification', {detail: data});

            // if (data.op.includes('Create')) {
            //     self.dispatch('CreatedNotification', {detail: data});
            // }

            // if (data.op === 'EntryCreatedNotification' || data.op === 'PostCreatedNotification') {
            //     self.dispatch('MainSubjectCreatedNotification', {detail: data});
            // }
            //
        }

        window.es = Subscribe(topics, cb);
        // firefox bug: https://bugzilla.mozilla.org/show_bug.cgi?id=1803431
        if (navigator.userAgent.toLowerCase().indexOf('firefox') > -1) {
            let resubscribe = (e) => {
                window.es.close();
                window.es = Subscribe(topics, cb);
                window.es.onerror = resubscribe;
            };
            window.es.onerror = resubscribe;
        }
    }

    getTopics() {
        let pub = true;
        const topics = [
            'count'
        ]

        if (this.kbinUserValue) {
            topics.push(`/api/user/${this.kbinUserValue}`);
            pub = true;
        }

        if (this.kbinMagazineValue) {
            topics.push(`/api/magazines/${this.kbinMagazineValue}`);
            pub = false;
        }

        if (this.kbinEntryIdValue) {
            topics.push(`/api/entries/${this.kbinEntryIdValue}`);
            pub = false;
        }

        if (this.kbinPostIdValue) {
            topics.push(`/api/posts/${this.kbinPostIdValue}`);
            pub = false;
        }

        if (pub) {
            topics.push('pub');
        }

        return topics;
    }
}
