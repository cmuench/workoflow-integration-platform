// Initialize Sentry before anything else so it captures all errors
import * as Sentry from '@sentry/browser';

const htmlEl = document.documentElement;
const sentryDsn = htmlEl.dataset.sentryDsn;
if (sentryDsn) {
    Sentry.init({
        dsn: sentryDsn,
        environment: htmlEl.dataset.sentryEnvironment || 'dev',
        // Resilience: if Sentry is unreachable, errors are silently dropped
        transport: Sentry.makeFetchTransport,
        beforeSend(event) {
            return event;
        },
    });
}

// Import styles
import './styles/app.scss';

// Import Font Awesome
import '@fortawesome/fontawesome-free/css/all.css';

// Import flag icons
import 'flag-icons/css/flag-icons.min.css';

// Import n8n demo web component
import '@n8n_io/n8n-demo-component';

// Start the Stimulus application
import './bootstrap';
