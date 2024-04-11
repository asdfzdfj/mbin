import { buildTopics, subscribe } from '../utils/event-source';
import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        endpoint: String,
        user: String,
        magazine: String,
        entryId: String,
        postId: String,
    };

    connect() {
        if (this.endpointValue) {
            this.connectEs();
        }
    }

    disconnect() {
        this.terminateEs();
    }

    terminateEs() {
        if (window.es instanceof EventSource) {
            window.es.close();
            window.es = null;
        }
    }

    suspendEs({ persisted }) {
        this.terminateEs();
        console.log('suspending es, terminating mercure connection', persisted);
    }

    // only attempt restore when it's pulled out of bfcache
    // and earlier connection has touched window.es var
    restoreEs({ persisted }) {
        if (persisted && undefined !== window.es) {
            console.log('restoring es, reconnecting to mercure', persisted);
            this.connectEs();
        }
    }

    connectEs() {
        this.terminateEs();

        const endpoint = this.endpointValue;
        const topics = buildTopics({
            user: this.userValue,
            magazine: this.magazineValie,
            entryId: this.entryIdValue,
            postId: this.postIdValue,
        });
        const callback = (e) => {
            const data = JSON.parse(e.data);

            this.dispatch(data.op, { detail: data });
            this.dispatch('Notification', { detail: data });
        };

        const eventSource = subscribe(endpoint, topics, callback);
        if (eventSource) {
            window.es = eventSource;

            // firefox bug: https://bugzilla.mozilla.org/show_bug.cgi?id=1803431
            if (navigator.userAgent.toLowerCase().includes('firefox')) {
                const resubscribe = () => {
                    this.terminateEs();
                    setTimeout(
                        () => {
                            const eventSource = subscribe(endpoint, topics, callback);
                            if (eventSource) {
                                window.es = eventSource;
                                window.es.onerror = resubscribe;
                            }
                        },
                        10000,
                    );
                };

                window.es.onerror = resubscribe;
            }
        }
    }
}
