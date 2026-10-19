import DOMPurify from "dompurify";

/** Sanitize at the final HTML sink; never concatenate markup afterward.
 * @param {unknown} value
 * @returns {string}
 */
export function sanitizeRichText(value) {
  if (typeof value !== "string" || !DOMPurify.isSupported) return "";
  return DOMPurify.sanitize(value, {
    ALLOWED_TAGS: ["p", "br", "strong", "em", "b", "i", "u", "s", "ol", "ul", "li", "h1", "h2", "h3", "h4", "h5", "h6", "span", "div", "blockquote", "pre", "code", "a"],
    ALLOWED_ATTR: ["href", "title", "class", "data-list"],
    ALLOW_DATA_ATTR: false,
    ALLOW_ARIA_ATTR: false,
    RETURN_TRUSTED_TYPE: false,
  });
}
