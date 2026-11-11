/**
 * Declarative toast triggers (resources/js/d2/lib/toast.js): any element with data-toast="message"
 * (and an optional data-toast-tone: neutral | info | success | warning | danger) shows that toast when
 * clicked. The message must be a server-provided (translated) string. From code: import { toast }.
 */
import { bindToastTriggers } from '../lib/toast.js';

export default function toasts() {
    bindToastTriggers();
}
